<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Controller\Admin;

use Lohndruckerei\Mensura\Service\Calculation\CalculationContext;
use Lohndruckerei\Mensura\Service\Calculation\Catalog;
use Lohndruckerei\Mensura\Service\Calculation\CatalogLoader;
use Lohndruckerei\Mensura\Service\Calculation\ConfiguratorBuilder;
use Lohndruckerei\Mensura\Service\Calculation\ContextFactory;
use Lohndruckerei\Mensura\Service\Calculation\PriceEngine;
use Lohndruckerei\Mensura\Service\Security\PurchaseFieldGuard;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin-API der Mensura-Administration (Testrechner, KONZEPT 3a Punkt 4, Abschnitt 5).
 *
 * Quelle ist entweder eine Kalkulation (`calculationId`, alle ihre Materialien, ohne Produkt) oder ein
 * Produktprofil (`productId`, auch inaktiv) – jeweils der gespeicherte Stand.
 *
 * - POST /api/_action/ld-mensura/test-configurator – Konfigurator-Daten wie im Shop (ohne EK)
 * - POST /api/_action/ld-mensura/test-calculate – rechnet mit derselben Engine wie Shop und Warenkorb und
 *   liefert den Rechenweg. EK-Rechenweg, EK gesamt und Deckungsbeitrag nur mit dem Recht `ld_mensura.purchase`
 *   (KONZEPT 3b, serverseitig geprüft).
 */
#[Route(defaults: ['_routeScope' => ['api']])]
class MensuraAdminApiController
{
    public const PURCHASE_PRIVILEGE = PurchaseFieldGuard::PRIVILEGE;

    public function __construct(
        private readonly CatalogLoader $loader,
        private readonly PriceEngine $engine,
        private readonly ConfiguratorBuilder $configuratorBuilder,
        private readonly ContextFactory $contextFactory,
    ) {
    }

    #[Route(
        path: '/api/_action/ld-mensura/test-configurator',
        name: 'api.action.ld-mensura.test-configurator',
        defaults: ['_acl' => ['ld_mensura_calculation:read']],
        methods: ['POST'],
    )]
    public function testConfigurator(Request $request, Context $context): JsonResponse
    {
        $body = $request->toArray();
        [$catalog, $error] = $this->catalog($body, $context);
        if ($catalog === null) {
            return $error;
        }

        return new JsonResponse($this->configuratorBuilder->build($catalog, $this->calculationContext($body)));
    }

    #[Route(
        path: '/api/_action/ld-mensura/test-calculate',
        name: 'api.action.ld-mensura.test-calculate',
        defaults: ['_acl' => ['ld_mensura_calculation:read']],
        methods: ['POST'],
    )]
    public function testCalculate(Request $request, Context $context): JsonResponse
    {
        $body = $request->toArray();
        [$catalog, $error] = $this->catalog($body, $context);
        if ($catalog === null) {
            return $error;
        }

        $configuration = \is_array($body['configuration'] ?? null) ? $body['configuration'] : [];
        $configuration['schema'] = 1;
        $configuration['productId'] = $catalog->productId();

        $result = $this->engine->calculate($configuration, $catalog, $this->calculationContext($body), true);
        $snapshot = $result->toSnapshot();

        $response = [
            'result' => $result->toStoreApi(),
            'areas' => $result->getAreas(),
            'weight' => $result->getWeight(),
            'sale' => $snapshot['breakdown']['sale'],
            'purchase' => null,
            'contributionMargin' => null,
        ];
        if (self::canSeePurchase($context)) {
            $response['purchase'] = $snapshot['breakdown']['purchase'];
            $response['contributionMargin'] = $snapshot['contributionMargin'];
        }
        // Logistikanteil (KONZEPT 3d.7) nur mit eigenem Recht; sonst steckt er unsichtbar in der Zwischensumme
        $response['logistics'] = null;
        if (self::canSeeLogistics($context) && $result->getLogistics() !== null) {
            $logistics = $result->getLogistics();
            $response['logistics'] = [
                'sale' => $logistics['sale'],
                'sharePercent' => $logistics['sharePercent'],
                'lines' => array_map(static fn (array $line) => [
                    'costType' => $line['costType'],
                    'amount' => round((float) $line['amount'], 2),
                ], $logistics['lines']),
            ];
        }

        return new JsonResponse($response);
    }

    /** Admin oder Rolle mit `ld_mensura.purchase` darf Einkaufspreise sehen. */
    public static function canSeePurchase(Context $context): bool
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            return false;
        }

        return $source->isAdmin() || $source->isAllowed(self::PURCHASE_PRIVILEGE);
    }

    /** Admin oder Rolle mit `ld_mensura.logistics` darf den unsichtbaren Logistikanteil sehen. */
    public static function canSeeLogistics(Context $context): bool
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            return false;
        }

        return $source->isAdmin() || $source->isAllowed('ld_mensura.logistics');
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{0: ?Catalog, 1: ?JsonResponse}
     */
    private function catalog(array $body, Context $context): array
    {
        // intern mit EK rechnen (Rechenweg vollständig); ausgeliefert wird EK nur mit Recht (testCalculate)
        return $context->state(fn (): array => $this->loadCatalog($body, $context), PurchaseFieldGuard::STATE_INTERNAL);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{0: ?Catalog, 1: ?JsonResponse}
     */
    private function loadCatalog(array $body, Context $context): array
    {
        $isId = static fn (mixed $id): bool => \is_string($id) && preg_match('/^[0-9a-f]{32}$/', $id) === 1;
        if ($isId($body['productId'] ?? null)) {
            $catalog = $this->loader->load($body['productId'], $context, false);

            return $catalog === null
                ? [null, $this->error(404, 'profile.not_found', 'Produktprofil nicht gefunden oder ohne Material')]
                : [$catalog, null];
        }
        if ($isId($body['calculationId'] ?? null)) {
            $catalog = $this->loader->loadForCalculation($body['calculationId'], $context);

            return $catalog === null
                ? [null, $this->error(404, 'calculation.not_found', 'Kalkulation nicht gefunden')]
                : [$catalog, null];
        }

        return [null, $this->error(400, 'request.invalid_source', 'calculationId oder productId fehlt oder ist ungültig')];
    }

    /** @param array<string, mixed> $body */
    private function calculationContext(array $body): CalculationContext
    {
        $customerGroupId = \is_string($body['customerGroupId'] ?? null) ? $body['customerGroupId'] : null;
        $language = \is_string($body['language'] ?? null) ? $body['language'] : 'de';

        return $this->contextFactory->forAdministration($customerGroupId, $language);
    }

    private function error(int $status, string $code, string $detail): JsonResponse
    {
        return new JsonResponse(['errors' => [['code' => $code, 'detail' => $detail]]], $status);
    }
}
