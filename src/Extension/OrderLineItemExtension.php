<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Extension;

use Lohndruckerei\Mensura\Core\Checkout\OrderLineItem\MensuraOrderLineItemDefinition;
use Lohndruckerei\Mensura\Core\Checkout\OrderLineItem\MensuraOrderLineItemLogisticsDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * `orderLineItem.extensions.ldMensura` → Rechenweg-Schnappschuss (nur Admin-API, kein ApiAware).
 */
class OrderLineItemExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToOneAssociationField('ldMensura', 'id', 'order_line_item_id', MensuraOrderLineItemDefinition::class, false))
                ->addFlags(new CascadeDelete()),
        );
        // Logistik-Schnappschuss (KONZEPT 3d.5): eigenes Recht ld_mensura.logistics, nie mitgeladen ohne Anfrage
        $collection->add(
            (new OneToOneAssociationField('ldMensuraLogistics', 'id', 'order_line_item_id', MensuraOrderLineItemLogisticsDefinition::class, false))
                ->addFlags(new CascadeDelete()),
        );
    }

    public function getEntityName(): string
    {
        return OrderLineItemDefinition::ENTITY_NAME;
    }
}
