<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Checkout\OrderLineItem;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Logistik-Schnappschuss einer bestellten Position (1:1 zu order_line_item, `extensions.ldMensuraLogistics`),
 * KONZEPT 3d.5 / INTEGRATION.md 7.1. Nicht ApiAware; lesen nur mit `ld_mensura.logistics`.
 * `goodsContributionMargin` verrät den EK und wird zusätzlich vom EK-Schutz (PurchaseFieldGuard) behandelt.
 */
class MensuraOrderLineItemLogisticsDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ld_mensura_order_line_item_logistics';

    public const SCHEMA_VERSION = 1;

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return MensuraOrderLineItemLogisticsEntity::class;
    }

    public function getCollectionClass(): string
    {
        return MensuraOrderLineItemLogisticsCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('order_line_item_id', 'orderLineItemId', OrderLineItemDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(OrderLineItemDefinition::class))->addFlags(new Required()),
            (new IntField('schema_version', 'schemaVersion'))->addFlags(new Required()),
            new FloatField('logistics_sale', 'logisticsSale'),
            new FloatField('production_time_sale', 'productionTimeSale'),
            new FloatField('share_percent', 'sharePercent'),
            new FloatField('goods_contribution_margin', 'goodsContributionMargin'),
            new JsonField('lines', 'lines'),

            new OneToOneAssociationField('orderLineItem', 'order_line_item_id', 'id', OrderLineItemDefinition::class, false),
        ]);
    }

    public function getDefaults(): array
    {
        return ['schemaVersion' => self::SCHEMA_VERSION];
    }
}
