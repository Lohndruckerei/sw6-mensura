<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\OptionSet\Aggregate\OptionSetGroup;

use Lohndruckerei\Mensura\Core\Content\Option\OptionGroupEntity;
use Lohndruckerei\Mensura\Core\Content\OptionSet\OptionSetEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class OptionSetGroupEntity extends Entity
{
    use EntityIdTrait;

    protected string $optionSetId;
    protected string $optionGroupId;
    protected ?int $position = null;
    protected ?bool $required = null;
    protected ?OptionSetEntity $optionSet = null;
    protected ?OptionGroupEntity $optionGroup = null;

    public function getOptionSetId(): string
    {
        return $this->optionSetId;
    }

    public function setOptionSetId(string $optionSetId): void
    {
        $this->optionSetId = $optionSetId;
    }

    public function getOptionGroupId(): string
    {
        return $this->optionGroupId;
    }

    public function setOptionGroupId(string $optionGroupId): void
    {
        $this->optionGroupId = $optionGroupId;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        $this->position = $position;
    }

    public function isRequired(): bool
    {
        return (bool) $this->required;
    }

    public function setRequired(?bool $required): void
    {
        $this->required = $required;
    }

    public function getOptionSet(): ?OptionSetEntity
    {
        return $this->optionSet;
    }

    public function setOptionSet(?OptionSetEntity $optionSet): void
    {
        $this->optionSet = $optionSet;
    }

    public function getOptionGroup(): ?OptionGroupEntity
    {
        return $this->optionGroup;
    }

    public function setOptionGroup(?OptionGroupEntity $optionGroup): void
    {
        $this->optionGroup = $optionGroup;
    }
}
