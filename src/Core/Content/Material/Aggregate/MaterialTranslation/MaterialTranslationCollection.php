<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Material\Aggregate\MaterialTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<MaterialTranslationEntity>
 */
class MaterialTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return MaterialTranslationEntity::class;
    }
}
