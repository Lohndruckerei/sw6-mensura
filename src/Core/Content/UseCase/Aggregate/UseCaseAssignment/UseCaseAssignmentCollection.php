<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\UseCase\Aggregate\UseCaseAssignment;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<UseCaseAssignmentEntity>
 */
class UseCaseAssignmentCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return UseCaseAssignmentEntity::class;
    }
}
