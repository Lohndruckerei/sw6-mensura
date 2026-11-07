<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Controller\StoreApi;

use Lohndruckerei\Mensura\Service\Calculation\CatalogLoader;
use Lohndruckerei\Mensura\Service\Calculation\ConfiguratorBuilder;
use Lohndruckerei\Mensura\Service\Calculation\ContextFactory;
use Lohndruckerei\Mensura\Service\Calculation\PriceEngine;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Store-API laut docs/contract/store-api.yaml. Alle Preise netto für die Kundengruppe des Kontexts,
 * berechnet ausschließlich hier (nie Preise des Frontends übernehmen). Keine Einkaufspreise.
 */
#[Route(defaults: ['_routeScope' => ['store-api']])]
class MensuraStoreApiController
{
    private const ID_PATTERN = '/^[0-9a-f]{32}$/';

    public function __construct(
        private readonly CatalogLoader $loader,
        private readonly PriceEngine $engine,
        private readonly ConfiguratorBuilder $builder,
        private readonly ContextFactory $contextFactory,
    ) {
    }

    #[Route(path: '/store-api/ld-mensura/product/{productId}/configurator', name: 'store-api.ld-mensura.configurator', methods: ['GET'])]
    public function configurator(string $productId, SalesChannelContext $context): JsonResponse
    {
        if (preg_match(self::ID_PATTERN, $productId) !== 1) {
            return $this->error(400, 'request.invalid_product_id', 'productId muss eine 32-stellige Hex-ID sein');
        }
        $catalog = $this->loader->load($productId, $context->getContext());
        if ($catalog === null) {
            return $this->error(404, 'product.not_configurable', 'Produkt hat kein Mensura-Profil');
        }

        return $this->json($this->builder->build($catalog, $this->contextFactory->fromSalesChannelContext($context)));
    }

    #[Route(path: '/store-api/ld-mensura/calculate', name: 'store-api.ld-mensura.calculate', methods: ['POST'])]
    public function calculate(Request $request, SalesChannelContext $context): JsonResponse
    {
        return $this->run($request, $context, $request->toArray()['matrix'] ?? false);
    }

    /** Vorlage (z. B. vom Produktberater) prüfen: gleiche Regeln wie calculate. */
    #[Route(path: '/store-api/ld-mensura/preset/validate', name: 'store-api.ld-mensura.preset-validate', methods: ['POST'])]
    public function presetValidate(Request $request, SalesChannelContext $context): JsonResponse
    {
        return $this->run($request, $context, false);
    }

    private function run(Request $request, SalesChannelContext $context, mixed $matrix): JsonResponse
    {
        try {
            $body = $request->toArray();
        } catch (\Throwable) {
            return $this->error(400, 'request.invalid_json', 'Anfrage ist kein gültiges JSON');
        }
        $configuration = $body['configuration'] ?? null;
        if (!\is_array($configuration) || !\is_string($configuration['productId'] ?? null) || preg_match(self::ID_PATTERN, $configuration['productId']) !== 1) {
            return $this->error(400, 'request.invalid_configuration', 'configuration.productId fehlt oder ist ungültig');
        }
        $catalog = $this->loader->load($configuration['productId'], $context->getContext());
        if ($catalog === null) {
            return $this->error(404, 'product.not_configurable', 'Produkt hat kein Mensura-Profil');
        }
        $result = $this->engine->calculate($configuration, $catalog, $this->contextFactory->fromSalesChannelContext($context), $matrix === true);

        return $this->json($result->toStoreApi());
    }

    /** @param array<string, mixed> $data */
    private function json(array $data, int $status = 200): JsonResponse
    {
        $response = new JsonResponse(null, $status);
        $response->setEncodingOptions(\JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION);
        $response->setData($data);

        return $response;
    }

    private function error(int $status, string $code, string $message): JsonResponse
    {
        return $this->json(['errors' => [['status' => (string) $status, 'code' => $code, 'detail' => $message]]], $status);
    }
}
