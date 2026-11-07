<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Logistics;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<LogisticsRuleEntity>
 */
class LogisticsRuleCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return LogisticsRuleEntity::class;
    }
}
