<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\AccessoryRule\Aggregate\AccessoryRuleTranslation;

use Lohndruckerei\Mensura\Core\Content\AccessoryRule\AccessoryRuleEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class AccessoryRuleTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected string $ldMensuraAccessoryRuleId;
    protected ?string $name = null;
    protected ?string $reason = null;
    protected ?AccessoryRuleEntity $ldMensuraAccessoryRule = null;

    public function getLdMensuraAccessoryRuleId(): string
    {
        return $this->ldMensuraAccessoryRuleId;
    }

    public function setLdMensuraAccessoryRuleId(string $ldMensuraAccessoryRuleId): void
    {
        $this->ldMensuraAccessoryRuleId = $ldMensuraAccessoryRuleId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): void
    {
        $this->reason = $reason;
    }

    public function getLdMensuraAccessoryRule(): ?AccessoryRuleEntity
    {
        return $this->ldMensuraAccessoryRule;
    }

    public function setLdMensuraAccessoryRule(AccessoryRuleEntity $ldMensuraAccessoryRule): void
    {
        $this->ldMensuraAccessoryRule = $ldMensuraAccessoryRule;
    }
}
