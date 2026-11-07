<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Material;

use Lohndruckerei\Mensura\Core\Content\Calculation\CalculationCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class MaterialEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $technicalName;
    protected ?string $code = null;
    protected ?bool $active = null;
    protected ?int $position = null;
    protected ?float $pricePurchase = null;
    protected ?float $priceSale = null;
    protected ?float $minimumPricePurchase = null;
    protected ?float $minimumPriceSale = null;
    protected ?string $tierBasis = null;
    protected ?array $tiers = null;
    protected ?int $sheetWidth = null;
    protected ?int $sheetHeight = null;
    protected ?int $widthMax = null;
    protected ?int $heightMax = null;
    protected ?float $weightPerSqm = null;
    protected ?array $properties = null;
    protected ?string $supplierId = null;
    protected ?array $supplierCodes = null;
    protected ?string $mediaId = null;
    protected ?string $name = null;
    protected ?string $description = null;

    protected ?MediaEntity $media = null;
    protected ?CalculationCollection $calculations = null;
    protected ?\Lohndruckerei\Mensura\Core\Content\Calculation\Aggregate\CalculationOptionValue\CalculationOptionValueCollection $calculationOptionValues = null;
    protected ?\Lohndruckerei\Mensura\Core\Content\ProductCalculation\ProductCalculationCollection $fixedProducts = null;
    /** @var \Shopware\Core\Framework\DataAbstractionLayer\EntityCollection<TranslationEntity>|null */
    protected $translations;

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function isActive(): bool
    {
        return (bool) $this->active;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getPricePurchase(): ?float
    {
        return $this->pricePurchase;
    }

    public function getPriceSale(): ?float
    {
        return $this->priceSale;
    }

    public function getMinimumPricePurchase(): ?float
    {
        return $this->minimumPricePurchase;
    }

    public function getMinimumPriceSale(): ?float
    {
        return $this->minimumPriceSale;
    }

    public function getTierBasis(): ?string
    {
        return $this->tierBasis;
    }

    public function getTiers(): array
    {
        return $this->tiers ?? [];
    }

    public function getWidthMax(): ?int
    {
        return $this->widthMax;
    }

    public function getHeightMax(): ?int
    {
        return $this->heightMax;
    }

    public function getSupplierId(): ?string
    {
        return $this->supplierId;
    }

    public function getSupplierCodes(): array
    {
        return $this->supplierCodes ?? [];
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
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

    public function setPricePurchase(?float $pricePurchase): void
    {
        $this->pricePurchase = $pricePurchase;
    }

    public function setPriceSale(?float $priceSale): void
    {
        $this->priceSale = $priceSale;
    }

    public function setMinimumPricePurchase(?float $minimumPricePurchase): void
    {
        $this->minimumPricePurchase = $minimumPricePurchase;
    }

    public function setMinimumPriceSale(?float $minimumPriceSale): void
    {
        $this->minimumPriceSale = $minimumPriceSale;
    }

    public function setTierBasis(?string $tierBasis): void
    {
        $this->tierBasis = $tierBasis;
    }

    public function setTiers(?array $tiers): void
    {
        $this->tiers = $tiers;
    }

    public function getSheetWidth(): ?int
    {
        return $this->sheetWidth;
    }

    public function setSheetWidth(?int $sheetWidth): void
    {
        $this->sheetWidth = $sheetWidth;
    }

    public function getSheetHeight(): ?int
    {
        return $this->sheetHeight;
    }

    public function setSheetHeight(?int $sheetHeight): void
    {
        $this->sheetHeight = $sheetHeight;
    }

    public function setWidthMax(?int $widthMax): void
    {
        $this->widthMax = $widthMax;
    }

    public function setHeightMax(?int $heightMax): void
    {
        $this->heightMax = $heightMax;
    }

    public function getWeightPerSqm(): ?float
    {
        return $this->weightPerSqm;
    }

    public function setWeightPerSqm(?float $weightPerSqm): void
    {
        $this->weightPerSqm = $weightPerSqm;
    }

    public function getProperties(): ?array
    {
        return $this->properties;
    }

    public function setProperties(?array $properties): void
    {
        $this->properties = $properties;
    }

    public function setSupplierId(?string $supplierId): void
    {
        $this->supplierId = $supplierId;
    }

    public function setSupplierCodes(?array $supplierCodes): void
    {
        $this->supplierCodes = $supplierCodes;
    }

    public function getMediaId(): ?string
    {
        return $this->mediaId;
    }

    public function setMediaId(?string $mediaId): void
    {
        $this->mediaId = $mediaId;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getMedia(): ?MediaEntity
    {
        return $this->media;
    }

    public function setMedia(?MediaEntity $media): void
    {
        $this->media = $media;
    }

    public function getCalculations(): ?CalculationCollection
    {
        return $this->calculations;
    }

    public function setCalculations(CalculationCollection $calculations): void
    {
        $this->calculations = $calculations;
    }

    public function getTranslations()
    {
        return $this->translations;
    }

    public function setTranslations(\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getCalculationOptionValues(): ?\Lohndruckerei\Mensura\Core\Content\Calculation\Aggregate\CalculationOptionValue\CalculationOptionValueCollection
    {
        return $this->calculationOptionValues;
    }

    public function setCalculationOptionValues(\Lohndruckerei\Mensura\Core\Content\Calculation\Aggregate\CalculationOptionValue\CalculationOptionValueCollection $calculationOptionValues): void
    {
        $this->calculationOptionValues = $calculationOptionValues;
    }

    public function getFixedProducts(): ?\Lohndruckerei\Mensura\Core\Content\ProductCalculation\ProductCalculationCollection
    {
        return $this->fixedProducts;
    }

    public function setFixedProducts(\Lohndruckerei\Mensura\Core\Content\ProductCalculation\ProductCalculationCollection $fixedProducts): void
    {
        $this->fixedProducts = $fixedProducts;
    }
}
