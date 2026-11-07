<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\ProductProfile\Aggregate\ProductProfileOption;

use Lohndruckerei\Mensura\Core\Content\Material\MaterialEntity;
use Lohndruckerei\Mensura\Core\Content\Option\OptionGroupEntity;
use Lohndruckerei\Mensura\Core\Content\ProductProfile\ProductProfileEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ProductProfileOptionEntity extends Entity
{
    use EntityIdTrait;

    protected string $productProfileId;
    protected string $optionGroupId;
    protected ?string $materialId = null;
    protected string $mode;
    protected ?array $valueIds = null;
    protected ?string $defaultValueId = null;
    protected ?int $position = null;
    protected ?ProductProfileEntity $productProfile = null;
    protected ?OptionGroupEntity $optionGroup = null;
    protected ?MaterialEntity $material = null;

    public function getProductProfileId(): string
    {
        return $this->productProfileId;
    }

    public function setProductProfileId(string $productProfileId): void
    {
        $this->productProfileId = $productProfileId;
    }

    public function getOptionGroupId(): string
    {
        return $this->optionGroupId;
    }

    public function setOptionGroupId(string $optionGroupId): void
    {
        $this->optionGroupId = $optionGroupId;
    }

    public function getMaterialId(): ?string
    {
        return $this->materialId;
    }

    public function setMaterialId(?string $materialId): void
    {
        $this->materialId = $materialId;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function setMode(string $mode): void
    {
        $this->mode = $mode;
    }

    public function getValueIds(): ?array
    {
        return $this->valueIds;
    }

    public function setValueIds(?array $valueIds): void
    {
        $this->valueIds = $valueIds;
    }

    public function getDefaultValueId(): ?string
    {
        return $this->defaultValueId;
    }

    public function setDefaultValueId(?string $defaultValueId): void
    {
        $this->defaultValueId = $defaultValueId;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        $this->position = $position;
    }

    public function getProductProfile(): ?ProductProfileEntity
    {
        return $this->productProfile;
    }

    public function setProductProfile(?ProductProfileEntity $productProfile): void
    {
        $this->productProfile = $productProfile;
    }

    public function getOptionGroup(): ?OptionGroupEntity
    {
        return $this->optionGroup;
    }

    public function setOptionGroup(?OptionGroupEntity $optionGroup): void
    {
        $this->optionGroup = $optionGroup;
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
