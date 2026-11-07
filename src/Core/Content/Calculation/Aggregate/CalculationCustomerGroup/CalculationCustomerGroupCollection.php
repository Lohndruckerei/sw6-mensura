<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Calculation\Aggregate\CalculationCustomerGroup;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<CalculationCustomerGroupEntity>
 */
class CalculationCustomerGroupCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return CalculationCustomerGroupEntity::class;
    }
}
