<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\AccessoryRule\Aggregate\AccessoryRuleTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<AccessoryRuleTranslationEntity>
 */
class AccessoryRuleTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AccessoryRuleTranslationEntity::class;
    }
}
