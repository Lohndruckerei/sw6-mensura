<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Service\Storefront;

use Shopware\Core\Content\Product\Events\ProductCrossSellingIdsCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductCrossSellingStreamCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSliderStaticCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSliderStreamCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSuggestCriteriaEvent;
use Shopware\Storefront\Page\Product\ProductPageCriteriaEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Produktprofil (`product.extensions.ldMensura`) mitladen – auf der Detailseite (Konfigurator statt Kaufformular)
 * und in Listings, Suche, Cross-Selling und Produkt-Slidern (Kauf-Button wird zu „Konfigurieren“, siehe
 * Storefront-Templates). Das Profil enthält keine Einkaufspreise.
 */
class ProductPageSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageCriteriaEvent::class => 'addProfile',
            ProductListingCriteriaEvent::class => 'addProfile',
            ProductSearchCriteriaEvent::class => 'addProfile',
            ProductSuggestCriteriaEvent::class => 'addProfile',
            ProductCrossSellingIdsCriteriaEvent::class => 'addProfile',
            ProductCrossSellingStreamCriteriaEvent::class => 'addProfile',
            ProductSliderStaticCriteriaEvent::class => 'addProfile',
            ProductSliderStreamCriteriaEvent::class => 'addProfile',
        ];
    }

    public function addProfile(object $event): void
    {
        if (method_exists($event, 'getCriteria')) {
            $event->getCriteria()->addAssociation('ldMensura');
        }
    }
}
