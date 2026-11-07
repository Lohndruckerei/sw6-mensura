<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Material\Aggregate\MaterialTranslation;

use Lohndruckerei\Mensura\Core\Content\Material\MaterialDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class MaterialTranslationDefinition extends EntityTranslationDefinition
{
    public const ENTITY_NAME = 'ld_mensura_material_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return MaterialTranslationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return MaterialTranslationCollection::class;
    }

    protected function getParentDefinitionClass(): string
    {
        return MaterialDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required()),
            new LongTextField('description', 'description'),
            new JsonField('badges', 'badges'),
            new CustomFields(),
        ]);
    }
}
