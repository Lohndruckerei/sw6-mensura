<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Checkout\OrderLineItem;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<MensuraOrderLineItemEntity>
 */
class MensuraOrderLineItemCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return MensuraOrderLineItemEntity::class;
    }
}
