<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Checkout\OrderLineItem;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class MensuraOrderLineItemLogisticsEntity extends Entity
{
    use EntityIdTrait;

    protected string $orderLineItemId;
    protected ?string $orderLineItemVersionId = null;
    protected int $schemaVersion = MensuraOrderLineItemLogisticsDefinition::SCHEMA_VERSION;
    protected ?float $logisticsSale = null;
    protected ?float $productionTimeSale = null;
    protected ?float $sharePercent = null;
    protected ?float $goodsContributionMargin = null;
    protected ?array $lines = null;
    protected ?OrderLineItemEntity $orderLineItem = null;

    public function getOrderLineItemId(): string
    {
        return $this->orderLineItemId;
    }

    public function setOrderLineItemId(string $orderLineItemId): void
    {
        $this->orderLineItemId = $orderLineItemId;
    }

    public function getOrderLineItemVersionId(): ?string
    {
        return $this->orderLineItemVersionId;
    }

    public function setOrderLineItemVersionId(?string $orderLineItemVersionId): void
    {
        $this->orderLineItemVersionId = $orderLineItemVersionId;
    }

    public function getSchemaVersion(): int
    {
        return $this->schemaVersion;
    }

    public function setSchemaVersion(int $schemaVersion): void
    {
        $this->schemaVersion = $schemaVersion;
    }

    public function getLogisticsSale(): ?float
    {
        return $this->logisticsSale;
    }

    public function setLogisticsSale(?float $logisticsSale): void
    {
        $this->logisticsSale = $logisticsSale;
    }

    public function getProductionTimeSale(): ?float
    {
        return $this->productionTimeSale;
    }

    public function setProductionTimeSale(?float $productionTimeSale): void
    {
        $this->productionTimeSale = $productionTimeSale;
    }

    public function getSharePercent(): ?float
    {
        return $this->sharePercent;
    }

    public function setSharePercent(?float $sharePercent): void
    {
        $this->sharePercent = $sharePercent;
    }

    public function getGoodsContributionMargin(): ?float
    {
        return $this->goodsContributionMargin;
    }

    public function setGoodsContributionMargin(?float $goodsContributionMargin): void
    {
        $this->goodsContributionMargin = $goodsContributionMargin;
    }

    public function getLines(): ?array
    {
        return $this->lines;
    }

    public function setLines(?array $lines): void
    {
        $this->lines = $lines;
    }

    public function getOrderLineItem(): ?OrderLineItemEntity
    {
        return $this->orderLineItem;
    }

    public function setOrderLineItem(?OrderLineItemEntity $orderLineItem): void
    {
        $this->orderLineItem = $orderLineItem;
    }
}
