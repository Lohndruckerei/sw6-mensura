<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Vorauswahl je Optionsgruppe (Wunsch des Benutzers 08.11.2026, docs/contract/README.md „Vorauswahl“):
 * - Kalkulation `preselect` (JSON {optionGroupId: false}; fehlt = vorauswählen)
 * - Profilregel `preselect` (NULL = wie Kalkulation, 1 = vorauswählen, 0 = keine Vorauswahl)
 */
class Migration1794180000Preselect extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794180000;
    }

    public function update(Connection $connection): void
    {
        if (!$this->columnExists($connection, 'ld_mensura_calculation', 'preselect')) {
            $connection->executeStatement('ALTER TABLE `ld_mensura_calculation` ADD COLUMN `preselect` JSON NULL, ADD CONSTRAINT `json.ld_mensura_calculation.preselect` CHECK (JSON_VALID(`preselect`))');
        }
        if (!$this->columnExists($connection, 'ld_mensura_product_profile_option', 'preselect')) {
            $connection->executeStatement('ALTER TABLE `ld_mensura_product_profile_option` ADD COLUMN `preselect` TINYINT(1) NULL AFTER `default_value_id`');
        }
    }
}
