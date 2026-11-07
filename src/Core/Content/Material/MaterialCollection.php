<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Material;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<MaterialEntity>
 */
class MaterialCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return MaterialEntity::class;
    }
}
