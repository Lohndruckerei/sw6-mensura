<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\AccessoryRule;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<AccessoryRuleEntity>
 */
class AccessoryRuleCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AccessoryRuleEntity::class;
    }
}
