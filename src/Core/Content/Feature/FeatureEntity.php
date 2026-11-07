<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Feature;

use Lohndruckerei\Mensura\Core\Content\AccessoryRule\AccessoryRuleCollection;
use Lohndruckerei\Mensura\Core\Content\Feature\Aggregate\FeatureTranslation\FeatureTranslationCollection;
use Lohndruckerei\Mensura\Core\Content\Feature\Aggregate\FeatureValue\FeatureValueCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class FeatureEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $technicalName;
    protected string $type;
    protected ?string $featureGroup = null;
    protected ?string $unit = null;
    protected ?array $choices = null;
    protected ?string $icon = null;
    protected ?int $position = null;
    protected ?string $name = null;
    protected ?string $helpText = null;
    protected ?FeatureTranslationCollection $translations = null;
    protected ?FeatureValueCollection $values = null;
    protected ?AccessoryRuleCollection $accessoryRules = null;

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getFeatureGroup(): ?string
    {
        return $this->featureGroup;
    }

    public function setFeatureGroup(?string $featureGroup): void
    {
        $this->featureGroup = $featureGroup;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(?string $unit): void
    {
        $this->unit = $unit;
    }

    public function getChoices(): ?array
    {
        return $this->choices;
    }

    public function setChoices(?array $choices): void
    {
        $this->choices = $choices;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): void
    {
        $this->icon = $icon;
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

    public function getHelpText(): ?string
    {
        return $this->helpText;
    }

    public function setHelpText(?string $helpText): void
    {
        $this->helpText = $helpText;
    }

    public function getTranslations(): ?FeatureTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(FeatureTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getValues(): ?FeatureValueCollection
    {
        return $this->values;
    }

    public function setValues(FeatureValueCollection $values): void
    {
        $this->values = $values;
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
