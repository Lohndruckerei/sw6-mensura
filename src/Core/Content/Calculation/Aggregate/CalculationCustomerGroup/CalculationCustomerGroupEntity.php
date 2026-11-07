<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Calculation\Aggregate\CalculationCustomerGroup;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class CalculationCustomerGroupEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $calculationId = null;
    protected string $customerGroupId;
    protected float $surchargePercent;
    protected ?float $priceRounding = null;
    protected ?CustomerGroupEntity $customerGroup = null;
    protected ?\Lohndruckerei\Mensura\Core\Content\Calculation\CalculationEntity $calculation = null;

    public function getCalculationId(): ?string
    {
        return $this->calculationId;
    }

    public function getCustomerGroupId(): string
    {
        return $this->customerGroupId;
    }

    public function getSurchargePercent(): float
    {
        return $this->surchargePercent;
    }

    public function getPriceRounding(): ?float
    {
        return $this->priceRounding;
    }

    public function setCalculationId(?string $calculationId): void
    {
        $this->calculationId = $calculationId;
    }

    public function setCustomerGroupId(string $customerGroupId): void
    {
        $this->customerGroupId = $customerGroupId;
    }

    public function setSurchargePercent(float $surchargePercent): void
    {
        $this->surchargePercent = $surchargePercent;
    }

    public function setPriceRounding(?float $priceRounding): void
    {
        $this->priceRounding = $priceRounding;
    }

    public function getCustomerGroup(): ?CustomerGroupEntity
    {
        return $this->customerGroup;
    }

    public function setCustomerGroup(?CustomerGroupEntity $customerGroup): void
    {
        $this->customerGroup = $customerGroup;
    }

    public function getCalculation(): ?\Lohndruckerei\Mensura\Core\Content\Calculation\CalculationEntity
    {
        return $this->calculation;
    }

    public function setCalculation(?\Lohndruckerei\Mensura\Core\Content\Calculation\CalculationEntity $calculation): void
    {
        $this->calculation = $calculation;
    }
}
