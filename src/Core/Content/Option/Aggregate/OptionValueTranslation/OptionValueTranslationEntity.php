<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Option\Aggregate\OptionValueTranslation;

use Lohndruckerei\Mensura\Core\Content\Option\OptionValueEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class OptionValueTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected string $ldMensuraOptionValueId;
    protected ?string $name = null;
    protected ?string $description = null;
    protected ?OptionValueEntity $ldMensuraOptionValue = null;
    protected ?array $badges = null;

    public function getLdMensuraOptionValueId(): string
    {
        return $this->ldMensuraOptionValueId;
    }

    public function setLdMensuraOptionValueId(string $ldMensuraOptionValueId): void
    {
        $this->ldMensuraOptionValueId = $ldMensuraOptionValueId;
    }

    public function getLdMensuraOptionValue(): ?OptionValueEntity
    {
        return $this->ldMensuraOptionValue;
    }

    public function setLdMensuraOptionValue(OptionValueEntity $ldMensuraOptionValue): void
    {
        $this->ldMensuraOptionValue = $ldMensuraOptionValue;
    }

    public function getName(): ?string
    {
        return $this->name;
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

    public function getBadges(): ?array
    {
        return $this->badges;
    }

    public function setBadges(?array $badges): void
    {
        $this->badges = $badges;
    }
}
