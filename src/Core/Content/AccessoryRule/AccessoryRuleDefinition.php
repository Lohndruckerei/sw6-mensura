<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\AccessoryRule;

use Lohndruckerei\Mensura\Core\Content\AccessoryRule\Aggregate\AccessoryRuleTranslation\AccessoryRuleTranslationDefinition;
use Lohndruckerei\Mensura\Core\Content\Feature\FeatureDefinition;
use Lohndruckerei\Mensura\Core\Content\Material\MaterialDefinition;
use Lohndruckerei\Mensura\Core\Content\Option\OptionValueDefinition;
use Lohndruckerei\Mensura\Core\Content\UseCase\UseCaseDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Zubehörregel (INTEGRATION.md Abschnitt 6): Auslöser → Shopware-Produkt mit Mengenregel.
 *
 * - `triggerType`: material, option_value, use_case, feature (dann `featureCondition`, z. B. {"op": ">=", "value": 4})
 * - `quantityRule`: fixed, per_piece, per_hole, per_perimeter (lfm Umfang), per_area (m²), jeweils × `quantityFactor`
 *   Beispiel: Bohrung „4 Ecken“, 10 Stk, per_hole × 1 → 40 Abstandshalter
 * - `level`: recommended oder required; `reason` (übersetzt) erklärt dem Kunden warum
 */
class AccessoryRuleDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ld_mensura_accessory_rule';

    public const TRIGGER_TYPES = ['material', 'option_value', 'use_case', 'feature'];
    public const QUANTITY_RULES = ['fixed', 'per_piece', 'per_hole', 'per_perimeter', 'per_area'];
    public const LEVELS = ['recommended', 'required'];

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AccessoryRuleEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AccessoryRuleCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('trigger_type', 'triggerType', 16))->addFlags(new Required()),
            new FkField('material_id', 'materialId', MaterialDefinition::class),
            new FkField('option_value_id', 'optionValueId', OptionValueDefinition::class),
            new FkField('use_case_id', 'useCaseId', UseCaseDefinition::class),
            new FkField('feature_id', 'featureId', FeatureDefinition::class),
            new JsonField('feature_condition', 'featureCondition'),
            (new FkField('product_id', 'productId', ProductDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(ProductDefinition::class))->addFlags(new Required()),
            (new StringField('quantity_rule', 'quantityRule', 16))->addFlags(new Required()),
            (new FloatField('quantity_factor', 'quantityFactor'))->addFlags(new Required()),
            (new StringField('level', 'level', 16))->addFlags(new Required()),
            new BoolField('active', 'active'),
            new IntField('position', 'position'),

            new TranslatedField('name'),
            new TranslatedField('reason'),
            new TranslatedField('customFields'),
            (new TranslationsAssociationField(AccessoryRuleTranslationDefinition::class, 'ld_mensura_accessory_rule_id'))->addFlags(new Required()),

            new ManyToOneAssociationField('material', 'material_id', MaterialDefinition::class, 'id', false),
            new ManyToOneAssociationField('optionValue', 'option_value_id', OptionValueDefinition::class, 'id', false),
            new ManyToOneAssociationField('useCase', 'use_case_id', UseCaseDefinition::class, 'id', false),
            new ManyToOneAssociationField('feature', 'feature_id', FeatureDefinition::class, 'id', false),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id', false),
        ]);
    }

    public function getDefaults(): array
    {
        return ['triggerType' => 'material', 'quantityRule' => 'fixed', 'quantityFactor' => 1.0, 'level' => 'recommended', 'active' => true, 'position' => 0];
    }
}
