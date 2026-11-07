<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\PriceTable\Aggregate\PriceTableRow;

use Lohndruckerei\Mensura\Core\Content\PriceTable\PriceTableEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class PriceTableRowEntity extends Entity
{
    use EntityIdTrait;

    protected string $priceTableId;
    protected int $quantity;
    protected ?float $pricePurchase = null;
    protected ?float $priceSale = null;
    protected ?PriceTableEntity $priceTable = null;

    public function getPriceTableId(): string
    {
        return $this->priceTableId;
    }

    public function setPriceTableId(string $priceTableId): void
    {
        $this->priceTableId = $priceTableId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getPricePurchase(): ?float
    {
        return $this->pricePurchase;
    }

    public function setPricePurchase(?float $pricePurchase): void
    {
        $this->pricePurchase = $pricePurchase;
    }

    public function getPriceSale(): ?float
    {
        return $this->priceSale;
    }

    public function setPriceSale(?float $priceSale): void
    {
        $this->priceSale = $priceSale;
    }

    public function getPriceTable(): ?PriceTableEntity
    {
        return $this->priceTable;
    }

    public function setPriceTable(?PriceTableEntity $priceTable): void
    {
        $this->priceTable = $priceTable;
    }
}
