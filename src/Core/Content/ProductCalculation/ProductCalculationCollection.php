<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\ProductCalculation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ProductCalculationEntity>
 */
class ProductCalculationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ProductCalculationEntity::class;
    }
}
