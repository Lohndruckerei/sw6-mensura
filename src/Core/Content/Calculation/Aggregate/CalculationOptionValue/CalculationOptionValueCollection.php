<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Calculation\Aggregate\CalculationOptionValue;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<CalculationOptionValueEntity>
 */
class CalculationOptionValueCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return CalculationOptionValueEntity::class;
    }
}
