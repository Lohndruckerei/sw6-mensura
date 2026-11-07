<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Option\Aggregate\OptionGroupTranslation;

use Lohndruckerei\Mensura\Core\Content\Option\OptionGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class OptionGroupTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected string $ldMensuraOptionGroupId;
    protected ?string $name = null;
    protected ?string $description = null;
    protected ?string $helpText = null;
    protected ?OptionGroupEntity $ldMensuraOptionGroup = null;

    public function getLdMensuraOptionGroupId(): string
    {
        return $this->ldMensuraOptionGroupId;
    }

    public function setLdMensuraOptionGroupId(string $ldMensuraOptionGroupId): void
    {
        $this->ldMensuraOptionGroupId = $ldMensuraOptionGroupId;
    }

    public function getLdMensuraOptionGroup(): ?OptionGroupEntity
    {
        return $this->ldMensuraOptionGroup;
    }

    public function setLdMensuraOptionGroup(OptionGroupEntity $ldMensuraOptionGroup): void
    {
        $this->ldMensuraOptionGroup = $ldMensuraOptionGroup;
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

    public function getHelpText(): ?string
    {
        return $this->helpText;
    }

    public function setHelpText(?string $helpText): void
    {
        $this->helpText = $helpText;
    }
}
