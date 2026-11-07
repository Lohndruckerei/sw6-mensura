<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Option;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class OptionGroupEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $technicalName;
    protected string $type;
    protected string $scope;
    protected string $displayType;
    protected ?bool $required = null;
    protected ?int $position = null;
    protected ?string $name = null;
    protected ?string $description = null;
    protected ?string $helpText = null;
    protected ?OptionValueCollection $values = null;
    protected $translations;

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function getDisplayType(): string
    {
        return $this->displayType;
    }

    public function isRequired(): bool
    {
        return (bool) $this->required;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getValues(): ?OptionValueCollection
    {
        return $this->values;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function setScope(string $scope): void
    {
        $this->scope = $scope;
    }

    public function setDisplayType(string $displayType): void
    {
        $this->displayType = $displayType;
    }

    public function setRequired(?bool $required): void
    {
        $this->required = $required;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        $this->position = $position;
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

    public function getHelpText(): ?string
    {
        return $this->helpText;
    }

    public function setHelpText(?string $helpText): void
    {
        $this->helpText = $helpText;
    }

    public function setValues(OptionValueCollection $values): void
    {
        $this->values = $values;
    }

    public function getTranslations()
    {
        return $this->translations;
    }

    public function setTranslations(\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection $translations): void
    {
        $this->translations = $translations;
    }
}
