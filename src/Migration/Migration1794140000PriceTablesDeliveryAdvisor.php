<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * v0.4.0 (docs/KONZEPT.md Fahrplan 2b, docs/PRODUKTBEISPIELE.md Lücken 1, 2, 9):
 * - Preistabellen (Flyer, Broschüren): `ld_mensura_price_table` + `_row`
 * - Einrichtepauschale EK/VK und Mengenstufen der Preismatrix an der Kalkulation
 * - Lieferzeit-Verfügbarkeitsregeln am Optionswert (`availability`)
 * - Badges je Material und Optionswert (übersetzt)
 * - Darstellung (`layout`) und Mengenstufen am Produktprofil
 * - Produktberater: Merkmal-Katalog + Merkmalswerte, Anwendungsfälle, Zubehörregeln (INTEGRATION.md Abschnitt 6)
 *
 * Getrennte EK/VK-Staffeln und der Preisanteil `base_area` brauchen keine neue Spalte (JSON in `tiers`
 * bzw. `price_components`).
 */
class Migration1794140000PriceTablesDeliveryAdvisor extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794140000;
    }

    public function update(Connection $connection): void
    {
        // ---- Preistabellen -------------------------------------------------------------------------
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `ld_mensura_price_table` (
    `id` BINARY(16) NOT NULL,
    `calculation_id` BINARY(16) NOT NULL,
    `material_id` BINARY(16) NULL,
    `name` VARCHAR(255) NULL,
    `option_value_ids` JSON NULL,
    `price_type` VARCHAR(16) NOT NULL DEFAULT 'piece',
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `position` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `idx.ld_mensura_price_table.lookup` (`calculation_id`, `material_id`),
    CONSTRAINT `json.ld_mensura_price_table.option_value_ids` CHECK (JSON_VALID(`option_value_ids`)),
    CONSTRAINT `fk.ld_mensura_price_table.calculation_id` FOREIGN KEY (`calculation_id`)
        REFERENCES `ld_mensura_calculation` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.ld_mensura_price_table.material_id` FOREIGN KEY (`material_id`)
        REFERENCES `ld_mensura_material` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `ld_mensura_price_table_row` (
    `id` BINARY(16) NOT NULL,
    `price_table_id` BINARY(16) NOT NULL,
    `quantity` INT NOT NULL,
    `price_purchase` DOUBLE NULL,
    `price_sale` DOUBLE NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.ld_mensura_price_table_row.quantity` (`price_table_id`, `quantity`),
    CONSTRAINT `fk.ld_mensura_price_table_row.price_table_id` FOREIGN KEY (`price_table_id`)
        REFERENCES `ld_mensura_price_table` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        // ---- Neue Spalten an bestehenden Tabellen ------------------------------------------------------
        $this->ensureColumn($connection, 'ld_mensura_calculation', 'setup_price_purchase', 'DOUBLE NULL');
        $this->ensureColumn($connection, 'ld_mensura_calculation', 'setup_price_sale', 'DOUBLE NULL');
        $this->ensureColumn($connection, 'ld_mensura_calculation', 'quantity_steps', 'JSON NULL');

        $this->ensureColumn($connection, 'ld_mensura_option_value', 'availability', 'JSON NULL');

        $this->ensureColumn($connection, 'ld_mensura_material_translation', 'badges', 'JSON NULL');
        $this->ensureColumn($connection, 'ld_mensura_option_value_translation', 'badges', 'JSON NULL');

        $this->ensureColumn($connection, 'ld_mensura_product_profile', 'layout', 'VARCHAR(16) NULL');
        $this->ensureColumn($connection, 'ld_mensura_product_profile', 'quantity_steps', 'JSON NULL');

        // ---- Produktberater: Merkmale -------------------------------------------------------------
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `ld_mensura_feature` (
    `id` BINARY(16) NOT NULL,
    `technical_name` VARCHAR(64) NOT NULL,
    `type` VARCHAR(16) NOT NULL DEFAULT 'bool',
    `feature_group` VARCHAR(32) NULL,
    `unit` VARCHAR(16) NULL,
    `choices` JSON NULL,
    `icon` VARCHAR(64) NULL,
    `position` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.ld_mensura_feature.technical_name` (`technical_name`),
    CONSTRAINT `json.ld_mensura_feature.choices` CHECK (JSON_VALID(`choices`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
        $this->createTranslationTable($connection, 'ld_mensura_feature', ', `help_text` LONGTEXT NULL');

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `ld_mensura_feature_value` (
    `id` BINARY(16) NOT NULL,
    `feature_id` BINARY(16) NOT NULL,
    `material_id` BINARY(16) NULL,
    `option_value_id` BINARY(16) NULL,
    `product_profile_id` BINARY(16) NULL,
    `value` JSON NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `idx.ld_mensura_feature_value.feature` (`feature_id`),
    CONSTRAINT `json.ld_mensura_feature_value.value` CHECK (JSON_VALID(`value`)),
    CONSTRAINT `fk.ld_mensura_feature_value.feature_id` FOREIGN KEY (`feature_id`)
        REFERENCES `ld_mensura_feature` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.ld_mensura_feature_value.material_id` FOREIGN KEY (`material_id`)
        REFERENCES `ld_mensura_material` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.ld_mensura_feature_value.option_value_id` FOREIGN KEY (`option_value_id`)
        REFERENCES `ld_mensura_option_value` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.ld_mensura_feature_value.product_profile_id` FOREIGN KEY (`product_profile_id`)
        REFERENCES `ld_mensura_product_profile` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        // ---- Produktberater: Anwendungsfälle ---------------------------------------------------------
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `ld_mensura_use_case` (
    `id` BINARY(16) NOT NULL,
    `technical_name` VARCHAR(64) NOT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `position` INT NOT NULL DEFAULT 0,
    `media_id` BINARY(16) NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.ld_mensura_use_case.technical_name` (`technical_name`),
    CONSTRAINT `fk.ld_mensura_use_case.media_id` FOREIGN KEY (`media_id`)
        REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
        $this->createTranslationTable($connection, 'ld_mensura_use_case', ', `description` LONGTEXT NULL');

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `ld_mensura_use_case_assignment` (
    `id` BINARY(16) NOT NULL,
    `use_case_id` BINARY(16) NOT NULL,
    `product_profile_id` BINARY(16) NULL,
    `material_id` BINARY(16) NULL,
    `suitability` INT NOT NULL DEFAULT 3,
    `preset` JSON NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `idx.ld_mensura_use_case_assignment.use_case` (`use_case_id`),
    CONSTRAINT `json.ld_mensura_use_case_assignment.preset` CHECK (JSON_VALID(`preset`)),
    CONSTRAINT `fk.ld_mensura_use_case_assignment.use_case_id` FOREIGN KEY (`use_case_id`)
        REFERENCES `ld_mensura_use_case` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.ld_mensura_use_case_assignment.profile_id` FOREIGN KEY (`product_profile_id`)
        REFERENCES `ld_mensura_product_profile` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.ld_mensura_use_case_assignment.material_id` FOREIGN KEY (`material_id`)
        REFERENCES `ld_mensura_material` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        // ---- Produktberater: Zubehörregeln ---------------------------------------------------------
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `ld_mensura_accessory_rule` (
    `id` BINARY(16) NOT NULL,
    `trigger_type` VARCHAR(16) NOT NULL DEFAULT 'material',
    `material_id` BINARY(16) NULL,
    `option_value_id` BINARY(16) NULL,
    `use_case_id` BINARY(16) NULL,
    `feature_id` BINARY(16) NULL,
    `feature_condition` JSON NULL,
    `product_id` BINARY(16) NOT NULL,
    `product_version_id` BINARY(16) NOT NULL,
    `quantity_rule` VARCHAR(16) NOT NULL DEFAULT 'fixed',
    `quantity_factor` DOUBLE NOT NULL DEFAULT 1,
    `level` VARCHAR(16) NOT NULL DEFAULT 'recommended',
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `position` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `json.ld_mensura_accessory_rule.feature_condition` CHECK (JSON_VALID(`feature_condition`)),
    CONSTRAINT `fk.ld_mensura_accessory_rule.material_id` FOREIGN KEY (`material_id`)
        REFERENCES `ld_mensura_material` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.ld_mensura_accessory_rule.option_value_id` FOREIGN KEY (`option_value_id`)
        REFERENCES `ld_mensura_option_value` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.ld_mensura_accessory_rule.use_case_id` FOREIGN KEY (`use_case_id`)
        REFERENCES `ld_mensura_use_case` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.ld_mensura_accessory_rule.feature_id` FOREIGN KEY (`feature_id`)
        REFERENCES `ld_mensura_feature` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.ld_mensura_accessory_rule.product` FOREIGN KEY (`product_id`, `product_version_id`)
        REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
        $this->createTranslationTable($connection, 'ld_mensura_accessory_rule', ', `reason` LONGTEXT NULL');
    }

    private function ensureColumn(Connection $connection, string $table, string $column, string $definition): void
    {
        if (!$this->columnExists($connection, $table, $column)) {
            $connection->executeStatement(\sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s', $table, $column, $definition));
        }
    }

    private function createTranslationTable(Connection $connection, string $parent, string $extraColumns): void
    {
        $connection->executeStatement(\sprintf(<<<'SQL'
CREATE TABLE IF NOT EXISTS `%1$s_translation` (
    `%1$s_id` BINARY(16) NOT NULL,
    `language_id` BINARY(16) NOT NULL,
    `name` VARCHAR(255) NULL%2$s,
    `custom_fields` JSON NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`%1$s_id`, `language_id`),
    CONSTRAINT `json.%1$s_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.%1$s_translation.parent` FOREIGN KEY (`%1$s_id`)
        REFERENCES `%1$s` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.%1$s_translation.language_id` FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, $parent, $extraColumns));
    }
}
