<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Controller\Storefront;

use Lohndruckerei\Mensura\Controller\StoreApi\MensuraStoreApiController;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Storefront-Routen des Konfigurators (Sitzungskontext statt Store-API-Token, das Shopware 6.7 in Twig nicht
 * mehr herausgibt): gleiche Bodies und Antworten wie die Store-API-Routen laut docs/contract – intern dieselben
 * Methoden mit dem SalesChannelContext der Sitzung. So gelten Kundengruppenpreise des angemeldeten Kunden, und
 * „In den Warenkorb“ landet im Warenkorb der Sitzung. Nur per XmlHttpRequest, nie im HTTP-Cache.
 */
#[Route(defaults: ['_routeScope' => ['storefront'], 'XmlHttpRequest' => true, '_httpCache' => false])]
class MensuraStorefrontController
{
    public function __construct(private readonly MensuraStoreApiController $storeApi)
    {
    }

    #[Route(path: '/ld-mensura/configurator/{productId}', name: 'frontend.ld-mensura.configurator', methods: ['GET'])]
    public function configurator(string $productId, SalesChannelContext $context): Response
    {
        return $this->private($this->storeApi->configurator($productId, $context));
    }

    #[Route(path: '/ld-mensura/calculate', name: 'frontend.ld-mensura.calculate', methods: ['POST'])]
    public function calculate(Request $request, SalesChannelContext $context): Response
    {
        return $this->private($this->storeApi->calculate($request, $context));
    }

    #[Route(path: '/ld-mensura/cart/add', name: 'frontend.ld-mensura.cart.add', methods: ['POST'])]
    public function cartAdd(Request $request, SalesChannelContext $context): Response
    {
        return $this->private($this->storeApi->cartAdd($request, $context));
    }

    /** Antworten hängen an Kunde und Warenkorb – nie zwischenspeichern. */
    private function private(Response $response): Response
    {
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
