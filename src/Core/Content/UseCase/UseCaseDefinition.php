<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\UseCase;

use Lohndruckerei\Mensura\Core\Content\AccessoryRule\AccessoryRuleDefinition;
use Lohndruckerei\Mensura\Core\Content\UseCase\Aggregate\UseCaseAssignment\UseCaseAssignmentDefinition;
use Lohndruckerei\Mensura\Core\Content\UseCase\Aggregate\UseCaseTranslation\UseCaseTranslationDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Anwendungsfall („Wofür brauchst du es?“): Schild außen, Schaufenster, Rückwand Küche/Bad, Messe …
 * Zuordnung zu Produktprofilen und Materialien mit Eignung 0–5 über `assignments`.
 */
class UseCaseDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ld_mensura_use_case';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return UseCaseEntity::class;
    }

    public function getCollectionClass(): string
    {
        return UseCaseCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('technical_name', 'technicalName', 64))->addFlags(new Required()),
            new BoolField('active', 'active'),
            new IntField('position', 'position'),
            new FkField('media_id', 'mediaId', MediaDefinition::class),

            new TranslatedField('name'),
            new TranslatedField('description'),
            new TranslatedField('customFields'),
            (new TranslationsAssociationField(UseCaseTranslationDefinition::class, 'ld_mensura_use_case_id'))->addFlags(new Required()),

            new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, 'id', false),
            (new OneToManyAssociationField('assignments', UseCaseAssignmentDefinition::class, 'use_case_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('accessoryRules', AccessoryRuleDefinition::class, 'use_case_id'))->addFlags(new CascadeDelete()),
        ]);
    }

    public function getDefaults(): array
    {
        return ['active' => true, 'position' => 0];
    }
}
