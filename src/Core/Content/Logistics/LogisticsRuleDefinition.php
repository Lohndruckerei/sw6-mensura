<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Logistics;

use Lohndruckerei\Mensura\Core\Content\Calculation\CalculationDefinition;
use Lohndruckerei\Mensura\Core\Content\Material\MaterialDefinition;
use Lohndruckerei\Mensura\Core\Content\Option\OptionValueDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Logistikanteil (KONZEPT 3d): unsichtbarer Aufschlag im VK. Nicht ApiAware (nie Store-API) und ohne
 * Rück-Assoziation an Kalkulation/Material/Optionswert, damit er nie versehentlich mitgeladen wird.
 * Lesen/Schreiben über die Admin-API nur mit den Zusatzrechten `ld_mensura.logistics` / `ld_mensura.logistics_edit`.
 *
 * Ziel: Kalkulation allein, Material, oder Optionswert (optional mit `calculationId` als Beschränkung).
 * Formel je Regel: Wert × Basis(form) × fRampe(längste Seite) × fMenge(Schwelle), mindestens `minimumAmount`.
 */
class LogisticsRuleDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ld_mensura_logistics_rule';

    public const COST_TYPES = ['base', 'quantity', 'bulky', 'express', 'multi_source', 'other'];
    public const FORMS = ['percent', 'per_sqm', 'per_piece', 'per_position'];
    public const THRESHOLD_BASES = ['total_area', 'quantity'];
    public const MINIMUM_SCOPES = ['position', 'order'];
    public const SOURCES = ['manual', 'vectura'];

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return LogisticsRuleEntity::class;
    }

    public function getCollectionClass(): string
    {
        return LogisticsRuleCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new FkField('calculation_id', 'calculationId', CalculationDefinition::class),
            new FkField('material_id', 'materialId', MaterialDefinition::class),
            new FkField('option_value_id', 'optionValueId', OptionValueDefinition::class),
            (new StringField('cost_type', 'costType', 32))->addFlags(new Required()),
            (new StringField('form', 'form', 16))->addFlags(new Required()),
            (new FloatField('value', 'value'))->addFlags(new Required()),
            new IntField('ramp_from', 'rampFrom'),
            new IntField('ramp_to', 'rampTo'),
            new StringField('threshold_basis', 'thresholdBasis', 16),
            new FloatField('threshold_from', 'thresholdFrom'),
            new FloatField('threshold_to', 'thresholdTo'),
            new FloatField('minimum_amount', 'minimumAmount'),
            new StringField('minimum_scope', 'minimumScope', 16),
            new DateField('valid_from', 'validFrom'),
            new BoolField('active', 'active'),
            new StringField('source', 'source', 16),
            new JsonField('source_reference', 'sourceReference'),
            new LongTextField('note', 'note'),

            new ManyToOneAssociationField('calculation', 'calculation_id', CalculationDefinition::class, 'id', false),
            new ManyToOneAssociationField('material', 'material_id', MaterialDefinition::class, 'id', false),
            new ManyToOneAssociationField('optionValue', 'option_value_id', OptionValueDefinition::class, 'id', false),
        ]);
    }

    public function getDefaults(): array
    {
        return ['form' => 'per_sqm', 'costType' => 'base', 'value' => 0.0, 'active' => true, 'source' => 'manual'];
    }
}
