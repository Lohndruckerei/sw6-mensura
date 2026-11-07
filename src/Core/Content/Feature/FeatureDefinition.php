<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Feature;

use Lohndruckerei\Mensura\Core\Content\AccessoryRule\AccessoryRuleDefinition;
use Lohndruckerei\Mensura\Core\Content\Feature\Aggregate\FeatureTranslation\FeatureTranslationDefinition;
use Lohndruckerei\Mensura\Core\Content\Feature\Aggregate\FeatureValue\FeatureValueDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Merkmal-Katalog (gemeinsame Sprache für Badges, Vergleichstabellen und den künftigen Produktberater,
 * INTEGRATION.md Abschnitt 6), z. B. `wasserfest` (bool), `kratzfest` (level 0–5), `haltbarkeit_jahre` (number + Einheit),
 * `einsatz` (enum innen/außen).
 *
 * - `type`: bool, level, number, enum, multi
 * - `featureGroup`: Haltbarkeit, Umgebung, Montage, Pflege, Nachhaltigkeit … (frei, technischer Schlüssel)
 * - `choices`: für enum/multi die erlaubten Schlüssel, z. B. ["innen", "aussen"]
 */
class FeatureDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ld_mensura_feature';

    public const TYPES = ['bool', 'level', 'number', 'enum', 'multi'];

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return FeatureEntity::class;
    }

    public function getCollectionClass(): string
    {
        return FeatureCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('technical_name', 'technicalName', 64))->addFlags(new Required()),
            (new StringField('type', 'type', 16))->addFlags(new Required()),
            new StringField('feature_group', 'featureGroup', 32),
            new StringField('unit', 'unit', 16),
            new JsonField('choices', 'choices'),
            new StringField('icon', 'icon', 64),
            new IntField('position', 'position'),

            new TranslatedField('name'),
            new TranslatedField('helpText'),
            new TranslatedField('customFields'),
            (new TranslationsAssociationField(FeatureTranslationDefinition::class, 'ld_mensura_feature_id'))->addFlags(new Required()),

            (new OneToManyAssociationField('values', FeatureValueDefinition::class, 'feature_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('accessoryRules', AccessoryRuleDefinition::class, 'feature_id'))->addFlags(new CascadeDelete()),
        ]);
    }

    public function getDefaults(): array
    {
        return ['type' => 'bool', 'position' => 0];
    }
}
