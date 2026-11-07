<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Material;

use Lohndruckerei\Mensura\Core\Content\Calculation\Aggregate\CalculationMaterial\CalculationMaterialDefinition;
use Lohndruckerei\Mensura\Core\Content\Calculation\Aggregate\CalculationOptionValue\CalculationOptionValueDefinition;
use Lohndruckerei\Mensura\Core\Content\OptionSet\OptionSetDefinition;
use Lohndruckerei\Mensura\Core\Content\ProductProfile\Aggregate\ProductProfileMaterial\ProductProfileMaterialDefinition;
use Lohndruckerei\Mensura\Core\Content\ProductProfile\Aggregate\ProductProfileOption\ProductProfileOptionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Lohndruckerei\Mensura\Core\Content\Calculation\CalculationDefinition;
use Lohndruckerei\Mensura\Core\Content\Material\Aggregate\MaterialTranslation\MaterialTranslationDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Material (Platte, Folie, Banner …) mit Preis je Einheit der Kalkulation (meist €/m²), EK und VK netto.
 * Mindestpreis, Staffel und Max.-Format überschreiben die Kalkulation, wenn gesetzt.
 */
class MaterialDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ld_mensura_material';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return MaterialEntity::class;
    }

    public function getCollectionClass(): string
    {
        return MaterialCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('technical_name', 'technicalName', 64))->addFlags(new Required()),
            new StringField('code', 'code', 64),
            new BoolField('active', 'active'),
            new IntField('position', 'position'),

            new FloatField('price_purchase', 'pricePurchase'),
            new FloatField('price_sale', 'priceSale'),
            new FloatField('minimum_price_purchase', 'minimumPricePurchase'),
            new FloatField('minimum_price_sale', 'minimumPriceSale'),
            new StringField('tier_basis', 'tierBasis', 16),
            new JsonField('tiers', 'tiers'),
            // Preisanteile des Materials, z. B. 0,20 €/Stk bei Mesh-Banner
            new JsonField('price_components', 'priceComponents'),
            // Folge-Set: welche Optionsgruppen nach Wahl dieses Materials erscheinen
            new FkField('option_set_id', 'optionSetId', OptionSetDefinition::class),

            new IntField('sheet_width', 'sheetWidth', 0),
            new IntField('sheet_height', 'sheetHeight', 0),
            new IntField('width_max', 'widthMax', 0),
            new IntField('height_max', 'heightMax', 0),
            new FloatField('weight_per_sqm', 'weightPerSqm'),
            new JsonField('properties', 'properties'),

            new IdField('supplier_id', 'supplierId'),
            new JsonField('supplier_codes', 'supplierCodes'),

            new FkField('media_id', 'mediaId', MediaDefinition::class),

            new TranslatedField('name'),
            new TranslatedField('description'),
            new TranslatedField('customFields'),
            (new TranslationsAssociationField(MaterialTranslationDefinition::class, 'ld_mensura_material_id'))->addFlags(new Required()),

            new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, 'id', false),
            new ManyToManyAssociationField('calculations', CalculationDefinition::class, CalculationMaterialDefinition::class, 'material_id', 'calculation_id'),
            (new OneToManyAssociationField('calculationOptionValues', CalculationOptionValueDefinition::class, 'material_id'))->addFlags(new CascadeDelete()),
            // Material, das in Produktprofilen verwendet wird, kann nicht gelöscht werden
            (new OneToManyAssociationField('profileMaterials', ProductProfileMaterialDefinition::class, 'material_id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('profileOptions', ProductProfileOptionDefinition::class, 'material_id'))->addFlags(new CascadeDelete()),
            new ManyToOneAssociationField('optionSet', 'option_set_id', OptionSetDefinition::class, 'id', false),
        ]);
    }

    public function getDefaults(): array
    {
        return ['active' => true, 'position' => 0];
    }
}
