<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Option\Aggregate\OptionValueTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<OptionValueTranslationEntity>
 */
class OptionValueTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OptionValueTranslationEntity::class;
    }
}
