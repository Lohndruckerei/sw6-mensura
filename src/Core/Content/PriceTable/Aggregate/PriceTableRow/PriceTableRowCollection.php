<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\PriceTable\Aggregate\PriceTableRow;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<PriceTableRowEntity>
 */
class PriceTableRowCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PriceTableRowEntity::class;
    }
}
