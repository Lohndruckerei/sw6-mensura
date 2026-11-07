<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\PriceTable;

use Lohndruckerei\Mensura\Core\Content\Calculation\CalculationEntity;
use Lohndruckerei\Mensura\Core\Content\Material\MaterialEntity;
use Lohndruckerei\Mensura\Core\Content\PriceTable\Aggregate\PriceTableRow\PriceTableRowCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class PriceTableEntity extends Entity
{
    use EntityIdTrait;

    protected string $calculationId;
    protected ?string $materialId = null;
    protected ?string $name = null;
    protected ?array $optionValueIds = null;
    protected string $priceType;
    protected ?bool $active = null;
    protected ?int $position = null;
    protected ?CalculationEntity $calculation = null;
    protected ?MaterialEntity $material = null;
    protected ?PriceTableRowCollection $rows = null;

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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getOptionValueIds(): ?array
    {
        return $this->optionValueIds;
    }

    public function setOptionValueIds(?array $optionValueIds): void
    {
        $this->optionValueIds = $optionValueIds;
    }

    public function getPriceType(): string
    {
        return $this->priceType;
    }

    public function setPriceType(string $priceType): void
    {
        $this->priceType = $priceType;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): void
    {
        $this->active = $active;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        $this->position = $position;
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

    public function getRows(): ?PriceTableRowCollection
    {
        return $this->rows;
    }

    public function setRows(PriceTableRowCollection $rows): void
    {
        $this->rows = $rows;
    }
}
