<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Feature\Aggregate\FeatureTranslation;

use Lohndruckerei\Mensura\Core\Content\Feature\FeatureEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class FeatureTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected string $ldMensuraFeatureId;
    protected ?string $name = null;
    protected ?string $helpText = null;
    protected ?FeatureEntity $ldMensuraFeature = null;

    public function getLdMensuraFeatureId(): string
    {
        return $this->ldMensuraFeatureId;
    }

    public function setLdMensuraFeatureId(string $ldMensuraFeatureId): void
    {
        $this->ldMensuraFeatureId = $ldMensuraFeatureId;
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

    public function getLdMensuraFeature(): ?FeatureEntity
    {
        return $this->ldMensuraFeature;
    }

    public function setLdMensuraFeature(FeatureEntity $ldMensuraFeature): void
    {
        $this->ldMensuraFeature = $ldMensuraFeature;
    }
}
