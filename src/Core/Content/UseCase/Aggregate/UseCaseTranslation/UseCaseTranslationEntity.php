<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\UseCase\Aggregate\UseCaseTranslation;

use Lohndruckerei\Mensura\Core\Content\UseCase\UseCaseEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class UseCaseTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected string $ldMensuraUseCaseId;
    protected ?string $name = null;
    protected ?string $description = null;
    protected ?UseCaseEntity $ldMensuraUseCase = null;

    public function getLdMensuraUseCaseId(): string
    {
        return $this->ldMensuraUseCaseId;
    }

    public function setLdMensuraUseCaseId(string $ldMensuraUseCaseId): void
    {
        $this->ldMensuraUseCaseId = $ldMensuraUseCaseId;
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

    public function getLdMensuraUseCase(): ?UseCaseEntity
    {
        return $this->ldMensuraUseCase;
    }

    public function setLdMensuraUseCase(UseCaseEntity $ldMensuraUseCase): void
    {
        $this->ldMensuraUseCase = $ldMensuraUseCase;
    }
}
