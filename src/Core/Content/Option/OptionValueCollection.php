<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Option;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<OptionValueEntity>
 */
class OptionValueCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OptionValueEntity::class;
    }
}
