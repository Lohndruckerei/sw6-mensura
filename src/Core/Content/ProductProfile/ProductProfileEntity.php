<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\ProductProfile;

use Lohndruckerei\Mensura\Core\Content\ProductProfile\Aggregate\ProductProfileMaterial\ProductProfileMaterialCollection;
use Lohndruckerei\Mensura\Core\Content\ProductProfile\Aggregate\ProductProfileOption\ProductProfileOptionCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ProductProfileEntity extends Entity
{
    use EntityIdTrait;

    protected string $productId;
    protected ?string $productVersionId = null;
    protected string $type;
    protected ?bool $active = null;
    protected ?int $widthMin = null;
    protected ?int $widthMax = null;
    protected ?int $heightMin = null;
    protected ?int $heightMax = null;
    protected ?int $minQuantity = null;
    protected ?int $maxQuantity = null;
    protected ?float $minimumPriceSale = null;
    protected ?float $surchargePercent = null;
    protected ?array $priceComponents = null;
    protected ?array $defaults = null;
    protected ?array $steps = null;
    protected ?string $templateProfileId = null;
    protected ?ProductEntity $product = null;
    protected ?ProductProfileMaterialCollection $materials = null;
    protected ?ProductProfileOptionCollection $options = null;

    public function getProductId(): string
    {
        return $this->productId;
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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function isActive(): bool
    {
        return (bool) $this->active;
    }

    public function setActive(?bool $active): void
    {
        $this->active = $active;
    }

    public function getWidthMin(): ?int
    {
        return $this->widthMin;
    }

    public function setWidthMin(?int $widthMin): void
    {
        $this->widthMin = $widthMin;
    }

    public function getWidthMax(): ?int
    {
        return $this->widthMax;
    }

    public function setWidthMax(?int $widthMax): void
    {
        $this->widthMax = $widthMax;
    }

    public function getHeightMin(): ?int
    {
        return $this->heightMin;
    }

    public function setHeightMin(?int $heightMin): void
    {
        $this->heightMin = $heightMin;
    }

    public function getHeightMax(): ?int
    {
        return $this->heightMax;
    }

    public function setHeightMax(?int $heightMax): void
    {
        $this->heightMax = $heightMax;
    }

    public function getMinQuantity(): ?int
    {
        return $this->minQuantity;
    }

    public function setMinQuantity(?int $minQuantity): void
    {
        $this->minQuantity = $minQuantity;
    }

    public function getMaxQuantity(): ?int
    {
        return $this->maxQuantity;
    }

    public function setMaxQuantity(?int $maxQuantity): void
    {
        $this->maxQuantity = $maxQuantity;
    }

    public function getMinimumPriceSale(): ?float
    {
        return $this->minimumPriceSale;
    }

    public function setMinimumPriceSale(?float $minimumPriceSale): void
    {
        $this->minimumPriceSale = $minimumPriceSale;
    }

    public function getSurchargePercent(): ?float
    {
        return $this->surchargePercent;
    }

    public function setSurchargePercent(?float $surchargePercent): void
    {
        $this->surchargePercent = $surchargePercent;
    }

    public function getPriceComponents(): ?array
    {
        return $this->priceComponents;
    }

    public function setPriceComponents(?array $priceComponents): void
    {
        $this->priceComponents = $priceComponents;
    }

    public function getDefaults(): ?array
    {
        return $this->defaults;
    }

    public function setDefaults(?array $defaults): void
    {
        $this->defaults = $defaults;
    }

    public function getSteps(): ?array
    {
        return $this->steps;
    }

    public function setSteps(?array $steps): void
    {
        $this->steps = $steps;
    }

    public function getTemplateProfileId(): ?string
    {
        return $this->templateProfileId;
    }

    public function setTemplateProfileId(?string $templateProfileId): void
    {
        $this->templateProfileId = $templateProfileId;
    }

    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    public function setProduct(?ProductEntity $product): void
    {
        $this->product = $product;
    }

    public function getMaterials(): ?ProductProfileMaterialCollection
    {
        return $this->materials;
    }

    public function setMaterials(ProductProfileMaterialCollection $materials): void
    {
        $this->materials = $materials;
    }

    public function getOptions(): ?ProductProfileOptionCollection
    {
        return $this->options;
    }

    public function setOptions(ProductProfileOptionCollection $options): void
    {
        $this->options = $options;
    }
}
