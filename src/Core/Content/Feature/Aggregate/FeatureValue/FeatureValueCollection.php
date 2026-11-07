<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Feature\Aggregate\FeatureValue;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<FeatureValueEntity>
 */
class FeatureValueCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return FeatureValueEntity::class;
    }
}
