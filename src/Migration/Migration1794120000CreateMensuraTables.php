<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Datenmodell Mensura (siehe docs/KONZEPT.md, Abschnitt 3, und docs/INTEGRATION.md).
 * Preise netto als DOUBLE; Preisanteile, Staffeln und Lieferantencodes als JSON.
 */
class Migration1794120000CreateMensuraTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794120000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `ld_mensura_calculation` (
    `id` BINARY(16) NOT NULL,
    `technical_name` VARCHAR(64) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` LONGTEXT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `unit` VARCHAR(16) NOT NULL DEFAULT 'area',
    `format_mode` VARCHAR(16) NOT NULL DEFAULT 'free',
    `fixed_formats` JSON NULL,
    `width_min` INT NULL,
    `width_max` INT NULL,
    `height_min` INT NULL,
    `height_max` INT NULL,
    `allow_rotation` TINYINT(1) NOT NULL DEFAULT 1,
    `min_area` DOUBLE NULL,
    `area_rounding` DOUBLE NULL,
    `max_motifs` INT NULL,
    `max_quantity` INT NULL,
    `price_components` JSON NULL,
    `minimum_price_purchase` DOUBLE NULL,
    `minimum_price_sale` DOUBLE NULL,
    `tier_basis` VARCHAR(16) NOT NULL DEFAULT 'none',
    `tiers` JSON NULL,
    `price_rounding` DOUBLE NULL,
    `formula` LONGTEXT NULL,
    `sale_rule` VARCHAR(16) NOT NULL DEFAULT 'margin',
    `sale_rule_value` DOUBLE NULL,
    `margin_min` DOUBLE NULL,
    `margin_max` DOUBLE NULL,
    `supplier_id` BINARY(16) NULL,
    `supplier_codes` JSON NULL,
    `custom_fields` JSON NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.ld_mensura_calculation.technical_name` (`technical_name`),
    CONSTRAINT `json.ld_mensura_calculation.fixed_formats` CHECK (JSON_VALID(`fixed_formats`)),
    CONSTRAINT `json.ld_mensura_calculation.price_components` CHECK (JSON_VALID(`price_components`)),
    CONSTRAINT `json.ld_mensura_calculation.tiers` CHECK (JSON_VALID(`tiers`)),
    CONSTRAINT `json.ld_mensura_calculation.supplier_codes` CHECK (JSON_VALID(`supplier_codes`)),
    CONSTRAINT `json.ld_mensura_calculation.custom_fields` CHECK (JSON_VALID(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `ld_mensura_material` (
    `id` BINARY(16) NOT NULL,
    `technical_name` VARCHAR(64) NOT NULL,
    `code` VARCHAR(64) NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `position` INT NOT NULL DEFAULT 0,
    `price_purchase` DOUBLE NULL,
    `price_sale` DOUBLE NULL,
    `minimum_price_purchase` DOUBLE NULL,
    `minimum_price_sale` DOUBLE NULL,
    `tier_basis` VARCHAR(16) NULL,
    `tiers` JSON NULL,
    `sheet_width` INT NULL,
    `sheet_height` INT NULL,
    `width_max` INT NULL,
    `height_max` INT NULL,
    `weight_per_sqm` DOUBLE NULL,
    `properties` JSON NULL,
    `supplier_id` BINARY(16) NULL,
    `supplier_codes` JSON NULL,
    `media_id` BINARY(16) NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.ld_mensura_material.technical_name` (`technical_name`),
    CONSTRAINT `json.ld_mensura_material.tiers` CHECK (JSON_VALID(`tiers`)),
    CONSTRAINT `json.ld_mensura_material.properties` CHECK (JSON_VALID(`properties`)),
    CONSTRAINT `json.ld_mensura_material.supplier_codes` CHECK (JSON_VALID(`supplier_codes`)),
    CONSTRAINT `fk.ld_mensura_material.media_id` FOREIGN KEY (`media_id`)
        REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $this->createTranslationTable($connection, 'ld_mensura_material', ', `description` LONGTEXT NULL');

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `ld_mensura_calculation_material` (
    `calculation_id` BINARY(16) NOT NULL,
    `material_id` BINARY(16) NOT NULL,
    `created_at` DATETIME(3) NOT NULL,
    PRIMARY KEY (`calculation_id`, `material_id`),
    CONSTRAINT `fk.ld_mensura_calculation_material.calculation_id` FOREIGN KEY (`calculation_id`)
        REFERENCES `ld_mensura_calculation` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.ld_mensura_calculation_material.material_id` FOREIGN KEY (`material_id`)
        REFERENCES `ld_mensura_material` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `ld_mensura_option_group` (
    `id` BINARY(16) NOT NULL,
    `technical_name` VARCHAR(64) NOT NULL,
    `type` VARCHAR(16) NOT NULL DEFAULT 'standard',
    `scope` VARCHAR(16) NOT NULL DEFAULT 'position',
    `display_type` VARCHAR(16) NOT NULL DEFAULT 'select',
    `required` TINYINT(1) NOT NULL DEFAULT 1,
    `position` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.ld_mensura_option_group.technical_name` (`technical_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $this->createTranslationTable($connection, 'ld_mensura_option_group', ', `description` LONGTEXT NULL, `help_text` LONGTEXT NULL');

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `ld_mensura_option_value` (
    `id` BINARY(16) NOT NULL,
    `option_group_id` BINARY(16) NOT NULL,
    `technical_name` VARCHAR(64) NOT NULL,
    `code` VARCHAR(64) NULL,
    `position` INT NOT NULL DEFAULT 0,
    `width_add` INT NOT NULL DEFAULT 0,
    `height_add` INT NOT NULL DEFAULT 0,
    `width_factor` DOUBLE NOT NULL DEFAULT 1,
    `height_factor` DOUBLE NOT NULL DEFAULT 1,
    `delivery_days` INT NULL,
    `exclusions` JSON NULL,
    `supplier_codes` JSON NULL,
    `media_id` BINARY(16) NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.ld_mensura_option_value.group_technical_name` (`option_group_id`, `technical_name`),
    CONSTRAINT `json.ld_mensura_option_value.exclusions` CHECK (JSON_VALID(`exclusions`)),
    CONSTRAINT `json.ld_mensura_option_value.supplier_codes` CHECK (JSON_VALID(`supplier_codes`)),
    CONSTRAINT `fk.ld_mensura_option_value.option_group_id` FOREIGN KEY (`option_group_id`)
        REFERENCES `ld_mensura_option_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.ld_mensura_option_value.media_id` FOREIGN KEY (`media_id`)
        REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $this->createTranslationTable($connection, 'ld_mensura_option_value', ', `description` LONGTEXT NULL');

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `ld_mensura_calculation_option_value` (
    `id` BINARY(16) NOT NULL,
    `calculation_id` BINARY(16) NOT NULL,
    `option_value_id` BINARY(16) NOT NULL,
    `material_id` BINARY(16) NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `position` INT NOT NULL DEFAULT 0,
    `price_components` JSON NULL,
    `tiers` JSON NULL,
    `conditions` JSON NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `idx.ld_mensura_calculation_option_value.lookup` (`calculation_id`, `option_value_id`, `material_id`),
    CONSTRAINT `json.ld_mensura_calculation_option_value.price_components` CHECK (JSON_VALID(`price_components`)),
    CONSTRAINT `json.ld_mensura_calculation_option_value.tiers` CHECK (JSON_VALID(`tiers`)),
    CONSTRAINT `json.ld_mensura_calculation_option_value.conditions` CHECK (JSON_VALID(`conditions`)),
    CONSTRAINT `fk.ld_mensura_calculation_option_value.calculation_id` FOREIGN KEY (`calculation_id`)
        REFERENCES `ld_mensura_calculation` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.ld_mensura_calculation_option_value.option_value_id` FOREIGN KEY (`option_value_id`)
        REFERENCES `ld_mensura_option_value` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.ld_mensura_calculation_option_value.material_id` FOREIGN KEY (`material_id`)
        REFERENCES `ld_mensura_material` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `ld_mensura_customer_group_rule` (
    `id` BINARY(16) NOT NULL,
    `calculation_id` BINARY(16) NULL,
    `customer_group_id` BINARY(16) NOT NULL,
    `surcharge_percent` DOUBLE NOT NULL DEFAULT 0,
    `price_rounding` DOUBLE NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `idx.ld_mensura_customer_group_rule.lookup` (`customer_group_id`, `calculation_id`),
    CONSTRAINT `fk.ld_mensura_customer_group_rule.calculation_id` FOREIGN KEY (`calculation_id`)
        REFERENCES `ld_mensura_calculation` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.ld_mensura_customer_group_rule.customer_group_id` FOREIGN KEY (`customer_group_id`)
        REFERENCES `customer_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `ld_mensura_product_calculation` (
    `id` BINARY(16) NOT NULL,
    `product_id` BINARY(16) NOT NULL,
    `product_version_id` BINARY(16) NOT NULL,
    `calculation_id` BINARY(16) NOT NULL,
    `fixed_material_id` BINARY(16) NULL,
    `material_ids` JSON NULL,
    `option_value_ids` JSON NULL,
    `defaults` JSON NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.ld_mensura_product_calculation.product` (`product_id`, `product_version_id`),
    CONSTRAINT `json.ld_mensura_product_calculation.material_ids` CHECK (JSON_VALID(`material_ids`)),
    CONSTRAINT `json.ld_mensura_product_calculation.option_value_ids` CHECK (JSON_VALID(`option_value_ids`)),
    CONSTRAINT `json.ld_mensura_product_calculation.defaults` CHECK (JSON_VALID(`defaults`)),
    CONSTRAINT `fk.ld_mensura_product_calculation.product` FOREIGN KEY (`product_id`, `product_version_id`)
        REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.ld_mensura_product_calculation.calculation_id` FOREIGN KEY (`calculation_id`)
        REFERENCES `ld_mensura_calculation` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.ld_mensura_product_calculation.fixed_material_id` FOREIGN KEY (`fixed_material_id`)
        REFERENCES `ld_mensura_material` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `ld_mensura_order_line_item` (
    `id` BINARY(16) NOT NULL,
    `order_line_item_id` BINARY(16) NOT NULL,
    `order_line_item_version_id` BINARY(16) NOT NULL,
    `schema_version` INT NOT NULL DEFAULT 1,
    `calculation_id` BINARY(16) NULL,
    `calculation_technical_name` VARCHAR(64) NULL,
    `material_id` BINARY(16) NULL,
    `supplier_id` BINARY(16) NULL,
    `supplier_product_number` VARCHAR(255) NULL,
    `configuration` JSON NULL,
    `breakdown` JSON NULL,
    `purchase_total` DOUBLE NULL,
    `sale_total` DOUBLE NULL,
    `customer_group_id` BINARY(16) NULL,
    `artwork` JSON NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.ld_mensura_order_line_item.line_item` (`order_line_item_id`, `order_line_item_version_id`),
    KEY `idx.ld_mensura_order_line_item.supplier_id` (`supplier_id`),
    CONSTRAINT `json.ld_mensura_order_line_item.configuration` CHECK (JSON_VALID(`configuration`)),
    CONSTRAINT `json.ld_mensura_order_line_item.breakdown` CHECK (JSON_VALID(`breakdown`)),
    CONSTRAINT `json.ld_mensura_order_line_item.artwork` CHECK (JSON_VALID(`artwork`)),
    CONSTRAINT `fk.ld_mensura_order_line_item.order_line_item` FOREIGN KEY (`order_line_item_id`, `order_line_item_version_id`)
        REFERENCES `order_line_item` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
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
