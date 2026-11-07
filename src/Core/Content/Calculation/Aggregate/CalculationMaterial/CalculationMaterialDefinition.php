<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Calculation\Aggregate\CalculationMaterial;

use Lohndruckerei\Mensura\Core\Content\Calculation\CalculationDefinition;
use Lohndruckerei\Mensura\Core\Content\Material\MaterialDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;

/**
 * Welche Materialien eine Kalkulation anbietet (n:m).
 */
class CalculationMaterialDefinition extends MappingEntityDefinition
{
    public const ENTITY_NAME = 'ld_mensura_calculation_material';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('calculation_id', 'calculationId', CalculationDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('material_id', 'materialId', MaterialDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('calculation', 'calculation_id', CalculationDefinition::class, 'id', false),
            new ManyToOneAssociationField('material', 'material_id', MaterialDefinition::class, 'id', false),
            new CreatedAtField(),
        ]);
    }
}
