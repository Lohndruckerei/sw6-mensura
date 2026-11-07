<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Logistikanteil (KONZEPT 3d): unsichtbarer Aufschlag im VK für die Versand- und Servicekosten der Lieferanten.
 *
 * `ld_mensura_logistics_rule` hängt an genau einem Ziel: Kalkulation (allein), Material oder Optionswert
 * (optional beschränkt auf eine Kalkulation). Änderungen legen eine neue Zeile mit `valid_from` an (Verlauf).
 * Eigene Tabelle, damit nur Rollen mit `ld_mensura.logistics` sie über die Admin-API lesen können.
 * „Genau ein Ziel“ prüft der LogisticsRuleValidator (MySQL erlaubt kein CHECK auf Spalten mit FK-CASCADE).
 */
class Migration1794180000LogisticsShare extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794180000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `ld_mensura_logistics_rule` (
    `id` BINARY(16) NOT NULL,
    `calculation_id` BINARY(16) NULL,
    `material_id` BINARY(16) NULL,
    `option_value_id` BINARY(16) NULL,
    `cost_type` VARCHAR(32) NOT NULL,
    `form` VARCHAR(16) NOT NULL,
    `value` DOUBLE NOT NULL DEFAULT 0,
    `ramp_from` INT NULL,
    `ramp_to` INT NULL,
    `threshold_basis` VARCHAR(16) NULL,
    `threshold_from` DOUBLE NULL,
    `threshold_to` DOUBLE NULL,
    `minimum_amount` DOUBLE NULL,
    `minimum_scope` VARCHAR(16) NULL,
    `valid_from` DATE NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `source` VARCHAR(16) NOT NULL DEFAULT 'manual',
    `source_reference` JSON NULL,
    `note` LONGTEXT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `idx.ld_mensura_logistics_rule.calculation` (`calculation_id`, `cost_type`, `valid_from`),
    KEY `idx.ld_mensura_logistics_rule.material` (`material_id`, `cost_type`, `valid_from`),
    KEY `idx.ld_mensura_logistics_rule.option_value` (`option_value_id`, `cost_type`, `valid_from`),
    CONSTRAINT `json.ld_mensura_logistics_rule.source_reference` CHECK (JSON_VALID(`source_reference`)),
    CONSTRAINT `chk.ld_mensura_logistics_rule.ramp` CHECK (
        (`ramp_from` IS NULL AND `ramp_to` IS NULL) OR (`ramp_from` IS NOT NULL AND `ramp_to` >= `ramp_from` + 100)
    ),
    CONSTRAINT `chk.ld_mensura_logistics_rule.threshold` CHECK (
        (`threshold_basis` IS NULL AND `threshold_from` IS NULL AND `threshold_to` IS NULL)
        OR (`threshold_basis` IS NOT NULL AND `threshold_from` IS NOT NULL AND `threshold_to` > `threshold_from`)
    ),
    CONSTRAINT `chk.ld_mensura_logistics_rule.minimum` CHECK (`minimum_scope` IS NULL OR `minimum_amount` IS NOT NULL),
    CONSTRAINT `fk.ld_mensura_logistics_rule.calculation_id` FOREIGN KEY (`calculation_id`)
        REFERENCES `ld_mensura_calculation` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.ld_mensura_logistics_rule.material_id` FOREIGN KEY (`material_id`)
        REFERENCES `ld_mensura_material` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.ld_mensura_logistics_rule.option_value_id` FOREIGN KEY (`option_value_id`)
        REFERENCES `ld_mensura_option_value` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }
}
