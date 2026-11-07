<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Feature\Aggregate\FeatureTranslation;

use Lohndruckerei\Mensura\Core\Content\Feature\FeatureDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class FeatureTranslationDefinition extends EntityTranslationDefinition
{
    public const ENTITY_NAME = 'ld_mensura_feature_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return FeatureTranslationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return FeatureTranslationCollection::class;
    }

    protected function getParentDefinitionClass(): string
    {
        return FeatureDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required()),
            new LongTextField('help_text', 'helpText'),
            new CustomFields(),
        ]);
    }
}
