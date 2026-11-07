<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Option;

use Lohndruckerei\Mensura\Core\Content\Calculation\Aggregate\CalculationOptionValue\CalculationOptionValueDefinition;
use Lohndruckerei\Mensura\Core\Content\Option\Aggregate\OptionValueTranslation\OptionValueTranslationDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Wert einer Optionsgruppe (z. B. „4/4 beidseitig“). Preise und Verfügbarkeit hängen an der
 * Kalkulation (CalculationOptionValue), hier nur die Eigenschaften des Werts selbst:
 * Formatzugabe (z. B. Hohlsaum, Beschnitt), Werktage (Gruppe vom Typ `delivery`),
 * allgemeine Ausschlüsse und Lieferantencodes.
 */
class OptionValueDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ld_mensura_option_value';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return OptionValueEntity::class;
    }

    public function getCollectionClass(): string
    {
        return OptionValueCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('option_group_id', 'optionGroupId', OptionGroupDefinition::class))->addFlags(new Required()),
            (new StringField('technical_name', 'technicalName', 64))->addFlags(new Required()),
            new StringField('code', 'code', 64),
            new IntField('position', 'position'),

            new IntField('width_add', 'widthAdd'),
            new IntField('height_add', 'heightAdd'),
            new FloatField('width_factor', 'widthFactor'),
            new FloatField('height_factor', 'heightFactor'),
            new IntField('delivery_days', 'deliveryDays', 0),
            new JsonField('exclusions', 'exclusions'),

            new JsonField('supplier_codes', 'supplierCodes'),
            new FkField('media_id', 'mediaId', MediaDefinition::class),

            new TranslatedField('name'),
            new TranslatedField('description'),
            new TranslatedField('customFields'),
            (new TranslationsAssociationField(OptionValueTranslationDefinition::class, 'ld_mensura_option_value_id'))->addFlags(new Required()),

            new ManyToOneAssociationField('optionGroup', 'option_group_id', OptionGroupDefinition::class, 'id', false),
            new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, 'id', false),
            (new OneToManyAssociationField('calculationAssignments', CalculationOptionValueDefinition::class, 'option_value_id'))->addFlags(new CascadeDelete()),
        ]);
    }

    public function getDefaults(): array
    {
        return ['position' => 0, 'widthAdd' => 0, 'heightAdd' => 0, 'widthFactor' => 1.0, 'heightFactor' => 1.0];
    }
}
