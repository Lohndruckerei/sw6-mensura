<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Anzeige-Schalter (Freigabe 07.11.2026, Fokus auf den Gesamtpreis):
 * - Produktprofil `display` (JSON, überschreibt die Plugin-Einstellungen showPriceHints/showArea/showPerSqm/showPerPiece/showFromPrice)
 * - Optionsgruppe `show_price_hint` (Aufpreise der Werte im Shop anzeigen, Standard ja)
 */
class Migration1794170000DisplaySettings extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794170000;
    }

    public function update(Connection $connection): void
    {
        if (!$this->columnExists($connection, 'ld_mensura_product_profile', 'display')) {
            $connection->executeStatement('ALTER TABLE `ld_mensura_product_profile` ADD COLUMN `display` JSON NULL, ADD CONSTRAINT `json.ld_mensura_product_profile.display` CHECK (JSON_VALID(`display`))');
        }
        if (!$this->columnExists($connection, 'ld_mensura_option_group', 'show_price_hint')) {
            $connection->executeStatement('ALTER TABLE `ld_mensura_option_group` ADD COLUMN `show_price_hint` TINYINT(1) NOT NULL DEFAULT 1 AFTER `required`');
        }
    }
}
