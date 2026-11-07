<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\PriceTable\Aggregate\PriceTableRow;

use Lohndruckerei\Mensura\Core\Content\PriceTable\PriceTableDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Zeile einer Preistabelle: ab Auflage `quantity` gilt EK/VK (je Stück oder gesamt, siehe Tabelle).
 */
class PriceTableRowDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ld_mensura_price_table_row';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return PriceTableRowEntity::class;
    }

    public function getCollectionClass(): string
    {
        return PriceTableRowCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('price_table_id', 'priceTableId', PriceTableDefinition::class))->addFlags(new Required()),
            (new IntField('quantity', 'quantity', 1))->addFlags(new Required()),
            new FloatField('price_purchase', 'pricePurchase'),
            new FloatField('price_sale', 'priceSale'),

            new ManyToOneAssociationField('priceTable', 'price_table_id', PriceTableDefinition::class, 'id', false),
        ]);
    }
}
