<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\OptionSet;

use Lohndruckerei\Mensura\Core\Content\Calculation\CalculationDefinition;
use Lohndruckerei\Mensura\Core\Content\Material\MaterialDefinition;
use Lohndruckerei\Mensura\Core\Content\Option\OptionValueDefinition;
use Lohndruckerei\Mensura\Core\Content\OptionSet\Aggregate\OptionSetGroup\OptionSetGroupDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Optionsset = geordnete Folge von Optionsgruppen (Konfigurationsschritt).
 *
 * Fluss im Konfigurator: Start-Set der Kalkulation → Folge-Set des gewählten Materials →
 * Folge-Sets der gewählten Optionswerte (rekursiv). Sichtbar sind nur Gruppen aktiver Sets.
 * Beispiel: Material „Aluverbund“ → Set „Plattenveredelung“ (Laminat, Lochbohrung, Schnitt);
 * Wert „Kaschierung auf Alu“ → Set „Alu-Weiterverarbeitung“.
 */
class OptionSetDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ld_mensura_option_set';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return OptionSetEntity::class;
    }

    public function getCollectionClass(): string
    {
        return OptionSetCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('technical_name', 'technicalName', 64))->addFlags(new Required()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            new LongTextField('description', 'description'),

            (new OneToManyAssociationField('groups', OptionSetGroupDefinition::class, 'option_set_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('calculations', CalculationDefinition::class, 'option_set_id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('materials', MaterialDefinition::class, 'option_set_id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('optionValues', OptionValueDefinition::class, 'option_set_id'))->addFlags(new SetNullOnDelete()),
        ]);
    }
}
