<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Extension;

use Lohndruckerei\Mensura\Core\Content\Material\MaterialDefinition;
use Lohndruckerei\Mensura\Core\Content\Option\OptionValueDefinition;
use Lohndruckerei\Mensura\Core\Content\UseCase\UseCaseDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Rückverknüpfungen der Bilder von Materialien und Optionswerten (Bild löschen → Verweis leeren).
 */
class MediaExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToManyAssociationField('ldMensuraMaterials', MaterialDefinition::class, 'media_id'))->addFlags(new SetNullOnDelete()),
        );
        $collection->add(
            (new OneToManyAssociationField('ldMensuraOptionValues', OptionValueDefinition::class, 'media_id'))->addFlags(new SetNullOnDelete()),
        );
        $collection->add(
            (new OneToManyAssociationField('ldMensuraUseCases', UseCaseDefinition::class, 'media_id'))->addFlags(new SetNullOnDelete()),
        );
    }

    public function getEntityName(): string
    {
        return MediaDefinition::ENTITY_NAME;
    }
}
