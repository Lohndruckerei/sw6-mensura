<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\ProductCalculation;

use Lohndruckerei\Mensura\Core\Content\Calculation\CalculationDefinition;
use Lohndruckerei\Mensura\Core\Content\Material\MaterialDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Verknüpft ein Shopware-Produkt mit einer Kalkulation (am Produkt: `extensions.ldMensura`).
 *
 * - allgemeines Produkt („Plattendirektdruck“): kein `fixedMaterialId`, `materialIds` leer = alle Materialien der Kalkulation
 * - Produkt je Material („Alu 3 mm“): `fixedMaterialId` gesetzt
 * - `materialIds` / `optionValueIds`: Einschränkung der Auswahl (leer = alles aus der Kalkulation)
 * - `defaults`: Vorbelegung im Konfigurator (Format, Menge, Optionen)
 */
class ProductCalculationDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ld_mensura_product_calculation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ProductCalculationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ProductCalculationCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('product_id', 'productId', ProductDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(ProductDefinition::class))->addFlags(new Required()),
            (new FkField('calculation_id', 'calculationId', CalculationDefinition::class))->addFlags(new Required()),
            new FkField('fixed_material_id', 'fixedMaterialId', MaterialDefinition::class),
            new JsonField('material_ids', 'materialIds'),
            new JsonField('option_value_ids', 'optionValueIds'),
            new JsonField('defaults', 'defaults'),

            new OneToOneAssociationField('product', 'product_id', 'id', ProductDefinition::class, false),
            new ManyToOneAssociationField('calculation', 'calculation_id', CalculationDefinition::class, 'id', false),
            new ManyToOneAssociationField('fixedMaterial', 'fixed_material_id', MaterialDefinition::class, 'id', false),
        ]);
    }
}
