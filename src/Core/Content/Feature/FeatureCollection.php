<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Feature;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<FeatureEntity>
 */
class FeatureCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return FeatureEntity::class;
    }
}
