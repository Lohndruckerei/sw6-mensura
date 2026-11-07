<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Option\Aggregate\OptionGroupTranslation;

use Lohndruckerei\Mensura\Core\Content\Option\OptionGroupDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class OptionGroupTranslationDefinition extends EntityTranslationDefinition
{
    public const ENTITY_NAME = 'ld_mensura_option_group_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return OptionGroupTranslationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return OptionGroupTranslationCollection::class;
    }

    protected function getParentDefinitionClass(): string
    {
        return OptionGroupDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required()),
            new LongTextField('description', 'description'),
            new LongTextField('help_text', 'helpText'),
            new CustomFields(),
        ]);
    }
}
