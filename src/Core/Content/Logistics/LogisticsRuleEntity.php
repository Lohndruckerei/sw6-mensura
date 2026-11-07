<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Logistics;

use Lohndruckerei\Mensura\Core\Content\Calculation\CalculationEntity;
use Lohndruckerei\Mensura\Core\Content\Material\MaterialEntity;
use Lohndruckerei\Mensura\Core\Content\Option\OptionValueEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class LogisticsRuleEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $calculationId = null;
    protected ?string $materialId = null;
    protected ?string $optionValueId = null;
    protected string $costType;
    protected string $form;
    protected float $value;
    protected ?int $rampFrom = null;
    protected ?int $rampTo = null;
    protected ?string $thresholdBasis = null;
    protected ?float $thresholdFrom = null;
    protected ?float $thresholdTo = null;
    protected ?float $minimumAmount = null;
    protected ?string $minimumScope = null;
    protected ?\DateTimeInterface $validFrom = null;
    protected ?bool $active = null;
    protected ?string $source = null;
    protected ?array $sourceReference = null;
    protected ?string $note = null;
    protected ?CalculationEntity $calculation = null;
    protected ?MaterialEntity $material = null;
    protected ?OptionValueEntity $optionValue = null;

    public function getCalculationId(): ?string
    {
        return $this->calculationId;
    }

    public function setCalculationId(?string $calculationId): void
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

    public function getOptionValueId(): ?string
    {
        return $this->optionValueId;
    }

    public function setOptionValueId(?string $optionValueId): void
    {
        $this->optionValueId = $optionValueId;
    }

    public function getCostType(): string
    {
        return $this->costType;
    }

    public function setCostType(string $costType): void
    {
        $this->costType = $costType;
    }

    public function getForm(): string
    {
        return $this->form;
    }

    public function setForm(string $form): void
    {
        $this->form = $form;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function setValue(float $value): void
    {
        $this->value = $value;
    }

    public function getRampFrom(): ?int
    {
        return $this->rampFrom;
    }

    public function setRampFrom(?int $rampFrom): void
    {
        $this->rampFrom = $rampFrom;
    }

    public function getRampTo(): ?int
    {
        return $this->rampTo;
    }

    public function setRampTo(?int $rampTo): void
    {
        $this->rampTo = $rampTo;
    }

    public function getThresholdBasis(): ?string
    {
        return $this->thresholdBasis;
    }

    public function setThresholdBasis(?string $thresholdBasis): void
    {
        $this->thresholdBasis = $thresholdBasis;
    }

    public function getThresholdFrom(): ?float
    {
        return $this->thresholdFrom;
    }

    public function setThresholdFrom(?float $thresholdFrom): void
    {
        $this->thresholdFrom = $thresholdFrom;
    }

    public function getThresholdTo(): ?float
    {
        return $this->thresholdTo;
    }

    public function setThresholdTo(?float $thresholdTo): void
    {
        $this->thresholdTo = $thresholdTo;
    }

    public function getMinimumAmount(): ?float
    {
        return $this->minimumAmount;
    }

    public function setMinimumAmount(?float $minimumAmount): void
    {
        $this->minimumAmount = $minimumAmount;
    }

    public function getMinimumScope(): ?string
    {
        return $this->minimumScope;
    }

    public function setMinimumScope(?string $minimumScope): void
    {
        $this->minimumScope = $minimumScope;
    }

    public function getValidFrom(): ?\DateTimeInterface
    {
        return $this->validFrom;
    }

    public function setValidFrom(?\DateTimeInterface $validFrom): void
    {
        $this->validFrom = $validFrom;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): void
    {
        $this->active = $active;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): void
    {
        $this->source = $source;
    }

    public function getSourceReference(): ?array
    {
        return $this->sourceReference;
    }

    public function setSourceReference(?array $sourceReference): void
    {
        $this->sourceReference = $sourceReference;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): void
    {
        $this->note = $note;
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

    public function getOptionValue(): ?OptionValueEntity
    {
        return $this->optionValue;
    }

    public function setOptionValue(?OptionValueEntity $optionValue): void
    {
        $this->optionValue = $optionValue;
    }
}
