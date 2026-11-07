<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Controller\Admin;

use Lohndruckerei\Mensura\Service\Calculation\CatalogLoader;
use Lohndruckerei\Mensura\Service\Calculation\ContextFactory;
use Lohndruckerei\Mensura\Service\Calculation\PriceEngine;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin-API der Mensura-Administration.
 *
 * POST /api/_action/ld-mensura/test-calculate – Testrechner einer Kalkulation (KONZEPT 3a Punkt 4, Abschnitt 5):
 * rechnet mit derselben Engine wie Shop und Warenkorb und liefert den Rechenweg Zeile für Zeile. EK-Rechenweg,
 * EK gesamt und Deckungsbeitrag nur mit dem Recht `ld_mensura.purchase` (KONZEPT 3b, serverseitig geprüft).
 */
#[Route(defaults: ['_routeScope' => ['api']])]
class MensuraAdminApiController
{
    public const PURCHASE_PRIVILEGE = 'ld_mensura.purchase';

    public function __construct(
        private readonly CatalogLoader $loader,
        private readonly PriceEngine $engine,
        private readonly ContextFactory $contextFactory,
    ) {
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
        $calculationId = $body['calculationId'] ?? null;
        if (!\is_string($calculationId) || preg_match('/^[0-9a-f]{32}$/', $calculationId) !== 1) {
            return new JsonResponse(['errors' => [['code' => 'request.invalid_calculation', 'detail' => 'calculationId fehlt oder ist ungültig']]], 400);
        }
        $catalog = $this->loader->loadForCalculation($calculationId, $context);
        if ($catalog === null) {
            return new JsonResponse(['errors' => [['code' => 'calculation.not_found', 'detail' => 'Kalkulation nicht gefunden']]], 404);
        }

        $configuration = \is_array($body['configuration'] ?? null) ? $body['configuration'] : [];
        $configuration['schema'] = 1;
        $configuration['productId'] = $calculationId;
        $customerGroupId = \is_string($body['customerGroupId'] ?? null) ? $body['customerGroupId'] : null;
        $language = \is_string($body['language'] ?? null) ? $body['language'] : 'de';

        $result = $this->engine->calculate($configuration, $catalog, $this->contextFactory->forAdministration($customerGroupId, $language), true);
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
}
