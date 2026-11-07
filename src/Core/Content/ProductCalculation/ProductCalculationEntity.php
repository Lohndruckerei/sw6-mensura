<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\ProductCalculation;

use Lohndruckerei\Mensura\Core\Content\Calculation\CalculationEntity;
use Lohndruckerei\Mensura\Core\Content\Material\MaterialEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ProductCalculationEntity extends Entity
{
    use EntityIdTrait;

    protected string $productId;
    protected ?string $productVersionId = null;
    protected string $calculationId;
    protected ?string $fixedMaterialId = null;
    protected ?array $materialIds = null;
    protected ?array $optionValueIds = null;
    protected ?array $defaults = null;

    protected ?ProductEntity $product = null;
    protected ?CalculationEntity $calculation = null;
    protected ?MaterialEntity $fixedMaterial = null;

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getCalculationId(): string
    {
        return $this->calculationId;
    }

    public function getFixedMaterialId(): ?string
    {
        return $this->fixedMaterialId;
    }

    /**
     * @return list<string>
     */
    public function getMaterialIds(): array
    {
        return $this->materialIds ?? [];
    }

    /**
     * @return list<string>
     */
    public function getOptionValueIds(): array
    {
        return $this->optionValueIds ?? [];
    }

    public function getDefaults(): array
    {
        return $this->defaults ?? [];
    }

    public function getCalculation(): ?CalculationEntity
    {
        return $this->calculation;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getProductVersionId(): ?string
    {
        return $this->productVersionId;
    }

    public function setProductVersionId(?string $productVersionId): void
    {
        $this->productVersionId = $productVersionId;
    }

    public function setCalculationId(string $calculationId): void
    {
        $this->calculationId = $calculationId;
    }

    public function setFixedMaterialId(?string $fixedMaterialId): void
    {
        $this->fixedMaterialId = $fixedMaterialId;
    }

    public function setMaterialIds(?array $materialIds): void
    {
        $this->materialIds = $materialIds;
    }

    public function setOptionValueIds(?array $optionValueIds): void
    {
        $this->optionValueIds = $optionValueIds;
    }

    public function setDefaults(?array $defaults): void
    {
        $this->defaults = $defaults;
    }

    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    public function setProduct(?ProductEntity $product): void
    {
        $this->product = $product;
    }

    public function setCalculation(?CalculationEntity $calculation): void
    {
        $this->calculation = $calculation;
    }

    public function getFixedMaterial(): ?MaterialEntity
    {
        return $this->fixedMaterial;
    }

    public function setFixedMaterial(?MaterialEntity $fixedMaterial): void
    {
        $this->fixedMaterial = $fixedMaterial;
    }
}
