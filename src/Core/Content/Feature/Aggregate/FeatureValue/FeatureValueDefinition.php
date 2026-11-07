<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Feature\Aggregate\FeatureValue;

use Lohndruckerei\Mensura\Core\Content\Feature\FeatureDefinition;
use Lohndruckerei\Mensura\Core\Content\Material\MaterialDefinition;
use Lohndruckerei\Mensura\Core\Content\Option\OptionValueDefinition;
use Lohndruckerei\Mensura\Core\Content\ProductProfile\ProductProfileDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Merkmalswert: Merkmal × (Material | Optionswert | Produktprofil), genau eines davon gesetzt.
 * `value` als JSON-Objekt {"value": …} passend zum Typ des Merkmals: true, 4, 5.5, "aussen", ["innen","aussen"].
 * Beispiel: Laminat Anti-Graffiti → kratzfest 4; Aluverbund 3 mm → einsatz ["innen","aussen"].
 */
class FeatureValueDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ld_mensura_feature_value';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return FeatureValueEntity::class;
    }

    public function getCollectionClass(): string
    {
        return FeatureValueCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('feature_id', 'featureId', FeatureDefinition::class))->addFlags(new Required()),
            new FkField('material_id', 'materialId', MaterialDefinition::class),
            new FkField('option_value_id', 'optionValueId', OptionValueDefinition::class),
            new FkField('product_profile_id', 'productProfileId', ProductProfileDefinition::class),
            new JsonField('value', 'value'),

            new ManyToOneAssociationField('feature', 'feature_id', FeatureDefinition::class, 'id', false),
            new ManyToOneAssociationField('material', 'material_id', MaterialDefinition::class, 'id', false),
            new ManyToOneAssociationField('optionValue', 'option_value_id', OptionValueDefinition::class, 'id', false),
            new ManyToOneAssociationField('productProfile', 'product_profile_id', ProductProfileDefinition::class, 'id', false),
        ]);
    }
}
