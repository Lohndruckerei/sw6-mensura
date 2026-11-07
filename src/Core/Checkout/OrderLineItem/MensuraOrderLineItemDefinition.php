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
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Rechenweg-Schnappschuss einer bestellten Position (1:1 zu order_line_item, `extensions.ldMensura`).
 *
 * Bewusst ohne ApiAware-Flag: nur Admin-API, nie Store-API – Einkaufspreise erscheinen nie beim Kunden.
 * Grundlage für Auftragsverteilung (Lieferantenvorschlag), Rechnung/Buchhaltung (EK, Deckungsbeitrag).
 * Kalkulation/Material werden nur als ID gemerkt (kein Fremdschlüssel): Stammdaten dürfen sich ändern
 * oder gelöscht werden, die Bestellung bleibt unverändert.
 */
class MensuraOrderLineItemDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ld_mensura_order_line_item';

    public const SCHEMA_VERSION = 1;

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return MensuraOrderLineItemEntity::class;
    }

    public function getCollectionClass(): string
    {
        return MensuraOrderLineItemCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('order_line_item_id', 'orderLineItemId', OrderLineItemDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(OrderLineItemDefinition::class))->addFlags(new Required()),
            (new IntField('schema_version', 'schemaVersion'))->addFlags(new Required()),
            new IdField('calculation_id', 'calculationId'),
            new StringField('calculation_technical_name', 'calculationTechnicalName', 64),
            new IdField('material_id', 'materialId'),
            new IdField('supplier_id', 'supplierId'),
            new StringField('supplier_product_number', 'supplierProductNumber'),
            new JsonField('configuration', 'configuration'),
            new JsonField('breakdown', 'breakdown'),
            new FloatField('purchase_total', 'purchaseTotal'),
            new FloatField('sale_total', 'saleTotal'),
            new IdField('customer_group_id', 'customerGroupId'),
            new JsonField('artwork', 'artwork'),

            new OneToOneAssociationField('orderLineItem', 'order_line_item_id', 'id', OrderLineItemDefinition::class, false),
        ]);
    }

    public function getDefaults(): array
    {
        return ['schemaVersion' => self::SCHEMA_VERSION];
    }
}
