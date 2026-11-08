<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\ProductProfile\Aggregate\ProductProfileOption;

use Lohndruckerei\Mensura\Core\Content\Material\MaterialDefinition;
use Lohndruckerei\Mensura\Core\Content\Option\OptionGroupDefinition;
use Lohndruckerei\Mensura\Core\Content\ProductProfile\ProductProfileDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Optionsregel eines Produkts je Optionsgruppe, optional nur für ein Material.
 *
 * - `inherit`  alles aus der Kalkulation (Standard, Zeile nur nötig für Reihenfolge/Standardwert)
 * - `restrict` nur `valueIds`
 * - `fixed`    genau ein Wert (`defaultValueId`), Gruppe nur als Info sichtbar
 * - `hidden`   Gruppe für den Kunden unsichtbar; `defaultValueId` wird trotzdem berechnet und
 *              an den Lieferanten übermittelt (z. B. Lochbohrung = Nein)
 *
 * Zeile mit `materialId` gewinnt vor der Zeile ohne Material.
 */
class ProductProfileOptionDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ld_mensura_product_profile_option';

    public const MODES = ['inherit', 'restrict', 'fixed', 'hidden'];

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ProductProfileOptionEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ProductProfileOptionCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('product_profile_id', 'productProfileId', ProductProfileDefinition::class))->addFlags(new Required()),
            (new FkField('option_group_id', 'optionGroupId', OptionGroupDefinition::class))->addFlags(new Required()),
            new FkField('material_id', 'materialId', MaterialDefinition::class),
            (new StringField('mode', 'mode', 16))->addFlags(new Required()),
            new JsonField('value_ids', 'valueIds'),
            new IdField('default_value_id', 'defaultValueId'),
            // Vorauswahl: null = wie Kalkulation, true/false überschreibt
            new BoolField('preselect', 'preselect'),
            new IntField('position', 'position'),

            new ManyToOneAssociationField('productProfile', 'product_profile_id', ProductProfileDefinition::class, 'id', false),
            new ManyToOneAssociationField('optionGroup', 'option_group_id', OptionGroupDefinition::class, 'id', false),
            new ManyToOneAssociationField('material', 'material_id', MaterialDefinition::class, 'id', false),
        ]);
    }

    public function getDefaults(): array
    {
        return ['mode' => 'inherit'];
    }
}
