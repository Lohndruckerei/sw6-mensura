<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Extension;

use Lohndruckerei\Mensura\Core\Content\AccessoryRule\AccessoryRuleDefinition;
use Lohndruckerei\Mensura\Core\Content\ProductProfile\ProductProfileDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * `product.extensions.ldMensura` → Produktprofil (Materialien, Optionsregeln, Grenzen).
 * Store-API-Zugriff (ApiAware) folgt mit dem Konfigurator, dann gezielt ohne Einkaufspreise.
 */
class ProductExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToOneAssociationField('ldMensura', 'id', 'product_id', ProductProfileDefinition::class, false))
                ->addFlags(new CascadeDelete()),
        );
        // Zubehörregeln, die auf dieses Produkt als Zubehör verweisen (Produkt löschen → Regel weg)
        $collection->add(
            (new OneToManyAssociationField('ldMensuraAccessoryRules', AccessoryRuleDefinition::class, 'product_id'))
                ->addFlags(new CascadeDelete()),
        );
    }

    public function getEntityName(): string
    {
        return ProductDefinition::ENTITY_NAME;
    }
}
