<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Calculation\Aggregate\CalculationCustomerGroup;

use Lohndruckerei\Mensura\Core\Content\Calculation\CalculationDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * VK-Aufschlag je Kundengruppe. Basis-VK = Wiederverkäufer (kein Eintrag nötig).
 * `calculationId` leer = Vorgabe für alle Kalkulationen; ein Eintrag je Kalkulation überschreibt sie.
 * Beispiel: Gewerbe +12,5 %. Uneingeloggte Besucher erhalten die Standard-Kundengruppe des Verkaufskanals.
 */
class CalculationCustomerGroupDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ld_mensura_customer_group_rule';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CalculationCustomerGroupEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CalculationCustomerGroupCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new FkField('calculation_id', 'calculationId', CalculationDefinition::class),
            (new FkField('customer_group_id', 'customerGroupId', CustomerGroupDefinition::class))->addFlags(new Required()),
            (new FloatField('surcharge_percent', 'surchargePercent'))->addFlags(new Required()),
            new FloatField('price_rounding', 'priceRounding'),

            new ManyToOneAssociationField('calculation', 'calculation_id', CalculationDefinition::class, 'id', false),
            new ManyToOneAssociationField('customerGroup', 'customer_group_id', CustomerGroupDefinition::class, 'id', false),
        ]);
    }
}
