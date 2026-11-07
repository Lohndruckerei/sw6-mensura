<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\UseCase\Aggregate\UseCaseAssignment;

use Lohndruckerei\Mensura\Core\Content\Material\MaterialDefinition;
use Lohndruckerei\Mensura\Core\Content\ProductProfile\ProductProfileDefinition;
use Lohndruckerei\Mensura\Core\Content\UseCase\UseCaseDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Anwendungsfall × Produktprofil bzw. Material, mit Eignung 0–5 und optionaler Vorbelegung
 * (`preset` im Schema der Konfiguration, Teilmenge erlaubt, z. B. {"options": {"veredelung": "ag-2"}}).
 */
class UseCaseAssignmentDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ld_mensura_use_case_assignment';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return UseCaseAssignmentEntity::class;
    }

    public function getCollectionClass(): string
    {
        return UseCaseAssignmentCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('use_case_id', 'useCaseId', UseCaseDefinition::class))->addFlags(new Required()),
            new FkField('product_profile_id', 'productProfileId', ProductProfileDefinition::class),
            new FkField('material_id', 'materialId', MaterialDefinition::class),
            new IntField('suitability', 'suitability', 0, 5),
            new JsonField('preset', 'preset'),

            new ManyToOneAssociationField('useCase', 'use_case_id', UseCaseDefinition::class, 'id', false),
            new ManyToOneAssociationField('productProfile', 'product_profile_id', ProductProfileDefinition::class, 'id', false),
            new ManyToOneAssociationField('material', 'material_id', MaterialDefinition::class, 'id', false),
        ]);
    }

    public function getDefaults(): array
    {
        return ['suitability' => 3];
    }
}
