<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Service\Integration;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Andocken an den LieferLotse (INTEGRATION.md Abschnitt 4), ohne harte Abhängigkeit: Das Ereignis wird nur als
 * Klassenname (Text) abonniert. Ist LieferLotse nicht installiert, wird es nie ausgelöst; Mensura nutzt keine
 * LieferLotse-Klasse direkt, nur deren öffentliche Methoden am übergebenen Objekt.
 *
 * Je Position einer Mensura-Bestellposition:
 * - `attributes`: normalisierte Konfiguration (ohne Preise) für XML/JSON/CSV
 * - `supplierProductNumber`: aus den Lieferantencodes für GENAU diesen Lieferanten (Schnappschuss), sonst bleibt die
 *   Zuordnung aus dem LieferLotse
 */
class SupplierOrderSubscriber implements EventSubscriberInterface
{
    public const EVENT = 'Lohndruckerei\LieferLotse\Event\SupplierOrderBuiltEvent';

    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [self::EVENT => 'onSupplierOrderBuilt'];
    }

    public function onSupplierOrderBuilt(object $event): void
    {
        if (!method_exists($event, 'getSupplierOrder')) {
            return;
        }
        $supplierOrder = $event->getSupplierOrder();
        $supplierId = (string) $supplierOrder->getSupplier()->getId();
        $items = $supplierOrder->getItems();

        $ids = [];
        foreach ($items as $item) {
            if (Uuid::isValid((string) $item->getOrderLineItemId())) {
                $ids[] = (string) $item->getOrderLineItemId();
            }
        }
        if ($ids === []) {
            return;
        }

        $rows = $this->connection->fetchAllAssociativeIndexed(
            'SELECT LOWER(HEX(`order_line_item_id`)) AS id, `configuration`, `breakdown`
             FROM `ld_mensura_order_line_item` WHERE `order_line_item_id` IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList(array_values(array_unique($ids)))],
            ['ids' => ArrayParameterType::BINARY],
        );

        foreach ($items as $item) {
            $row = $rows[strtolower((string) $item->getOrderLineItemId())] ?? null;
            if ($row === null) {
                continue;
            }
            $configuration = json_decode((string) $row['configuration'], true);
            if (\is_array($configuration) && method_exists($item, 'setAttributes')) {
                unset($configuration['productId']);
                // LieferLotse filtert Preisschlüssel zusätzlich; die Konfiguration enthält keine Preise
                $item->setAttributes(['mensura' => $configuration]);
            }
            $breakdown = json_decode((string) $row['breakdown'], true);
            $number = \is_array($breakdown) ? ($breakdown['supplierProductNumbers'][$supplierId] ?? null) : null;
            if (\is_string($number) && $number !== '' && method_exists($item, 'setSupplierProductNumber')) {
                $item->setSupplierProductNumber($number);
            }
        }
    }
}
