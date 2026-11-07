<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\ProductProfile\Aggregate\ProductProfileMaterial;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ProductProfileMaterialEntity>
 */
class ProductProfileMaterialCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ProductProfileMaterialEntity::class;
    }
}
