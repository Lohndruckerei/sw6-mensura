<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\ProductProfile\Aggregate\ProductProfileMaterial;

use Lohndruckerei\Mensura\Core\Content\Calculation\CalculationEntity;
use Lohndruckerei\Mensura\Core\Content\Material\MaterialEntity;
use Lohndruckerei\Mensura\Core\Content\ProductProfile\ProductProfileEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ProductProfileMaterialEntity extends Entity
{
    use EntityIdTrait;

    protected string $productProfileId;
    protected string $calculationId;
    protected ?string $materialId = null;
    protected ?int $position = null;
    protected ?float $surchargePercent = null;
    protected ?ProductProfileEntity $productProfile = null;
    protected ?CalculationEntity $calculation = null;
    protected ?MaterialEntity $material = null;

    public function getProductProfileId(): string
    {
        return $this->productProfileId;
    }

    public function setProductProfileId(string $productProfileId): void
    {
        $this->productProfileId = $productProfileId;
    }

    public function getCalculationId(): string
    {
        return $this->calculationId;
    }

    public function setCalculationId(string $calculationId): void
    {
        $this->calculationId = $calculationId;
    }

    public function getMaterialId(): ?string
    {
        return $this->materialId;
    }

    public function setMaterialId(?string $materialId): void
    {
        $this->materialId = $materialId;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        $this->position = $position;
    }

    public function getSurchargePercent(): ?float
    {
        return $this->surchargePercent;
    }

    public function setSurchargePercent(?float $surchargePercent): void
    {
        $this->surchargePercent = $surchargePercent;
    }

    public function getProductProfile(): ?ProductProfileEntity
    {
        return $this->productProfile;
    }

    public function setProductProfile(?ProductProfileEntity $productProfile): void
    {
        $this->productProfile = $productProfile;
    }

    public function getCalculation(): ?CalculationEntity
    {
        return $this->calculation;
    }

    public function setCalculation(?CalculationEntity $calculation): void
    {
        $this->calculation = $calculation;
    }

    public function getMaterial(): ?MaterialEntity
    {
        return $this->material;
    }

    public function setMaterial(?MaterialEntity $material): void
    {
        $this->material = $material;
    }
}
