<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Extension;

use Lohndruckerei\Mensura\Core\Content\ProductCalculation\ProductCalculationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * `product.extensions.ldMensura` → Kalkulation des Produkts.
 * Store-API-Zugriff (ApiAware) folgt mit dem Konfigurator, dann gezielt ohne Einkaufspreise.
 */
class ProductExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToOneAssociationField('ldMensura', 'id', 'product_id', ProductCalculationDefinition::class, false))
                ->addFlags(new CascadeDelete()),
        );
    }

    public function getEntityName(): string
    {
        return ProductDefinition::ENTITY_NAME;
    }
}
