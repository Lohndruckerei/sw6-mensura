<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Service\Storefront;

use Shopware\Storefront\Page\Product\ProductPageCriteriaEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Produktdetailseite: Produktprofil (`product.extensions.ldMensura`) mitladen, damit das Storefront-Template
 * den Konfigurator einblendet. Das Profil enthält keine Einkaufspreise.
 */
class ProductPageSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [ProductPageCriteriaEvent::class => 'onProductPageCriteria'];
    }

    public function onProductPageCriteria(ProductPageCriteriaEvent $event): void
    {
        $event->getCriteria()->addAssociation('ldMensura');
    }
}
