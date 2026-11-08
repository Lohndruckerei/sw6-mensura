<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Checkout\OrderLineItem;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<MensuraOrderLineItemLogisticsEntity>
 */
class MensuraOrderLineItemLogisticsCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return MensuraOrderLineItemLogisticsEntity::class;
    }
}
