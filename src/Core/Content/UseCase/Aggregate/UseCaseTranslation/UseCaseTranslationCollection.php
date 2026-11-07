<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\UseCase\Aggregate\UseCaseTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<UseCaseTranslationEntity>
 */
class UseCaseTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return UseCaseTranslationEntity::class;
    }
}
