<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Extension;

use Lohndruckerei\Mensura\Core\Content\AccessoryRule\Aggregate\AccessoryRuleTranslation\AccessoryRuleTranslationDefinition;
use Lohndruckerei\Mensura\Core\Content\Feature\Aggregate\FeatureTranslation\FeatureTranslationDefinition;
use Lohndruckerei\Mensura\Core\Content\Material\Aggregate\MaterialTranslation\MaterialTranslationDefinition;
use Lohndruckerei\Mensura\Core\Content\Option\Aggregate\OptionGroupTranslation\OptionGroupTranslationDefinition;
use Lohndruckerei\Mensura\Core\Content\Option\Aggregate\OptionValueTranslation\OptionValueTranslationDefinition;
use Lohndruckerei\Mensura\Core\Content\UseCase\Aggregate\UseCaseTranslation\UseCaseTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Language\LanguageDefinition;

/**
 * Rückverknüpfungen der Mensura-Übersetzungen (Sprache löschen → Übersetzungen weg).
 */
class LanguageExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add((new OneToManyAssociationField('ldMensuraMaterialTranslations', MaterialTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()));
        $collection->add((new OneToManyAssociationField('ldMensuraOptionGroupTranslations', OptionGroupTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()));
        $collection->add((new OneToManyAssociationField('ldMensuraOptionValueTranslations', OptionValueTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()));
        $collection->add((new OneToManyAssociationField('ldMensuraFeatureTranslations', FeatureTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()));
        $collection->add((new OneToManyAssociationField('ldMensuraUseCaseTranslations', UseCaseTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()));
        $collection->add((new OneToManyAssociationField('ldMensuraAccessoryRuleTranslations', AccessoryRuleTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()));
    }

    public function getEntityName(): string
    {
        return LanguageDefinition::ENTITY_NAME;
    }
}
