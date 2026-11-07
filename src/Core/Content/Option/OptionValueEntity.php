<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Option;

use Lohndruckerei\Mensura\Core\Content\AccessoryRule\AccessoryRuleCollection;
use Lohndruckerei\Mensura\Core\Content\Feature\Aggregate\FeatureValue\FeatureValueCollection;
use Lohndruckerei\Mensura\Core\Content\Calculation\Aggregate\CalculationOptionValue\CalculationOptionValueCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class OptionValueEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $optionSetId = null;
    protected ?\Lohndruckerei\Mensura\Core\Content\OptionSet\OptionSetEntity $optionSet = null;

    protected string $optionGroupId;
    protected string $technicalName;
    protected ?string $code = null;
    protected ?int $position = null;
    protected ?int $widthAdd = null;
    protected ?int $heightAdd = null;
    protected ?float $widthFactor = null;
    protected ?float $heightFactor = null;
    protected ?int $deliveryDays = null;
    protected ?int $width = null;
    protected ?int $height = null;
    protected ?array $exclusions = null;
    protected ?array $supplierCodes = null;
    protected ?string $mediaId = null;
    protected ?string $name = null;
    protected ?string $description = null;

    protected ?OptionGroupEntity $optionGroup = null;
    protected ?MediaEntity $media = null;
    protected ?CalculationOptionValueCollection $calculationAssignments = null;
    protected $translations;
    protected ?array $availability = null;
    protected ?array $badges = null;
    protected ?FeatureValueCollection $featureValues = null;
    protected ?AccessoryRuleCollection $accessoryRules = null;

    public function getOptionGroupId(): string
    {
        return $this->optionGroupId;
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getWidthAdd(): int
    {
        return $this->widthAdd ?? 0;
    }

    public function getHeightAdd(): int
    {
        return $this->heightAdd ?? 0;
    }

    public function getDeliveryDays(): ?int
    {
        return $this->deliveryDays;
    }

    public function getExclusions(): array
    {
        return $this->exclusions ?? [];
    }

    public function getSupplierCodes(): array
    {
        return $this->supplierCodes ?? [];
    }

    public function getOptionGroup(): ?OptionGroupEntity
    {
        return $this->optionGroup;
    }

    public function setOptionGroupId(string $optionGroupId): void
    {
        $this->optionGroupId = $optionGroupId;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        $this->position = $position;
    }

    public function setWidthAdd(?int $widthAdd): void
    {
        $this->widthAdd = $widthAdd;
    }

    public function setHeightAdd(?int $heightAdd): void
    {
        $this->heightAdd = $heightAdd;
    }

    public function getWidthFactor(): ?float
    {
        return $this->widthFactor;
    }

    public function setWidthFactor(?float $widthFactor): void
    {
        $this->widthFactor = $widthFactor;
    }

    public function getHeightFactor(): ?float
    {
        return $this->heightFactor;
    }

    public function setHeightFactor(?float $heightFactor): void
    {
        $this->heightFactor = $heightFactor;
    }

    public function setDeliveryDays(?int $deliveryDays): void
    {
        $this->deliveryDays = $deliveryDays;
    }

    public function setExclusions(?array $exclusions): void
    {
        $this->exclusions = $exclusions;
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

    public function setOptionGroup(?OptionGroupEntity $optionGroup): void
    {
        $this->optionGroup = $optionGroup;
    }

    public function getMedia(): ?MediaEntity
    {
        return $this->media;
    }

    public function setMedia(?MediaEntity $media): void
    {
        $this->media = $media;
    }

    public function getCalculationAssignments(): ?CalculationOptionValueCollection
    {
        return $this->calculationAssignments;
    }

    public function setCalculationAssignments(CalculationOptionValueCollection $calculationAssignments): void
    {
        $this->calculationAssignments = $calculationAssignments;
    }

    public function getTranslations()
    {
        return $this->translations;
    }

    public function setTranslations(\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getOptionSetId(): ?string
    {
        return $this->optionSetId;
    }

    public function setOptionSetId(?string $optionSetId): void
    {
        $this->optionSetId = $optionSetId;
    }

    public function getOptionSet(): ?\Lohndruckerei\Mensura\Core\Content\OptionSet\OptionSetEntity
    {
        return $this->optionSet;
    }

    public function setOptionSet(?\Lohndruckerei\Mensura\Core\Content\OptionSet\OptionSetEntity $optionSet): void
    {
        $this->optionSet = $optionSet;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function setWidth(?int $width): void
    {
        $this->width = $width;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(?int $height): void
    {
        $this->height = $height;
    }

    public function getAvailability(): ?array
    {
        return $this->availability;
    }

    public function setAvailability(?array $availability): void
    {
        $this->availability = $availability;
    }

    public function getBadges(): ?array
    {
        return $this->badges;
    }

    public function setBadges(?array $badges): void
    {
        $this->badges = $badges;
    }

    public function getFeatureValues(): ?FeatureValueCollection
    {
        return $this->featureValues;
    }

    public function setFeatureValues(FeatureValueCollection $featureValues): void
    {
        $this->featureValues = $featureValues;
    }

    public function getAccessoryRules(): ?AccessoryRuleCollection
    {
        return $this->accessoryRules;
    }

    public function setAccessoryRules(AccessoryRuleCollection $accessoryRules): void
    {
        $this->accessoryRules = $accessoryRules;
    }
}
