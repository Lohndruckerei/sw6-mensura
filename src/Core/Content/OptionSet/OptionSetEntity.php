<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\OptionSet;

use Lohndruckerei\Mensura\Core\Content\Calculation\CalculationCollection;
use Lohndruckerei\Mensura\Core\Content\Material\MaterialCollection;
use Lohndruckerei\Mensura\Core\Content\Option\OptionValueCollection;
use Lohndruckerei\Mensura\Core\Content\OptionSet\Aggregate\OptionSetGroup\OptionSetGroupCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class OptionSetEntity extends Entity
{
    use EntityIdTrait;

    protected string $technicalName;
    protected string $name;
    protected ?string $description = null;
    protected ?OptionSetGroupCollection $groups = null;
    protected ?CalculationCollection $calculations = null;
    protected ?MaterialCollection $materials = null;
    protected ?OptionValueCollection $optionValues = null;

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function getName(): string
    {
        return $this->name;
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

    public function getGroups(): ?OptionSetGroupCollection
    {
        return $this->groups;
    }

    public function setGroups(OptionSetGroupCollection $groups): void
    {
        $this->groups = $groups;
    }

    public function getCalculations(): ?CalculationCollection
    {
        return $this->calculations;
    }

    public function setCalculations(CalculationCollection $calculations): void
    {
        $this->calculations = $calculations;
    }

    public function getMaterials(): ?MaterialCollection
    {
        return $this->materials;
    }

    public function setMaterials(MaterialCollection $materials): void
    {
        $this->materials = $materials;
    }

    public function getOptionValues(): ?OptionValueCollection
    {
        return $this->optionValues;
    }

    public function setOptionValues(OptionValueCollection $optionValues): void
    {
        $this->optionValues = $optionValues;
    }
}
