<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\AccessoryRule\Aggregate\AccessoryRuleTranslation;

use Lohndruckerei\Mensura\Core\Content\AccessoryRule\AccessoryRuleDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class AccessoryRuleTranslationDefinition extends EntityTranslationDefinition
{
    public const ENTITY_NAME = 'ld_mensura_accessory_rule_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AccessoryRuleTranslationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AccessoryRuleTranslationCollection::class;
    }

    protected function getParentDefinitionClass(): string
    {
        return AccessoryRuleDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new StringField('name', 'name'),
            new LongTextField('reason', 'reason'),
            new CustomFields(),
        ]);
    }
}
