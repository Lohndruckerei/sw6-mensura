<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Feature\Aggregate\FeatureValue;

use Lohndruckerei\Mensura\Core\Content\Feature\FeatureEntity;
use Lohndruckerei\Mensura\Core\Content\Material\MaterialEntity;
use Lohndruckerei\Mensura\Core\Content\Option\OptionValueEntity;
use Lohndruckerei\Mensura\Core\Content\ProductProfile\ProductProfileEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class FeatureValueEntity extends Entity
{
    use EntityIdTrait;

    protected string $featureId;
    protected ?string $materialId = null;
    protected ?string $optionValueId = null;
    protected ?string $productProfileId = null;
    protected mixed $value = null;
    protected ?FeatureEntity $feature = null;
    protected ?MaterialEntity $material = null;
    protected ?OptionValueEntity $optionValue = null;
    protected ?ProductProfileEntity $productProfile = null;

    public function getFeatureId(): string
    {
        return $this->featureId;
    }

    public function setFeatureId(string $featureId): void
    {
        $this->featureId = $featureId;
    }

    public function getMaterialId(): ?string
    {
        return $this->materialId;
    }

    public function setMaterialId(?string $materialId): void
    {
        $this->materialId = $materialId;
    }

    public function getOptionValueId(): ?string
    {
        return $this->optionValueId;
    }

    public function setOptionValueId(?string $optionValueId): void
    {
        $this->optionValueId = $optionValueId;
    }

    public function getProductProfileId(): ?string
    {
        return $this->productProfileId;
    }

    public function setProductProfileId(?string $productProfileId): void
    {
        $this->productProfileId = $productProfileId;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function setValue(mixed $value): void
    {
        $this->value = $value;
    }

    public function getFeature(): ?FeatureEntity
    {
        return $this->feature;
    }

    public function setFeature(?FeatureEntity $feature): void
    {
        $this->feature = $feature;
    }

    public function getMaterial(): ?MaterialEntity
    {
        return $this->material;
    }

    public function setMaterial(?MaterialEntity $material): void
    {
        $this->material = $material;
    }

    public function getOptionValue(): ?OptionValueEntity
    {
        return $this->optionValue;
    }

    public function setOptionValue(?OptionValueEntity $optionValue): void
    {
        $this->optionValue = $optionValue;
    }

    public function getProductProfile(): ?ProductProfileEntity
    {
        return $this->productProfile;
    }

    public function setProductProfile(?ProductProfileEntity $productProfile): void
    {
        $this->productProfile = $productProfile;
    }
}
