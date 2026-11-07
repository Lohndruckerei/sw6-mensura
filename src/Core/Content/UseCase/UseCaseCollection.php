<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\UseCase;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<UseCaseEntity>
 */
class UseCaseCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return UseCaseEntity::class;
    }
}
