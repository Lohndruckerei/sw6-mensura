<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\PriceTable;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<PriceTableEntity>
 */
class PriceTableCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PriceTableEntity::class;
    }
}
