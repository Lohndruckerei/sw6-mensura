<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\UseCase;

use Lohndruckerei\Mensura\Core\Content\AccessoryRule\AccessoryRuleCollection;
use Lohndruckerei\Mensura\Core\Content\UseCase\Aggregate\UseCaseAssignment\UseCaseAssignmentCollection;
use Lohndruckerei\Mensura\Core\Content\UseCase\Aggregate\UseCaseTranslation\UseCaseTranslationCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class UseCaseEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $technicalName;
    protected ?bool $active = null;
    protected ?int $position = null;
    protected ?string $mediaId = null;
    protected ?string $name = null;
    protected ?string $description = null;
    protected ?MediaEntity $media = null;
    protected ?UseCaseTranslationCollection $translations = null;
    protected ?UseCaseAssignmentCollection $assignments = null;
    protected ?AccessoryRuleCollection $accessoryRules = null;

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
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

    public function getMediaId(): ?string
    {
        return $this->mediaId;
    }

    public function setMediaId(?string $mediaId): void
    {
        $this->mediaId = $mediaId;
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

    public function getMedia(): ?MediaEntity
    {
        return $this->media;
    }

    public function setMedia(?MediaEntity $media): void
    {
        $this->media = $media;
    }

    public function getTranslations(): ?UseCaseTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(UseCaseTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getAssignments(): ?UseCaseAssignmentCollection
    {
        return $this->assignments;
    }

    public function setAssignments(UseCaseAssignmentCollection $assignments): void
    {
        $this->assignments = $assignments;
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
