<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\ProductProfile;

use Lohndruckerei\Mensura\Core\Content\Feature\Aggregate\FeatureValue\FeatureValueDefinition;
use Lohndruckerei\Mensura\Core\Content\ProductProfile\Aggregate\ProductProfileMaterial\ProductProfileMaterialDefinition;
use Lohndruckerei\Mensura\Core\Content\UseCase\Aggregate\UseCaseAssignment\UseCaseAssignmentDefinition;
use Lohndruckerei\Mensura\Core\Content\ProductProfile\Aggregate\ProductProfileOption\ProductProfileOptionDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Produktprofil (1:1 zum Shopware-Produkt, `product.extensions.ldMensura`).
 *
 * Kalkulationen rechnen, Produkte wählen aus: Das Profil wählt Materialien – auch aus mehreren
 * Kalkulationen – und schränkt Optionen ein (`options`), erweitert aber nie. Grenzen und Mengen
 * dürfen nur enger sein als die der Kalkulation; welche Kalkulation rechnet, bestimmt das gewählte Material.
 *
 * - `type`: `general` (z. B. Plattendirektdruck) oder `special` (z. B. Duschrückwände) – nur zur Übersicht
 * - `priceComponents` / `surchargePercent`: Aufschlag des Produkts (VK) auf das Ergebnis der Kalkulation
 * - `defaults`: Vorbelegung (Material, Format, Menge, Optionen); `steps`: Schritte/Texte im Konfigurator
 */
class ProductProfileDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ld_mensura_product_profile';

    public const TYPES = ['general', 'special'];
    public const LAYOUTS = ['compact', 'guided'];

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ProductProfileEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ProductProfileCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('product_id', 'productId', ProductDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(ProductDefinition::class))->addFlags(new Required()),
            (new StringField('type', 'type', 16))->addFlags(new Required()),
            new BoolField('active', 'active'),
            new IntField('width_min', 'widthMin', 0),
            new IntField('width_max', 'widthMax', 0),
            new IntField('height_min', 'heightMin', 0),
            new IntField('height_max', 'heightMax', 0),
            new IntField('min_quantity', 'minQuantity', 1),
            new IntField('max_quantity', 'maxQuantity', 1),
            new FloatField('minimum_price_sale', 'minimumPriceSale'),
            new FloatField('surcharge_percent', 'surchargePercent'),
            new JsonField('price_components', 'priceComponents'),
            new JsonField('defaults', 'defaults'),
            new JsonField('steps', 'steps'),
            new IdField('template_profile_id', 'templateProfileId'),
            // Darstellung im Shop: compact, guided oder leer (= Plugin-Einstellung storefrontLayout)
            new StringField('layout', 'layout', 16),
            // Zeilen der Preismatrix; leer = aus der Kalkulation
            new JsonField('quantity_steps', 'quantitySteps'),

            new OneToOneAssociationField('product', 'product_id', 'id', ProductDefinition::class, false),
            (new OneToManyAssociationField('materials', ProductProfileMaterialDefinition::class, 'product_profile_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('options', ProductProfileOptionDefinition::class, 'product_profile_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('featureValues', FeatureValueDefinition::class, 'product_profile_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('useCaseAssignments', UseCaseAssignmentDefinition::class, 'product_profile_id'))->addFlags(new CascadeDelete()),
        ]);
    }

    public function getDefaults(): array
    {
        return ['type' => 'general', 'active' => true];
    }
}
