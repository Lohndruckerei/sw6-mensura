<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Core\Content\Option\Aggregate\OptionGroupTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<OptionGroupTranslationEntity>
 */
class OptionGroupTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OptionGroupTranslationEntity::class;
    }
}
