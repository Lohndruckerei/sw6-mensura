<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Logistik-Schnappschuss je Bestellposition (KONZEPT 3d.5, INTEGRATION.md 7.1): Logistikanteil und
 * Produktionszeit-Aufschlag im Endpreis getrennt, für Vectura. Eigene Tabelle mit eigenem Recht
 * (`ld_mensura.logistics`), damit Rollen mit `ld_mensura.orders` den unsichtbaren Anteil nicht sehen.
 */
class Migration1794190000OrderLineItemLogistics extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794190000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `ld_mensura_order_line_item_logistics` (
    `id` BINARY(16) NOT NULL,
    `order_line_item_id` BINARY(16) NOT NULL,
    `order_line_item_version_id` BINARY(16) NOT NULL,
    `schema_version` INT NOT NULL DEFAULT 1,
    `logistics_sale` DOUBLE NULL,
    `production_time_sale` DOUBLE NULL,
    `share_percent` DOUBLE NULL,
    `goods_contribution_margin` DOUBLE NULL,
    `lines` JSON NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.ld_mensura_oli_logistics.line_item` (`order_line_item_id`, `order_line_item_version_id`),
    CONSTRAINT `json.ld_mensura_oli_logistics.lines` CHECK (JSON_VALID(`lines`)),
    CONSTRAINT `fk.ld_mensura_oli_logistics.order_line_item` FOREIGN KEY (`order_line_item_id`, `order_line_item_version_id`)
        REFERENCES `order_line_item` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }
}
