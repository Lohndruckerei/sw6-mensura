<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\OptionSet\Aggregate\OptionSetGroup;

use Lohndruckerei\Mensura\Core\Content\Option\OptionGroupDefinition;
use Lohndruckerei\Mensura\Core\Content\OptionSet\OptionSetDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Gruppe in einem Set: Reihenfolge und optional abweichende Pflicht-Einstellung.
 * Dieselbe Gruppe darf in mehreren Sets stehen – die Auswahl des Kunden bleibt beim Wechsel erhalten.
 */
class OptionSetGroupDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ld_mensura_option_set_group';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return OptionSetGroupEntity::class;
    }

    public function getCollectionClass(): string
    {
        return OptionSetGroupCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('option_set_id', 'optionSetId', OptionSetDefinition::class))->addFlags(new Required()),
            (new FkField('option_group_id', 'optionGroupId', OptionGroupDefinition::class))->addFlags(new Required()),
            new IntField('position', 'position'),
            new BoolField('required', 'required'),

            new ManyToOneAssociationField('optionSet', 'option_set_id', OptionSetDefinition::class, 'id', false),
            new ManyToOneAssociationField('optionGroup', 'option_group_id', OptionGroupDefinition::class, 'id', false),
        ]);
    }

    public function getDefaults(): array
    {
        return ['position' => 0];
    }
}
