<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\ProductProfile;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ProductProfileEntity>
 */
class ProductProfileCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ProductProfileEntity::class;
    }
}
