<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Calculation\Aggregate\CalculationOptionValue;

use Lohndruckerei\Mensura\Core\Content\Calculation\CalculationEntity;
use Lohndruckerei\Mensura\Core\Content\Material\MaterialEntity;
use Lohndruckerei\Mensura\Core\Content\Option\OptionValueEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class CalculationOptionValueEntity extends Entity
{
    use EntityIdTrait;

    protected string $calculationId;
    protected string $optionValueId;
    protected ?string $materialId = null;
    protected ?bool $active = null;
    protected ?bool $isDefault = null;
    protected ?int $position = null;
    protected ?array $priceComponents = null;
    protected ?array $tiers = null;
    protected ?array $conditions = null;

    protected ?CalculationEntity $calculation = null;
    protected ?OptionValueEntity $optionValue = null;
    protected ?MaterialEntity $material = null;

    public function getCalculationId(): string
    {
        return $this->calculationId;
    }

    public function getOptionValueId(): string
    {
        return $this->optionValueId;
    }

    public function getMaterialId(): ?string
    {
        return $this->materialId;
    }

    public function isActive(): bool
    {
        return (bool) $this->active;
    }

    public function getIsDefault(): ?bool
    {
        return $this->isDefault;
    }

    public function isDefault(): bool
    {
        return (bool) $this->isDefault;
    }

    public function getPriceComponents(): array
    {
        return $this->priceComponents ?? [];
    }

    public function getTiers(): array
    {
        return $this->tiers ?? [];
    }

    public function getConditions(): array
    {
        return $this->conditions ?? [];
    }

    public function getOptionValue(): ?OptionValueEntity
    {
        return $this->optionValue;
    }

    public function setCalculationId(string $calculationId): void
    {
        $this->calculationId = $calculationId;
    }

    public function setOptionValueId(string $optionValueId): void
    {
        $this->optionValueId = $optionValueId;
    }

    public function setMaterialId(?string $materialId): void
    {
        $this->materialId = $materialId;
    }

    public function setActive(?bool $active): void
    {
        $this->active = $active;
    }

    public function setIsDefault(?bool $isDefault): void
    {
        $this->isDefault = $isDefault;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        $this->position = $position;
    }

    public function setPriceComponents(?array $priceComponents): void
    {
        $this->priceComponents = $priceComponents;
    }

    public function setTiers(?array $tiers): void
    {
        $this->tiers = $tiers;
    }

    public function setConditions(?array $conditions): void
    {
        $this->conditions = $conditions;
    }

    public function getCalculation(): ?CalculationEntity
    {
        return $this->calculation;
    }

    public function setCalculation(?CalculationEntity $calculation): void
    {
        $this->calculation = $calculation;
    }

    public function setOptionValue(?OptionValueEntity $optionValue): void
    {
        $this->optionValue = $optionValue;
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
