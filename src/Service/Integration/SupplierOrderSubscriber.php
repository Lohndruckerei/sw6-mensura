<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Service\Integration;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Andocken an den LieferLotse (INTEGRATION.md Abschnitt 4), ohne harte Abhängigkeit: Das Ereignis wird nur als
 * Klassenname (Text) abonniert. Ist LieferLotse nicht installiert, wird es nie ausgelöst; Mensura nutzt keine
 * LieferLotse-Klasse direkt, nur deren öffentliche Methoden am übergebenen Objekt.
 *
 * Je Position einer Mensura-Bestellposition:
 * - `options`: lesbare Zeilen („Material: …“, „Format: …“, „Farbigkeit: …“) in der Korrespondenzsprache des
 *   Lieferanten (sonst Sprache der Bestellung) – diese Zeilen zeigen E-Mail-Vorlage, XML, CSV und JSON
 * - `attributes.mensura`: normalisierte Konfiguration (ohne Preise) für XML/JSON/CSV, ergänzt um `labels`
 *   (dieselben Angaben mit lesbaren Namen und Lieferantencodes)
 * - `supplierProductNumber`: aus den Lieferantencodes für GENAU diesen Lieferanten (Schnappschuss), sonst bleibt die
 *   Zuordnung aus dem LieferLotse
 */
class SupplierOrderSubscriber implements EventSubscriberInterface
{
    public const EVENT = 'Lohndruckerei\LieferLotse\Event\SupplierOrderBuiltEvent';

    private const LABELS = [
        'de' => ['material' => 'Material', 'format' => 'Format', 'quantity' => 'Auflage', 'motifs' => 'Motiv(e)', 'pieces' => 'Stk', 'rotated' => 'gedreht', 'reference' => 'Referenz des Kunden'],
        'en' => ['material' => 'Material', 'format' => 'Size', 'quantity' => 'Quantity', 'motifs' => 'design(s)', 'pieces' => 'pcs', 'rotated' => 'rotated', 'reference' => 'Customer reference'],
    ];

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
        $supplier = $supplierOrder->getSupplier();
        $supplierId = (string) $supplier->getId();
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

        // nur die Live-Version der Bestellposition (im Admin bearbeitete Bestellungen haben weitere Versionen)
        $rows = $this->connection->fetchAllAssociativeIndexed(
            'SELECT LOWER(HEX(`order_line_item_id`)) AS id, LOWER(HEX(`material_id`)) AS material_id, `configuration`, `breakdown`
             FROM `ld_mensura_order_line_item`
             WHERE `order_line_item_id` IN (:ids) AND `order_line_item_version_id` = :version',
            ['ids' => Uuid::fromHexToBytesList(array_values(array_unique($ids))), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => ArrayParameterType::BINARY],
        );
        if ($rows === []) {
            return;
        }

        $languageId = $this->languageId($supplier, $supplierOrder);
        $labels = self::LABELS[$this->languagePrefix($languageId)];

        foreach ($items as $item) {
            $row = $rows[strtolower((string) $item->getOrderLineItemId())] ?? null;
            if ($row === null) {
                continue;
            }
            $configuration = json_decode((string) $row['configuration'], true);
            $breakdown = json_decode((string) $row['breakdown'], true);
            $breakdown = \is_array($breakdown) ? $breakdown : [];

            if (\is_array($configuration)) {
                unset($configuration['productId']);
                $readable = $this->readable($configuration, (string) $row['material_id'], $languageId, $supplierId, $labels, (int) $item->getQuantity());
                if (method_exists($item, 'setOptions')) {
                    $item->setOptions($readable['lines']);
                }
                if (method_exists($item, 'setAttributes')) {
                    // LieferLotse filtert Preisschlüssel zusätzlich; Konfiguration und Labels enthalten keine Preise
                    $item->setAttributes(['mensura' => $configuration + ['labels' => $readable['labels']]]);
                }
            }

            $number = $breakdown['supplierProductNumbers'][$supplierId] ?? null;
            if (\is_string($number) && $number !== '' && method_exists($item, 'setSupplierProductNumber')) {
                $item->setSupplierProductNumber($number);
            }
        }
    }

    /**
     * Lesbare Zeilen und Labels einer Konfiguration.
     *
     * @param array<string, mixed>  $configuration
     * @param array<string, string> $labels
     *
     * @return array{lines: list<string>, labels: array<string, mixed>}
     */
    private function readable(array $configuration, string $materialId, string $languageId, string $supplierId, array $labels, int $quantity): array
    {
        $material = $this->material($materialId !== '' ? $materialId : (string) ($configuration['materialId'] ?? ''), $languageId);
        $materialCode = $this->supplierCode($material['supplierCodes'] ?? null, $supplierId);

        $width = (int) ($configuration['format']['width'] ?? 0);
        $height = (int) ($configuration['format']['height'] ?? 0);
        $format = number_format($width, 0, ',', '.') . ' × ' . number_format($height, 0, ',', '.') . ' mm'
            . (!empty($configuration['format']['rotated']) ? ' (' . $labels['rotated'] . ')' : '');
        $motifs = max(1, (int) ($configuration['motifs'] ?? 1));
        $quantityText = $motifs > 1
            ? \sprintf('%d %s × %d %s = %d %s', $motifs, $labels['motifs'], (int) ($configuration['perMotif'] ?? 0), $labels['pieces'], $quantity, $labels['pieces'])
            : \sprintf('%d %s', $quantity, $labels['pieces']);

        $selected = \is_array($configuration['options'] ?? null) ? $configuration['options'] : [];
        if (!empty($configuration['deliveryValue'])) {
            $selected += ['__delivery' => (string) $configuration['deliveryValue']];
        }
        $names = $this->optionNames($selected, $languageId);
        // Reihenfolge wie im Konfigurator (Position der Optionsgruppe), Lieferzeit zuletzt
        uksort($selected, static function (string $a, string $b) use ($selected, $names): int {
            $position = static fn (string $group) => $names[$group . '|' . $selected[$group]]['position'] ?? PHP_INT_MAX;

            return $position($a) <=> $position($b);
        });

        $lines = [
            $labels['material'] . ': ' . ($material['name'] ?? '–') . ($materialCode !== null ? ' [' . $materialCode . ']' : ''),
            $labels['format'] . ': ' . $format,
            $labels['quantity'] . ': ' . $quantityText,
        ];
        $options = [];
        foreach ($selected as $group => $value) {
            $name = $names[$group . '|' . $value] ?? null;
            $code = $this->supplierCode($name['supplierCodes'] ?? null, $supplierId);
            $groupName = $name['group'] ?? (string) $group;
            $valueName = $name['value'] ?? (string) $value;
            $lines[] = $groupName . ': ' . $valueName . ($code !== null ? ' [' . $code . ']' : '');
            $options[] = array_filter([
                'group' => $name['groupKey'] ?? (string) $group,
                'name' => $groupName,
                'value' => (string) $value,
                'valueName' => $valueName,
                'supplierCode' => $code,
            ], static fn ($v) => $v !== null);
        }
        if (!empty($configuration['reference'])) {
            $lines[] = $labels['reference'] . ': ' . $configuration['reference'];
        }

        return [
            'lines' => $lines,
            'labels' => array_filter([
                'material' => $material['name'] ?? null,
                'materialSupplierCode' => $materialCode,
                'format' => $format,
                'quantity' => $quantityText,
                'options' => $options,
            ], static fn ($v) => $v !== null && $v !== []),
        ];
    }

    /** @return array{name: ?string, supplierCodes: mixed} */
    private function material(string $materialId, string $languageId): array
    {
        if (!Uuid::isValid($materialId)) {
            return ['name' => null, 'supplierCodes' => null];
        }
        $row = $this->connection->fetchAssociative(
            'SELECT COALESCE(t.`name`, s.`name`) AS name, m.`supplier_codes` AS codes
             FROM `ld_mensura_material` m
             LEFT JOIN `ld_mensura_material_translation` t ON t.`ld_mensura_material_id` = m.`id` AND t.`language_id` = :lang
             LEFT JOIN `ld_mensura_material_translation` s ON s.`ld_mensura_material_id` = m.`id` AND s.`language_id` = :system
             WHERE m.`id` = :id',
            ['id' => Uuid::fromHexToBytes($materialId), 'lang' => Uuid::fromHexToBytes($languageId), 'system' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)],
        );

        return ['name' => $row['name'] ?? null, 'supplierCodes' => isset($row['codes']) ? json_decode((string) $row['codes'], true) : null];
    }

    /**
     * Gruppen- und Wertnamen zu den gewählten technischen Namen (`__delivery` = Wert der Lieferzeit-Gruppe).
     *
     * @param array<string, string> $selected
     *
     * @return array<string, array{group: ?string, value: ?string, groupKey: string, position: int, supplierCodes: mixed}>
     */
    private function optionNames(array $selected, string $languageId): array
    {
        if ($selected === []) {
            return [];
        }
        $rows = $this->connection->fetchAllAssociative(
            'SELECT g.`technical_name` AS group_key, g.`type` AS group_type, g.`position` AS group_position, v.`technical_name` AS value_key,
                    COALESCE(gt.`name`, gs.`name`) AS group_name, COALESCE(vt.`name`, vs.`name`) AS value_name, v.`supplier_codes` AS codes
             FROM `ld_mensura_option_value` v
             JOIN `ld_mensura_option_group` g ON g.`id` = v.`option_group_id`
             LEFT JOIN `ld_mensura_option_group_translation` gt ON gt.`ld_mensura_option_group_id` = g.`id` AND gt.`language_id` = :lang
             LEFT JOIN `ld_mensura_option_group_translation` gs ON gs.`ld_mensura_option_group_id` = g.`id` AND gs.`language_id` = :system
             LEFT JOIN `ld_mensura_option_value_translation` vt ON vt.`ld_mensura_option_value_id` = v.`id` AND vt.`language_id` = :lang
             LEFT JOIN `ld_mensura_option_value_translation` vs ON vs.`ld_mensura_option_value_id` = v.`id` AND vs.`language_id` = :system
             WHERE v.`technical_name` IN (:values)',
            [
                'lang' => Uuid::fromHexToBytes($languageId),
                'system' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'values' => array_values(array_unique(array_map('strval', $selected))),
            ],
            ['values' => ArrayParameterType::STRING],
        );

        $names = [];
        foreach ($rows as $row) {
            $entry = [
                'group' => $row['group_name'],
                'value' => $row['value_name'],
                'groupKey' => $row['group_key'],
                'position' => (int) $row['group_position'],
                'supplierCodes' => $row['codes'] !== null ? json_decode((string) $row['codes'], true) : null,
            ];
            $names[$row['group_key'] . '|' . $row['value_key']] = $entry;
            if ($row['group_type'] === 'delivery') {
                $names['__delivery|' . $row['value_key']] = $entry;
            }
        }

        return $names;
    }

    private function supplierCode(mixed $codes, string $supplierId): ?string
    {
        if (!\is_array($codes)) {
            return null;
        }
        foreach ($codes as $code) {
            if (\is_array($code) && strtolower((string) ($code['supplierId'] ?? '')) === strtolower($supplierId) && ($code['code'] ?? '') !== '') {
                return (string) $code['code'];
            }
        }

        return null;
    }

    /** Korrespondenzsprache des Lieferanten, sonst Sprache der Bestellung, sonst Systemsprache. */
    private function languageId(object $supplier, object $supplierOrder): string
    {
        $languageId = method_exists($supplier, 'getLanguageId') ? $supplier->getLanguageId() : null;
        if (!\is_string($languageId) || !Uuid::isValid($languageId)) {
            $order = method_exists($supplierOrder, 'getOrder') ? $supplierOrder->getOrder() : null;
            $languageId = $order !== null && method_exists($order, 'getLanguageId') ? $order->getLanguageId() : null;
        }

        return \is_string($languageId) && Uuid::isValid($languageId) ? $languageId : Defaults::LANGUAGE_SYSTEM;
    }

    private function languagePrefix(string $languageId): string
    {
        $code = $this->connection->fetchOne(
            'SELECT lo.`code` FROM `language` l JOIN `locale` lo ON lo.`id` = l.`locale_id` WHERE l.`id` = :id',
            ['id' => Uuid::fromHexToBytes($languageId)],
        );

        return \is_string($code) && str_starts_with($code, 'de') ? 'de' : 'en';
    }
}
