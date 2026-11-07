<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\UseCase\Aggregate\UseCaseAssignment;

use Lohndruckerei\Mensura\Core\Content\Material\MaterialEntity;
use Lohndruckerei\Mensura\Core\Content\ProductProfile\ProductProfileEntity;
use Lohndruckerei\Mensura\Core\Content\UseCase\UseCaseEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class UseCaseAssignmentEntity extends Entity
{
    use EntityIdTrait;

    protected string $useCaseId;
    protected ?string $productProfileId = null;
    protected ?string $materialId = null;
    protected ?int $suitability = null;
    protected ?array $preset = null;
    protected ?UseCaseEntity $useCase = null;
    protected ?ProductProfileEntity $productProfile = null;
    protected ?MaterialEntity $material = null;

    public function getUseCaseId(): string
    {
        return $this->useCaseId;
    }

    public function setUseCaseId(string $useCaseId): void
    {
        $this->useCaseId = $useCaseId;
    }

    public function getProductProfileId(): ?string
    {
        return $this->productProfileId;
    }

    public function setProductProfileId(?string $productProfileId): void
    {
        $this->productProfileId = $productProfileId;
    }

    public function getMaterialId(): ?string
    {
        return $this->materialId;
    }

    public function setMaterialId(?string $materialId): void
    {
        $this->materialId = $materialId;
    }

    public function getSuitability(): ?int
    {
        return $this->suitability;
    }

    public function setSuitability(?int $suitability): void
    {
        $this->suitability = $suitability;
    }

    public function getPreset(): ?array
    {
        return $this->preset;
    }

    public function setPreset(?array $preset): void
    {
        $this->preset = $preset;
    }

    public function getUseCase(): ?UseCaseEntity
    {
        return $this->useCase;
    }

    public function setUseCase(?UseCaseEntity $useCase): void
    {
        $this->useCase = $useCase;
    }

    public function getProductProfile(): ?ProductProfileEntity
    {
        return $this->productProfile;
    }

    public function setProductProfile(?ProductProfileEntity $productProfile): void
    {
        $this->productProfile = $productProfile;
    }

    public function getMaterial(): ?MaterialEntity
    {
        return $this->material;
    }

    public function setMaterial(?MaterialEntity $material): void
    {
        $this->material = $material;
    }
}
