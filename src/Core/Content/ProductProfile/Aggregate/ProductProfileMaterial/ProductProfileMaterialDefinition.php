<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\ProductProfile\Aggregate\ProductProfileMaterial;

use Lohndruckerei\Mensura\Core\Content\Calculation\CalculationDefinition;
use Lohndruckerei\Mensura\Core\Content\Material\MaterialDefinition;
use Lohndruckerei\Mensura\Core\Content\ProductProfile\ProductProfileDefinition;
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
 * Erlaubtes Material eines Produkts: Kalkulation + Material.
 * `materialId` leer = alle Materialien dieser Kalkulation (z. B. „alle Aluverbundplatten“).
 * Ein Profil mit genau einem Material = festes Material, der Schritt „Material“ entfällt.
 */
class ProductProfileMaterialDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ld_mensura_product_profile_material';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ProductProfileMaterialEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ProductProfileMaterialCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('product_profile_id', 'productProfileId', ProductProfileDefinition::class))->addFlags(new Required()),
            (new FkField('calculation_id', 'calculationId', CalculationDefinition::class))->addFlags(new Required()),
            new FkField('material_id', 'materialId', MaterialDefinition::class),
            new IntField('position', 'position'),
            new FloatField('surcharge_percent', 'surchargePercent'),

            new ManyToOneAssociationField('productProfile', 'product_profile_id', ProductProfileDefinition::class, 'id', false),
            new ManyToOneAssociationField('calculation', 'calculation_id', CalculationDefinition::class, 'id', false),
            new ManyToOneAssociationField('material', 'material_id', MaterialDefinition::class, 'id', false),
        ]);
    }

    public function getDefaults(): array
    {
        return ['position' => 0];
    }
}
