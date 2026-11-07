<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Bezug von Mindestfläche und Flächenrundung (Kalkulation `areaScope`):
 * `total` = Gesamtfläche der Position (Länge × Breite × Stück), Standard laut Entscheidung vom 07.11.2026
 * (Folie: bis 1 m² gleicher Grundpreis, darüber in Schritten); `piece` = je Stück wie bisher.
 */
class Migration1794150000AreaScope extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794150000;
    }

    public function update(Connection $connection): void
    {
        if (!$this->columnExists($connection, 'ld_mensura_calculation', 'area_scope')) {
            $connection->executeStatement("ALTER TABLE `ld_mensura_calculation` ADD COLUMN `area_scope` VARCHAR(16) NOT NULL DEFAULT 'total' AFTER `area_rounding`");
        }
    }
}
