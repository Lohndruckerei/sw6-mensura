<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\ProductProfile\Aggregate\ProductProfileOption;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ProductProfileOptionEntity>
 */
class ProductProfileOptionCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ProductProfileOptionEntity::class;
    }
}
