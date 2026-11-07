<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\AccessoryRule;

use Lohndruckerei\Mensura\Core\Content\AccessoryRule\Aggregate\AccessoryRuleTranslation\AccessoryRuleTranslationCollection;
use Lohndruckerei\Mensura\Core\Content\Feature\FeatureEntity;
use Lohndruckerei\Mensura\Core\Content\Material\MaterialEntity;
use Lohndruckerei\Mensura\Core\Content\Option\OptionValueEntity;
use Lohndruckerei\Mensura\Core\Content\UseCase\UseCaseEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class AccessoryRuleEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $triggerType;
    protected ?string $materialId = null;
    protected ?string $optionValueId = null;
    protected ?string $useCaseId = null;
    protected ?string $featureId = null;
    protected ?array $featureCondition = null;
    protected string $productId;
    protected ?string $productVersionId = null;
    protected string $quantityRule;
    protected float $quantityFactor;
    protected string $level;
    protected ?bool $active = null;
    protected ?int $position = null;
    protected ?string $name = null;
    protected ?string $reason = null;
    protected ?AccessoryRuleTranslationCollection $translations = null;
    protected ?MaterialEntity $material = null;
    protected ?OptionValueEntity $optionValue = null;
    protected ?UseCaseEntity $useCase = null;
    protected ?FeatureEntity $feature = null;
    protected ?ProductEntity $product = null;

    public function getTriggerType(): string
    {
        return $this->triggerType;
    }

    public function setTriggerType(string $triggerType): void
    {
        $this->triggerType = $triggerType;
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

    public function getUseCaseId(): ?string
    {
        return $this->useCaseId;
    }

    public function setUseCaseId(?string $useCaseId): void
    {
        $this->useCaseId = $useCaseId;
    }

    public function getFeatureId(): ?string
    {
        return $this->featureId;
    }

    public function setFeatureId(?string $featureId): void
    {
        $this->featureId = $featureId;
    }

    public function getFeatureCondition(): ?array
    {
        return $this->featureCondition;
    }

    public function setFeatureCondition(?array $featureCondition): void
    {
        $this->featureCondition = $featureCondition;
    }

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

    public function getQuantityRule(): string
    {
        return $this->quantityRule;
    }

    public function setQuantityRule(string $quantityRule): void
    {
        $this->quantityRule = $quantityRule;
    }

    public function getQuantityFactor(): float
    {
        return $this->quantityFactor;
    }

    public function setQuantityFactor(float $quantityFactor): void
    {
        $this->quantityFactor = $quantityFactor;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function setLevel(string $level): void
    {
        $this->level = $level;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): void
    {
        $this->reason = $reason;
    }

    public function getTranslations(): ?AccessoryRuleTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(AccessoryRuleTranslationCollection $translations): void
    {
        $this->translations = $translations;
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

    public function getUseCase(): ?UseCaseEntity
    {
        return $this->useCase;
    }

    public function setUseCase(?UseCaseEntity $useCase): void
    {
        $this->useCase = $useCase;
    }

    public function getFeature(): ?FeatureEntity
    {
        return $this->feature;
    }

    public function setFeature(?FeatureEntity $feature): void
    {
        $this->feature = $feature;
    }

    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    public function setProduct(?ProductEntity $product): void
    {
        $this->product = $product;
    }
}
