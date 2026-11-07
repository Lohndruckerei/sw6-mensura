<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\PriceTable;

use Lohndruckerei\Mensura\Core\Content\Calculation\CalculationDefinition;
use Lohndruckerei\Mensura\Core\Content\Material\MaterialDefinition;
use Lohndruckerei\Mensura\Core\Content\PriceTable\Aggregate\PriceTableRow\PriceTableRowDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Preistabelle einer Kalkulation (Flyer, Broschüren …): Auflage → Preis, getrennt nach EK und VK.
 *
 * - Schlüssel: `materialId` (leer = alle Materialien) + `optionValueIds` (alle müssen gewählt sein).
 *   Passen mehrere Tabellen, gewinnt die spezifischste (meiste Schlüsselwerte, mit Material vor ohne).
 * - `priceType`: `piece` (Preis je Stück) oder `total` (Preis für die ganze Auflage)
 * - Zwischenmengen rechnen mit der Zeile darunter (300 Stk = 300 × Stückpreis der Zeile 250).
 * Pflege per CSV-Import/-Export in der Administration (Fahrplan Schritt 3).
 */
class PriceTableDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ld_mensura_price_table';

    public const PRICE_TYPES = ['piece', 'total'];

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return PriceTableEntity::class;
    }

    public function getCollectionClass(): string
    {
        return PriceTableCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('calculation_id', 'calculationId', CalculationDefinition::class))->addFlags(new Required()),
            new FkField('material_id', 'materialId', MaterialDefinition::class),
            new StringField('name', 'name'),
            new JsonField('option_value_ids', 'optionValueIds'),
            (new StringField('price_type', 'priceType', 16))->addFlags(new Required()),
            new BoolField('active', 'active'),
            new IntField('position', 'position'),

            new ManyToOneAssociationField('calculation', 'calculation_id', CalculationDefinition::class, 'id', false),
            new ManyToOneAssociationField('material', 'material_id', MaterialDefinition::class, 'id', false),
            (new OneToManyAssociationField('rows', PriceTableRowDefinition::class, 'price_table_id'))->addFlags(new CascadeDelete()),
        ]);
    }

    public function getDefaults(): array
    {
        return ['priceType' => 'piece', 'active' => true, 'position' => 0];
    }
}
