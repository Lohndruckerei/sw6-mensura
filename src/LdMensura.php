<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

/**
 * Mensura – Produkt-Konfigurator und Flächen-/Preiskalkulation der Lohndruckerei.
 *
 * Konzept: docs/KONZEPT.md · Schnittstellen zu LieferLotse und künftigen Plugins: docs/INTEGRATION.md
 */
class LdMensura extends Plugin
{
    /**
     * Reihenfolge: abhängige Tabellen zuerst.
     */
    public const TABLES = [
        'ld_mensura_order_line_item',
        'ld_mensura_product_calculation',
        'ld_mensura_customer_group_rule',
        'ld_mensura_calculation_option_value',
        'ld_mensura_option_value_translation',
        'ld_mensura_option_value',
        'ld_mensura_option_group_translation',
        'ld_mensura_option_group',
        'ld_mensura_calculation_material',
        'ld_mensura_material_translation',
        'ld_mensura_material',
        'ld_mensura_calculation',
    ];

    /**
     * Ohne „Daten behalten“ werden alle Mensura-Tabellen entfernt – auch die Rechenweg-Schnappschüsse
     * der Bestellungen (Bestellungen selbst bleiben unverändert).
     */
    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        /** @var Connection $connection */
        $connection = $this->container->get(Connection::class);

        foreach (self::TABLES as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS `' . $table . '`');
        }
    }
}
