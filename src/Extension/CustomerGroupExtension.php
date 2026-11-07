<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Extension;

use Lohndruckerei\Mensura\Core\Content\Calculation\Aggregate\CalculationCustomerGroup\CalculationCustomerGroupDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Rückverknüpfung: `customerGroup.extensions.ldMensuraRules` (VK-Aufschläge dieser Kundengruppe).
 */
class CustomerGroupExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToManyAssociationField('ldMensuraRules', CalculationCustomerGroupDefinition::class, 'customer_group_id'))
                ->addFlags(new CascadeDelete()),
        );
    }

    public function getEntityName(): string
    {
        return CustomerGroupDefinition::ENTITY_NAME;
    }
}
