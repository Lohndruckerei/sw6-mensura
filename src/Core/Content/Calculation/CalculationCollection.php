<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Calculation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<CalculationEntity>
 */
class CalculationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return CalculationEntity::class;
    }
}
