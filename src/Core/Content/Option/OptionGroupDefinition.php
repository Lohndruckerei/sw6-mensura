<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Option;

use Lohndruckerei\Mensura\Core\Content\Option\Aggregate\OptionGroupTranslation\OptionGroupTranslationDefinition;
use Lohndruckerei\Mensura\Core\Content\OptionSet\Aggregate\OptionSetGroup\OptionSetGroupDefinition;
use Lohndruckerei\Mensura\Core\Content\ProductProfile\Aggregate\ProductProfileOption\ProductProfileOptionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Optionsgruppe der Bibliothek (z. B. Farbigkeit, Schneiden, Veredelung, Lieferzeit).
 *
 * - `type`: `standard` oder `delivery` (Werte tragen Werktage; Mensura errechnet den Liefertermin)
 * - `scope`: `position` (je Position wählbar) oder `supplier` (eine Wahl für alle Positionen eines
 *   Lieferanten – wird im Warenkorb erzwungen, sobald so eingestellt)
 * - `displayType`: select, tiles, radio, checkbox, number
 */
class OptionGroupDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ld_mensura_option_group';

    /** format = Festformate (Werte mit Breite/Höhe, z. B. DIN A0–A2) */
    public const TYPES = ['standard', 'delivery', 'format'];
    public const SCOPES = ['position', 'supplier'];
    public const DISPLAY_TYPES = ['select', 'tiles', 'radio', 'checkbox', 'number', 'multi'];

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return OptionGroupEntity::class;
    }

    public function getCollectionClass(): string
    {
        return OptionGroupCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('technical_name', 'technicalName', 64))->addFlags(new Required()),
            (new StringField('type', 'type', 16))->addFlags(new Required()),
            (new StringField('scope', 'scope', 16))->addFlags(new Required()),
            (new StringField('display_type', 'displayType', 16))->addFlags(new Required()),
            new BoolField('required', 'required'),
            // Aufpreise der Werte im Shop anzeigen (false = Fokus auf den Gesamtpreis)
            new BoolField('show_price_hint', 'showPriceHint'),
            new IntField('position', 'position'),

            new TranslatedField('name'),
            new TranslatedField('description'),
            new TranslatedField('helpText'),
            new TranslatedField('customFields'),
            (new TranslationsAssociationField(OptionGroupTranslationDefinition::class, 'ld_mensura_option_group_id'))->addFlags(new Required()),

            (new OneToManyAssociationField('values', OptionValueDefinition::class, 'option_group_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('setGroups', OptionSetGroupDefinition::class, 'option_group_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('profileOptions', ProductProfileOptionDefinition::class, 'option_group_id'))->addFlags(new CascadeDelete()),
        ]);
    }

    public function getDefaults(): array
    {
        return ['type' => 'standard', 'scope' => 'position', 'displayType' => 'select', 'required' => true, 'showPriceHint' => true, 'position' => 0];
    }
}
