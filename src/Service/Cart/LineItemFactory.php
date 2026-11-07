<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Service\Cart;

use Lohndruckerei\Mensura\Service\Calculation\CalculationResult;
use Lohndruckerei\Mensura\Service\Calculation\Catalog;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Baut die Warenkorbposition einer Konfiguration: normale Produktposition (`type = product`, referenziert das
 * Shopware-Produkt), Menge = Gesamtmenge der Konfiguration, Payload `ldMensura` laut INTEGRATION.md Abschnitt 1
 * plus `configuration` (normalisierte Vertragsform, daraus rechnet der CartProcessor neu) und `ldDeliveryDate`.
 * Keine Preise im Payload.
 */
final class LineItemFactory
{
    public const PAYLOAD_KEY = 'ldMensura';

    public static function create(CalculationResult $result, Catalog $catalog): LineItem
    {
        $lineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE, $catalog->productId(), self::quantity($result, $catalog));
        $lineItem->setStackable(true);
        $lineItem->setRemovable(true);
        self::applyResult($lineItem, $result, $catalog);

        return $lineItem;
    }

    /** Payload und Menge aus einer (Neu-)Berechnung setzen. */
    public static function applyResult(LineItem $lineItem, CalculationResult $result, Catalog $catalog): void
    {
        $quantity = self::quantity($result, $catalog);
        if ($lineItem->getQuantity() !== $quantity) {
            $lineItem->setQuantity($quantity);
        }
        $lineItem->setPayloadValue(self::PAYLOAD_KEY, self::payload($result, $catalog));
        $deliveryDate = self::deliveryDate($result, $catalog);
        if ($deliveryDate !== null) {
            $lineItem->setPayloadValue('ldDeliveryDate', $deliveryDate);
        }
    }

    /** @return array<string, mixed> */
    public static function payload(CalculationResult $result, Catalog $catalog): array
    {
        $config = $result->getConfiguration();
        $api = $result->toStoreApi();
        $calculation = $catalog->calculation($config->calculationId) ?? [];
        $material = $catalog->material($config->materialId) ?? [];
        $options = [];
        $codes = [];
        foreach ($config->options as $groupId => $valueId) {
            $group = $catalog->group($groupId) ?? [];
            $value = $catalog->value($valueId) ?? [];
            $options[] = [
                'group' => $group['technicalName'] ?? null,
                'groupName' => $group['name'] ?? null,
                'value' => $value['technicalName'] ?? null,
                'valueName' => $value['name'] ?? null,
            ];
            if (!empty($value['code'])) {
                $codes[] = $value['code'];
            }
        }
        $sku = implode('-', array_filter([
            $material['code'] ?? $material['technicalName'] ?? null,
            $config->width . 'x' . $config->height,
            ...$codes,
        ]));

        return [
            'schema' => 1,
            'calculation' => $calculation['technicalName'] ?? null,
            'material' => ['id' => $config->materialId, 'key' => $material['technicalName'] ?? null, 'name' => $material['name'] ?? null],
            'format' => ['width' => $config->width, 'height' => $config->height, 'rotated' => $config->rotated, 'unit' => 'mm'],
            // piece/total = Länge × Breite (× Stück) inkl. Formatzugaben; billed = abgerechnet (Mindestfläche, Schrittweite)
            'area' => [
                'piece' => $result->getAreas()['piece'] ?? null,
                'total' => $result->getAreas()['total'] ?? null,
                'billed' => $result->getAreas()['billed'] ?? null,
            ],
            // Gewicht in kg (Gewicht je m² des Materials × Fläche) – für ein späteres Versand-Plugin
            'weight' => $result->getWeight() !== null ? $result->getWeight() + ['unit' => 'kg'] : null,
            'motifs' => $config->motifs,
            'perMotif' => $config->perMotif,
            'options' => $options,
            'delivery' => $config->deliveryValueId !== null ? ($catalog->value($config->deliveryValueId)['technicalName'] ?? null) : null,
            'reference' => $config->reference,
            'sku' => $sku,
            'configuration' => json_decode((string) json_encode($api['configuration']), true),
        ];
    }

    private static function quantity(CalculationResult $result, Catalog $catalog): int
    {
        $config = $result->getConfiguration();
        $calculation = $catalog->calculation($config->calculationId) ?? [];

        return max(1, $config->quantity((string) ($calculation['motifMode'] ?? 'multiply')));
    }

    private static function deliveryDate(CalculationResult $result, Catalog $catalog): ?string
    {
        $value = $result->getConfiguration()->deliveryValueId;
        $technicalName = $value !== null ? ($catalog->value($value)['technicalName'] ?? null) : null;
        foreach ($result->toStoreApi()['delivery'] ?? [] as $row) {
            if ($row['value'] === $technicalName) {
                return $row['date'];
            }
        }

        return null;
    }
}
