<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\UseCase\Aggregate\UseCaseTranslation;

use Lohndruckerei\Mensura\Core\Content\UseCase\UseCaseDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class UseCaseTranslationDefinition extends EntityTranslationDefinition
{
    public const ENTITY_NAME = 'ld_mensura_use_case_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return UseCaseTranslationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return UseCaseTranslationCollection::class;
    }

    protected function getParentDefinitionClass(): string
    {
        return UseCaseDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required()),
            new LongTextField('description', 'description'),
            new CustomFields(),
        ]);
    }
}
