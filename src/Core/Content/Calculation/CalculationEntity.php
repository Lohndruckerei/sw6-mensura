<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Calculation;

use Lohndruckerei\Mensura\Core\Content\PriceTable\PriceTableCollection;
use Lohndruckerei\Mensura\Core\Content\Calculation\Aggregate\CalculationCustomerGroup\CalculationCustomerGroupCollection;
use Lohndruckerei\Mensura\Core\Content\Calculation\Aggregate\CalculationOptionValue\CalculationOptionValueCollection;
use Lohndruckerei\Mensura\Core\Content\Material\MaterialCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class CalculationEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $optionSetId = null;
    protected string $motifMode;
    protected string $tierScope;
    protected string $bestPriceMode;
    protected ?\Lohndruckerei\Mensura\Core\Content\OptionSet\OptionSetEntity $optionSet = null;
    protected ?\Lohndruckerei\Mensura\Core\Content\ProductProfile\Aggregate\ProductProfileMaterial\ProductProfileMaterialCollection $profileMaterials = null;

    protected string $technicalName;
    protected string $name;
    protected ?string $description = null;
    protected ?bool $active = null;
    protected string $unit;

    protected string $formatMode;
    protected ?array $fixedFormats = null;
    protected ?int $widthMin = null;
    protected ?int $widthMax = null;
    protected ?int $heightMin = null;
    protected ?int $heightMax = null;
    protected ?bool $allowRotation = null;
    protected ?float $minArea = null;
    protected ?float $areaRounding = null;
    protected ?int $maxMotifs = null;
    protected ?int $maxQuantity = null;

    /** @var list<array{type: string, purchase?: float|null, sale?: float|null}>|null */
    protected ?array $priceComponents = null;
    protected ?float $minimumPricePurchase = null;
    protected ?float $minimumPriceSale = null;
    protected string $tierBasis;
    /** @var list<array{from: float, purchase?: float|null, sale?: float|null, discount?: float|null}>|null */
    protected ?array $tiers = null;
    protected ?float $priceRounding = null;
    protected ?string $formula = null;

    protected string $saleRule;
    protected ?float $saleRuleValue = null;
    protected ?float $marginMin = null;
    protected ?float $marginMax = null;

    protected ?string $supplierId = null;
    /** @var list<array{supplierId: string, code: string}>|null */
    protected ?array $supplierCodes = null;

    protected ?MaterialCollection $materials = null;
    protected ?CalculationOptionValueCollection $optionValues = null;
    protected ?CalculationCustomerGroupCollection $customerGroups = null;
    protected ?float $setupPricePurchase = null;
    protected ?float $setupPriceSale = null;
    protected ?array $quantitySteps = null;
    protected ?PriceTableCollection $priceTables = null;

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isActive(): bool
    {
        return (bool) $this->active;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function getFormatMode(): string
    {
        return $this->formatMode;
    }

    public function getPriceComponents(): array
    {
        return $this->priceComponents ?? [];
    }

    public function getMinimumPriceSale(): ?float
    {
        return $this->minimumPriceSale;
    }

    public function getMinimumPricePurchase(): ?float
    {
        return $this->minimumPricePurchase;
    }

    public function getTierBasis(): string
    {
        return $this->tierBasis;
    }

    public function getTiers(): array
    {
        return $this->tiers ?? [];
    }

    public function getSaleRule(): string
    {
        return $this->saleRule;
    }

    public function getSupplierId(): ?string
    {
        return $this->supplierId;
    }

    public function getSupplierCodes(): array
    {
        return $this->supplierCodes ?? [];
    }

    public function getMaterials(): ?MaterialCollection
    {
        return $this->materials;
    }

    public function getOptionValues(): ?CalculationOptionValueCollection
    {
        return $this->optionValues;
    }

    public function getCustomerGroups(): ?CalculationCustomerGroupCollection
    {
        return $this->customerGroups;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function setName(string $name): void
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

    public function setActive(?bool $active): void
    {
        $this->active = $active;
    }

    public function setUnit(string $unit): void
    {
        $this->unit = $unit;
    }

    public function setFormatMode(string $formatMode): void
    {
        $this->formatMode = $formatMode;
    }

    public function getFixedFormats(): ?array
    {
        return $this->fixedFormats;
    }

    public function setFixedFormats(?array $fixedFormats): void
    {
        $this->fixedFormats = $fixedFormats;
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

    public function isAllowRotation(): bool
    {
        return (bool) $this->allowRotation;
    }

    public function setAllowRotation(?bool $allowRotation): void
    {
        $this->allowRotation = $allowRotation;
    }

    public function getMinArea(): ?float
    {
        return $this->minArea;
    }

    public function setMinArea(?float $minArea): void
    {
        $this->minArea = $minArea;
    }

    public function getAreaRounding(): ?float
    {
        return $this->areaRounding;
    }

    public function setAreaRounding(?float $areaRounding): void
    {
        $this->areaRounding = $areaRounding;
    }

    public function getMaxMotifs(): ?int
    {
        return $this->maxMotifs;
    }

    public function setMaxMotifs(?int $maxMotifs): void
    {
        $this->maxMotifs = $maxMotifs;
    }

    public function getMaxQuantity(): ?int
    {
        return $this->maxQuantity;
    }

    public function setMaxQuantity(?int $maxQuantity): void
    {
        $this->maxQuantity = $maxQuantity;
    }

    public function setPriceComponents(?array $priceComponents): void
    {
        $this->priceComponents = $priceComponents;
    }

    public function setMinimumPricePurchase(?float $minimumPricePurchase): void
    {
        $this->minimumPricePurchase = $minimumPricePurchase;
    }

    public function setMinimumPriceSale(?float $minimumPriceSale): void
    {
        $this->minimumPriceSale = $minimumPriceSale;
    }

    public function setTierBasis(string $tierBasis): void
    {
        $this->tierBasis = $tierBasis;
    }

    public function setTiers(?array $tiers): void
    {
        $this->tiers = $tiers;
    }

    public function getPriceRounding(): ?float
    {
        return $this->priceRounding;
    }

    public function setPriceRounding(?float $priceRounding): void
    {
        $this->priceRounding = $priceRounding;
    }

    public function getFormula(): ?string
    {
        return $this->formula;
    }

    public function setFormula(?string $formula): void
    {
        $this->formula = $formula;
    }

    public function setSaleRule(string $saleRule): void
    {
        $this->saleRule = $saleRule;
    }

    public function getSaleRuleValue(): ?float
    {
        return $this->saleRuleValue;
    }

    public function setSaleRuleValue(?float $saleRuleValue): void
    {
        $this->saleRuleValue = $saleRuleValue;
    }

    public function getMarginMin(): ?float
    {
        return $this->marginMin;
    }

    public function setMarginMin(?float $marginMin): void
    {
        $this->marginMin = $marginMin;
    }

    public function getMarginMax(): ?float
    {
        return $this->marginMax;
    }

    public function setMarginMax(?float $marginMax): void
    {
        $this->marginMax = $marginMax;
    }

    public function setSupplierId(?string $supplierId): void
    {
        $this->supplierId = $supplierId;
    }

    public function setSupplierCodes(?array $supplierCodes): void
    {
        $this->supplierCodes = $supplierCodes;
    }

    public function setMaterials(MaterialCollection $materials): void
    {
        $this->materials = $materials;
    }

    public function setOptionValues(CalculationOptionValueCollection $optionValues): void
    {
        $this->optionValues = $optionValues;
    }

    public function setCustomerGroups(CalculationCustomerGroupCollection $customerGroups): void
    {
        $this->customerGroups = $customerGroups;
    }

    public function getOptionSetId(): ?string
    {
        return $this->optionSetId;
    }

    public function setOptionSetId(?string $optionSetId): void
    {
        $this->optionSetId = $optionSetId;
    }

    public function getMotifMode(): string
    {
        return $this->motifMode;
    }

    public function setMotifMode(string $motifMode): void
    {
        $this->motifMode = $motifMode;
    }

    public function getTierScope(): string
    {
        return $this->tierScope;
    }

    public function setTierScope(string $tierScope): void
    {
        $this->tierScope = $tierScope;
    }

    public function getBestPriceMode(): string
    {
        return $this->bestPriceMode;
    }

    public function setBestPriceMode(string $bestPriceMode): void
    {
        $this->bestPriceMode = $bestPriceMode;
    }

    public function getOptionSet(): ?\Lohndruckerei\Mensura\Core\Content\OptionSet\OptionSetEntity
    {
        return $this->optionSet;
    }

    public function setOptionSet(?\Lohndruckerei\Mensura\Core\Content\OptionSet\OptionSetEntity $optionSet): void
    {
        $this->optionSet = $optionSet;
    }

    public function getProfileMaterials(): ?\Lohndruckerei\Mensura\Core\Content\ProductProfile\Aggregate\ProductProfileMaterial\ProductProfileMaterialCollection
    {
        return $this->profileMaterials;
    }

    public function setProfileMaterials(\Lohndruckerei\Mensura\Core\Content\ProductProfile\Aggregate\ProductProfileMaterial\ProductProfileMaterialCollection $profileMaterials): void
    {
        $this->profileMaterials = $profileMaterials;
    }

    public function getSetupPricePurchase(): ?float
    {
        return $this->setupPricePurchase;
    }

    public function setSetupPricePurchase(?float $setupPricePurchase): void
    {
        $this->setupPricePurchase = $setupPricePurchase;
    }

    public function getSetupPriceSale(): ?float
    {
        return $this->setupPriceSale;
    }

    public function setSetupPriceSale(?float $setupPriceSale): void
    {
        $this->setupPriceSale = $setupPriceSale;
    }

    public function getQuantitySteps(): ?array
    {
        return $this->quantitySteps;
    }

    public function setQuantitySteps(?array $quantitySteps): void
    {
        $this->quantitySteps = $quantitySteps;
    }

    public function getPriceTables(): ?PriceTableCollection
    {
        return $this->priceTables;
    }

    public function setPriceTables(PriceTableCollection $priceTables): void
    {
        $this->priceTables = $priceTables;
    }
}
