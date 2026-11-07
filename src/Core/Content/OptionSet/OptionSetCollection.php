<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\OptionSet;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<OptionSetEntity>
 */
class OptionSetCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OptionSetEntity::class;
    }
}
