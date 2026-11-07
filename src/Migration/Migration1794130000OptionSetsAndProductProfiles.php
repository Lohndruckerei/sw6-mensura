<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * v0.3.0: Optionssets (Konfigurationsfluss), Produktprofile (ersetzen ld_mensura_product_calculation),
 * Motiv-/Staffel-/Bestpreis-Einstellungen, Preisanteile am Material, Festformat-Werte.
 */
class Migration1794130000OptionSetsAndProductProfiles extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794130000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `ld_mensura_option_set` (
    `id` BINARY(16) NOT NULL,
    `technical_name` VARCHAR(64) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` LONGTEXT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.ld_mensura_option_set.technical_name` (`technical_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `ld_mensura_option_set_group` (
    `id` BINARY(16) NOT NULL,
    `option_set_id` BINARY(16) NOT NULL,
    `option_group_id` BINARY(16) NOT NULL,
    `position` INT NOT NULL DEFAULT 0,
    `required` TINYINT(1) NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.ld_mensura_option_set_group.set_group` (`option_set_id`, `option_group_id`),
    CONSTRAINT `fk.ld_mensura_option_set_group.option_set_id` FOREIGN KEY (`option_set_id`)
        REFERENCES `ld_mensura_option_set` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.ld_mensura_option_set_group.option_group_id` FOREIGN KEY (`option_group_id`)
        REFERENCES `ld_mensura_option_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        // Start-Set der Kalkulation, Folge-Sets an Material und Optionswert
        $this->ensureColumn($connection, 'ld_mensura_calculation', 'option_set_id', 'BINARY(16) NULL');
        $this->ensureColumn($connection, 'ld_mensura_calculation', 'motif_mode', "VARCHAR(16) NOT NULL DEFAULT 'multiply'");
        $this->ensureColumn($connection, 'ld_mensura_calculation', 'tier_scope', "VARCHAR(16) NOT NULL DEFAULT 'total'");
        $this->ensureColumn($connection, 'ld_mensura_calculation', 'best_price_mode', "VARCHAR(16) NOT NULL DEFAULT 'hint'");
        $this->ensureOptionSetForeignKey($connection, 'ld_mensura_calculation', 'option_set_id');

        $this->ensureColumn($connection, 'ld_mensura_material', 'option_set_id', 'BINARY(16) NULL');
        $this->ensureColumn($connection, 'ld_mensura_material', 'price_components', 'JSON NULL');
        $this->ensureOptionSetForeignKey($connection, 'ld_mensura_material', 'option_set_id');

        $this->ensureColumn($connection, 'ld_mensura_option_value', 'option_set_id', 'BINARY(16) NULL');
        $this->ensureColumn($connection, 'ld_mensura_option_value', 'width', 'INT NULL');
        $this->ensureColumn($connection, 'ld_mensura_option_value', 'height', 'INT NULL');
        $this->ensureOptionSetForeignKey($connection, 'ld_mensura_option_value', 'option_set_id');

        // Produktprofile ersetzen die 1:1-Verknüpfung Produkt ↔ Kalkulation (v0.2.0, nie befüllt)
        if ($this->mensuraTableExists($connection, 'ld_mensura_product_calculation')) {
            $rows = (int) $connection->fetchOne('SELECT COUNT(*) FROM `ld_mensura_product_calculation`');
            if ($rows > 0) {
                throw new \RuntimeException('ld_mensura_product_calculation enthält Daten – bitte vor dem Update auf Produktprofile übertragen.');
            }
            $connection->executeStatement('DROP TABLE `ld_mensura_product_calculation`');
        }

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `ld_mensura_product_profile` (
    `id` BINARY(16) NOT NULL,
    `product_id` BINARY(16) NOT NULL,
    `product_version_id` BINARY(16) NOT NULL,
    `type` VARCHAR(16) NOT NULL DEFAULT 'general',
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `width_min` INT NULL,
    `width_max` INT NULL,
    `height_min` INT NULL,
    `height_max` INT NULL,
    `min_quantity` INT NULL,
    `max_quantity` INT NULL,
    `minimum_price_sale` DOUBLE NULL,
    `surcharge_percent` DOUBLE NULL,
    `price_components` JSON NULL,
    `defaults` JSON NULL,
    `steps` JSON NULL,
    `template_profile_id` BINARY(16) NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.ld_mensura_product_profile.product` (`product_id`, `product_version_id`),
    CONSTRAINT `json.ld_mensura_product_profile.price_components` CHECK (JSON_VALID(`price_components`)),
    CONSTRAINT `json.ld_mensura_product_profile.defaults` CHECK (JSON_VALID(`defaults`)),
    CONSTRAINT `json.ld_mensura_product_profile.steps` CHECK (JSON_VALID(`steps`)),
    CONSTRAINT `fk.ld_mensura_product_profile.product` FOREIGN KEY (`product_id`, `product_version_id`)
        REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `ld_mensura_product_profile_material` (
    `id` BINARY(16) NOT NULL,
    `product_profile_id` BINARY(16) NOT NULL,
    `calculation_id` BINARY(16) NOT NULL,
    `material_id` BINARY(16) NULL,
    `position` INT NOT NULL DEFAULT 0,
    `surcharge_percent` DOUBLE NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `idx.ld_mensura_product_profile_material.profile` (`product_profile_id`, `position`),
    CONSTRAINT `fk.ld_mensura_product_profile_material.profile` FOREIGN KEY (`product_profile_id`)
        REFERENCES `ld_mensura_product_profile` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.ld_mensura_product_profile_material.calculation` FOREIGN KEY (`calculation_id`)
        REFERENCES `ld_mensura_calculation` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.ld_mensura_product_profile_material.material` FOREIGN KEY (`material_id`)
        REFERENCES `ld_mensura_material` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `ld_mensura_product_profile_option` (
    `id` BINARY(16) NOT NULL,
    `product_profile_id` BINARY(16) NOT NULL,
    `option_group_id` BINARY(16) NOT NULL,
    `material_id` BINARY(16) NULL,
    `mode` VARCHAR(16) NOT NULL DEFAULT 'inherit',
    `value_ids` JSON NULL,
    `default_value_id` BINARY(16) NULL,
    `position` INT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `idx.ld_mensura_product_profile_option.profile` (`product_profile_id`, `option_group_id`),
    CONSTRAINT `json.ld_mensura_product_profile_option.value_ids` CHECK (JSON_VALID(`value_ids`)),
    CONSTRAINT `fk.ld_mensura_product_profile_option.profile` FOREIGN KEY (`product_profile_id`)
        REFERENCES `ld_mensura_product_profile` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.ld_mensura_product_profile_option.group` FOREIGN KEY (`option_group_id`)
        REFERENCES `ld_mensura_option_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.ld_mensura_product_profile_option.material` FOREIGN KEY (`material_id`)
        REFERENCES `ld_mensura_material` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    private function ensureColumn(Connection $connection, string $table, string $column, string $definition): void
    {
        if (!$this->columnExists($connection, $table, $column)) {
            $connection->executeStatement(\sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s', $table, $column, $definition));
        }
    }

    private function ensureOptionSetForeignKey(Connection $connection, string $table, string $column): void
    {
        $name = \sprintf('fk.%s.%s', $table, $column);
        $exists = $connection->fetchOne(
            'SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [$table, $name],
        );
        if ($exists === false) {
            $connection->executeStatement(\sprintf(
                'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `ld_mensura_option_set` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
                $table,
                $name,
                $column,
            ));
        }
    }

    private function mensuraTableExists(Connection $connection, string $table): bool
    {
        return $connection->fetchOne(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table],
        ) !== false;
    }
}
