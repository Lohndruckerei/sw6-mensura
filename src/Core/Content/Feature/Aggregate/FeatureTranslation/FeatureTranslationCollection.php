<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Feature\Aggregate\FeatureTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<FeatureTranslationEntity>
 */
class FeatureTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return FeatureTranslationEntity::class;
    }
}
