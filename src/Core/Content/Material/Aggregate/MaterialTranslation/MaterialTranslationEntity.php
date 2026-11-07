<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Material\Aggregate\MaterialTranslation;

use Lohndruckerei\Mensura\Core\Content\Material\MaterialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class MaterialTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected string $ldMensuraMaterialId;
    protected ?string $name = null;
    protected ?string $description = null;
    protected ?MaterialEntity $ldMensuraMaterial = null;
    protected ?array $badges = null;

    public function getLdMensuraMaterialId(): string
    {
        return $this->ldMensuraMaterialId;
    }

    public function setLdMensuraMaterialId(string $ldMensuraMaterialId): void
    {
        $this->ldMensuraMaterialId = $ldMensuraMaterialId;
    }

    public function getLdMensuraMaterial(): ?MaterialEntity
    {
        return $this->ldMensuraMaterial;
    }

    public function setLdMensuraMaterial(MaterialEntity $ldMensuraMaterial): void
    {
        $this->ldMensuraMaterial = $ldMensuraMaterial;
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
