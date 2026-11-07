<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Option;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<OptionGroupEntity>
 */
class OptionGroupCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OptionGroupEntity::class;
    }
}
