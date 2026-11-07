<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Calculation;

use Lohndruckerei\Mensura\Core\Content\Calculation\Aggregate\CalculationCustomerGroup\CalculationCustomerGroupDefinition;
use Lohndruckerei\Mensura\Core\Content\Calculation\Aggregate\CalculationMaterial\CalculationMaterialDefinition;
use Lohndruckerei\Mensura\Core\Content\Calculation\Aggregate\CalculationOptionValue\CalculationOptionValueDefinition;
use Lohndruckerei\Mensura\Core\Content\Material\MaterialDefinition;
use Lohndruckerei\Mensura\Core\Content\OptionSet\OptionSetDefinition;
use Lohndruckerei\Mensura\Core\Content\PriceTable\PriceTableDefinition;
use Lohndruckerei\Mensura\Core\Content\ProductProfile\Aggregate\ProductProfileMaterial\ProductProfileMaterialDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Kalkulation = Rechenvorlage für eine Artikelart (z. B. „Aluverbund“, „PVC-Banner“).
 * Alle Preise netto in der Standardwährung, jeweils als EK (purchase) und VK (sale).
 * Basis-VK gilt für Wiederverkäufer; andere Kundengruppen über `customerGroups` (Aufschlag %).
 */
class CalculationDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ld_mensura_calculation';

    public const UNITS = ['piece', 'area', 'length', 'weight', 'volume'];
    public const FORMAT_MODES = ['free', 'fixed', 'both', 'none'];
    public const TIER_BASES = ['none', 'quantity', 'total_area', 'piece_area', 'total_price'];
    public const SALE_RULES = ['margin', 'markup', 'factor', 'free'];
    /** multiply = Motive × Stück je Motiv (Platten); split = Gesamtmenge auf Sorten verteilt (Etiketten) */
    public const MOTIF_MODES = ['multiply', 'split'];
    /** Staffel über alle Motive (total) oder je Motiv */
    public const TIER_SCOPES = ['total', 'per_motif'];
    /** Staffelsprung: none, hint (größere günstigere Menge anbieten), apply (günstigeren Preis anwenden) */
    public const BEST_PRICE_MODES = ['none', 'hint', 'apply'];
    public const AREA_SCOPES = ['total', 'piece'];

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CalculationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CalculationCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('technical_name', 'technicalName', 64))->addFlags(new Required()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            new LongTextField('description', 'description'),
            new BoolField('active', 'active'),
            (new StringField('unit', 'unit', 16))->addFlags(new Required()),

            // Format
            (new StringField('format_mode', 'formatMode', 16))->addFlags(new Required()),
            new JsonField('fixed_formats', 'fixedFormats'),
            new IntField('width_min', 'widthMin', 0),
            new IntField('width_max', 'widthMax', 0),
            new IntField('height_min', 'heightMin', 0),
            new IntField('height_max', 'heightMax', 0),
            new BoolField('allow_rotation', 'allowRotation'),
            new FloatField('min_area', 'minArea'),
            new FloatField('area_rounding', 'areaRounding'),
            // Mindestfläche/Flächenrundung bezogen auf die Position (total, L × B × Stück) oder je Stück (piece)
            (new StringField('area_scope', 'areaScope', 16))->addFlags(new Required()),
            new IntField('max_motifs', 'maxMotifs', 1),
            new IntField('max_quantity', 'maxQuantity', 1),

            // Konfigurationsfluss und Mengenlogik
            new FkField('option_set_id', 'optionSetId', OptionSetDefinition::class),
            (new StringField('motif_mode', 'motifMode', 16))->addFlags(new Required()),
            (new StringField('tier_scope', 'tierScope', 16))->addFlags(new Required()),
            (new StringField('best_price_mode', 'bestPriceMode', 16))->addFlags(new Required()),
            new ManyToOneAssociationField('optionSet', 'option_set_id', OptionSetDefinition::class, 'id', false),

            // Preise: Grundpreise als Preisanteile [{type, purchase, sale}], Mindestpreis je Position
            new JsonField('price_components', 'priceComponents'),
            new FloatField('minimum_price_purchase', 'minimumPricePurchase'),
            new FloatField('minimum_price_sale', 'minimumPriceSale'),
            (new StringField('tier_basis', 'tierBasis', 16))->addFlags(new Required()),
            new JsonField('tiers', 'tiers'),
            new FloatField('price_rounding', 'priceRounding'),
            // Einrichtepauschale je Position (Rüstkosten), Teil der Zwischensumme vor dem Mindestpreis
            new FloatField('setup_price_purchase', 'setupPricePurchase'),
            new FloatField('setup_price_sale', 'setupPriceSale'),
            // Zeilen der Preismatrix (geführte Darstellung), z. B. [1, 2, 5, 10, 25, 50, 100]
            new JsonField('quantity_steps', 'quantitySteps'),
            new LongTextField('formula', 'formula'),

            // Margenrechner (Vorschlag EK → VK)
            (new StringField('sale_rule', 'saleRule', 16))->addFlags(new Required()),
            new FloatField('sale_rule_value', 'saleRuleValue'),
            new FloatField('margin_min', 'marginMin'),
            new FloatField('margin_max', 'marginMax'),

            // LieferLotse (optional, ohne Fremdschlüssel)
            new IdField('supplier_id', 'supplierId'),
            new JsonField('supplier_codes', 'supplierCodes'),

            new CustomFields(),

            new ManyToManyAssociationField('materials', MaterialDefinition::class, CalculationMaterialDefinition::class, 'calculation_id', 'material_id'),
            (new OneToManyAssociationField('optionValues', CalculationOptionValueDefinition::class, 'calculation_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('customerGroups', CalculationCustomerGroupDefinition::class, 'calculation_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('priceTables', PriceTableDefinition::class, 'calculation_id'))->addFlags(new CascadeDelete()),
            // Kalkulation, die noch in Produktprofilen verwendet wird, kann nicht gelöscht werden
            (new OneToManyAssociationField('profileMaterials', ProductProfileMaterialDefinition::class, 'calculation_id'))->addFlags(new RestrictDelete()),
        ]);
    }

    public function getDefaults(): array
    {
        return [
            'active' => true,
            'unit' => 'area',
            'formatMode' => 'free',
            'allowRotation' => true,
            'tierBasis' => 'none',
            'saleRule' => 'margin',
            'motifMode' => 'multiply',
            'tierScope' => 'total',
            'bestPriceMode' => 'hint',
            'areaScope' => 'total',
        ];
    }
}
