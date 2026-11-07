<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Calculation\Aggregate\CalculationOptionValue;

use Lohndruckerei\Mensura\Core\Content\Calculation\CalculationDefinition;
use Lohndruckerei\Mensura\Core\Content\Material\MaterialDefinition;
use Lohndruckerei\Mensura\Core\Content\Option\OptionValueDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Optionswert in einer Kalkulation: Verfügbarkeit, Preis und Abhängigkeiten.
 *
 * - `materialId` leer  → gilt für alle Materialien der Kalkulation
 * - `materialId` gesetzt → nur für dieses Material bzw. abweichender Preis dafür.
 *   Gibt es für einen Wert nur Zeilen mit Material, ist er nur bei diesen Materialien wählbar
 *   (z. B. Material A: Veredelung A + B, Material B: nur C + D).
 * - `priceComponents`: [{type: per_area|per_piece|per_motif|per_order|per_perimeter|percent|factor, purchase, sale}]
 * - `conditions`: {requires: [optionValueId…], excludes: [optionValueId…]}
 */
class CalculationOptionValueDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ld_mensura_calculation_option_value';

    public const PRICE_COMPONENT_TYPES = ['per_area', 'per_piece', 'per_motif', 'per_order', 'per_perimeter', 'percent', 'factor'];

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CalculationOptionValueEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CalculationOptionValueCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('calculation_id', 'calculationId', CalculationDefinition::class))->addFlags(new Required()),
            (new FkField('option_value_id', 'optionValueId', OptionValueDefinition::class))->addFlags(new Required()),
            new FkField('material_id', 'materialId', MaterialDefinition::class),
            new BoolField('active', 'active'),
            new BoolField('is_default', 'isDefault'),
            new IntField('position', 'position'),
            new JsonField('price_components', 'priceComponents'),
            new JsonField('tiers', 'tiers'),
            new JsonField('conditions', 'conditions'),

            new ManyToOneAssociationField('calculation', 'calculation_id', CalculationDefinition::class, 'id', false),
            new ManyToOneAssociationField('optionValue', 'option_value_id', OptionValueDefinition::class, 'id', false),
            new ManyToOneAssociationField('material', 'material_id', MaterialDefinition::class, 'id', false),
        ]);
    }

    public function getDefaults(): array
    {
        return ['active' => true, 'isDefault' => false, 'position' => 0];
    }
}
