<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\OptionSet\Aggregate\OptionSetGroup;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<OptionSetGroupEntity>
 */
class OptionSetGroupCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OptionSetGroupEntity::class;
    }
}
