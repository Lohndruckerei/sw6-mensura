<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Mindestfläche und Flächenrundung auch je Material (überschreiben die Kalkulation), z. B. Folie A ab 1 m²,
 * Folie B ab 0,5 m² in derselben Kalkulation.
 */
class Migration1794160000MaterialAreaRules extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794160000;
    }

    public function update(Connection $connection): void
    {
        if (!$this->columnExists($connection, 'ld_mensura_material', 'min_area')) {
            $connection->executeStatement('ALTER TABLE `ld_mensura_material` ADD COLUMN `min_area` DOUBLE NULL AFTER `minimum_price_sale`');
        }
        if (!$this->columnExists($connection, 'ld_mensura_material', 'area_rounding')) {
            $connection->executeStatement('ALTER TABLE `ld_mensura_material` ADD COLUMN `area_rounding` DOUBLE NULL AFTER `min_area`');
        }
    }
}
