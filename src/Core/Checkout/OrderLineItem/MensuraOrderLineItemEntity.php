<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Checkout\OrderLineItem;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class MensuraOrderLineItemEntity extends Entity
{
    use EntityIdTrait;

    protected string $orderLineItemId;
    protected ?string $orderLineItemVersionId = null;
    protected int $schemaVersion;
    protected ?string $calculationId = null;
    protected ?string $calculationTechnicalName = null;
    protected ?string $materialId = null;
    protected ?string $supplierId = null;
    protected ?string $supplierProductNumber = null;
    protected ?array $configuration = null;
    protected ?array $breakdown = null;
    protected ?float $purchaseTotal = null;
    protected ?float $saleTotal = null;
    protected ?string $customerGroupId = null;
    protected ?array $artwork = null;
    protected ?OrderLineItemEntity $orderLineItem = null;

    public function getOrderLineItemId(): string
    {
        return $this->orderLineItemId;
    }

    public function getSchemaVersion(): int
    {
        return $this->schemaVersion;
    }

    public function getSupplierId(): ?string
    {
        return $this->supplierId;
    }

    public function getSupplierProductNumber(): ?string
    {
        return $this->supplierProductNumber;
    }

    public function getConfiguration(): array
    {
        return $this->configuration ?? [];
    }

    public function getBreakdown(): array
    {
        return $this->breakdown ?? [];
    }

    public function getPurchaseTotal(): ?float
    {
        return $this->purchaseTotal;
    }

    public function getSaleTotal(): ?float
    {
        return $this->saleTotal;
    }

    /**
     * Deckungsbeitrag in € (VK − EK) oder null, wenn ein Wert fehlt.
     */
    public function getContributionMargin(): ?float
    {
        if ($this->purchaseTotal === null || $this->saleTotal === null) {
            return null;
        }

        return round($this->saleTotal - $this->purchaseTotal, 2);
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

    public function setSchemaVersion(int $schemaVersion): void
    {
        $this->schemaVersion = $schemaVersion;
    }

    public function getCalculationId(): ?string
    {
        return $this->calculationId;
    }

    public function setCalculationId(?string $calculationId): void
    {
        $this->calculationId = $calculationId;
    }

    public function getCalculationTechnicalName(): ?string
    {
        return $this->calculationTechnicalName;
    }

    public function setCalculationTechnicalName(?string $calculationTechnicalName): void
    {
        $this->calculationTechnicalName = $calculationTechnicalName;
    }

    public function getMaterialId(): ?string
    {
        return $this->materialId;
    }

    public function setMaterialId(?string $materialId): void
    {
        $this->materialId = $materialId;
    }

    public function setSupplierId(?string $supplierId): void
    {
        $this->supplierId = $supplierId;
    }

    public function setSupplierProductNumber(?string $supplierProductNumber): void
    {
        $this->supplierProductNumber = $supplierProductNumber;
    }

    public function setConfiguration(?array $configuration): void
    {
        $this->configuration = $configuration;
    }

    public function setBreakdown(?array $breakdown): void
    {
        $this->breakdown = $breakdown;
    }

    public function setPurchaseTotal(?float $purchaseTotal): void
    {
        $this->purchaseTotal = $purchaseTotal;
    }

    public function setSaleTotal(?float $saleTotal): void
    {
        $this->saleTotal = $saleTotal;
    }

    public function getCustomerGroupId(): ?string
    {
        return $this->customerGroupId;
    }

    public function setCustomerGroupId(?string $customerGroupId): void
    {
        $this->customerGroupId = $customerGroupId;
    }

    public function getArtwork(): ?array
    {
        return $this->artwork;
    }

    public function setArtwork(?array $artwork): void
    {
        $this->artwork = $artwork;
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
