<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Controller\Admin;

use Lohndruckerei\Mensura\Service\Calculation\CatalogLoader;
use Lohndruckerei\Mensura\Service\Calculation\ContextFactory;
use Lohndruckerei\Mensura\Service\Calculation\LogisticsPreview;
use Lohndruckerei\Mensura\Service\Security\PurchaseFieldGuard;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin-API für den Logistikanteil (KONZEPT 3d.6/3d.7), nur mit Recht `ld_mensura.logistics`
 * (Entity-Recht `ld_mensura_logistics_rule:read`, serverseitig über `_acl` geprüft).
 *
 * - POST /api/_action/ld-mensura/logistics/preview – Body {calculationId, rules[] (Entwurf), samples[]}:
 *   je Beispiel VK ohne/mit Logistik, Anteil am VK, Warnung über `LdMensura.config.logisticsWarnPercent`.
 */
#[Route(defaults: ['_routeScope' => ['api']])]
class MensuraLogisticsAdminController
{
    public function __construct(
        private readonly CatalogLoader $loader,
        private readonly LogisticsPreview $preview,
        private readonly ContextFactory $contextFactory,
        private readonly SystemConfigService $systemConfig,
    ) {
    }

    #[Route(
        path: '/api/_action/ld-mensura/logistics/preview',
        name: 'api.action.ld-mensura.logistics-preview',
        defaults: ['_acl' => ['ld_mensura_logistics_rule:read', 'ld_mensura_calculation:read']],
        methods: ['POST'],
    )]
    public function preview(Request $request, Context $context): JsonResponse
    {
        $body = $request->toArray();
        $calculationId = $body['calculationId'] ?? null;
        if (!\is_string($calculationId) || preg_match('/^[0-9a-f]{32}$/', $calculationId) !== 1) {
            return new JsonResponse(['errors' => [['code' => 'request.invalid_source', 'detail' => 'calculationId fehlt oder ist ungültig']]], 400);
        }
        // intern mit EK rechnen (Logistik-Form `percent` braucht den Waren-EK); ausgeliefert wird kein EK
        $catalog = $context->state(fn () => $this->loader->loadForCalculation($calculationId, $context), PurchaseFieldGuard::STATE_INTERNAL);
        if ($catalog === null) {
            return new JsonResponse(['errors' => [['code' => 'calculation.not_found', 'detail' => 'Kalkulation nicht gefunden']]], 404);
        }
        $warnPercent = (float) ($this->systemConfig->get('LdMensura.config.logisticsWarnPercent') ?? 15);
        $language = \is_string($body['language'] ?? null) ? $body['language'] : 'de';

        return new JsonResponse($this->preview->preview(
            $catalog,
            \is_array($body['rules'] ?? null) ? array_values($body['rules']) : [],
            \is_array($body['samples'] ?? null) ? array_values($body['samples']) : [],
            $this->contextFactory->forAdministration(null, $language),
            $warnPercent > 0 ? $warnPercent : 15.0,
        ));
    }
}
