<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Service\Security;

/**
 * Einkaufspreise (EK) serverseitig schützen (KONZEPT 3b): Welche Felder sind EK, wie werden sie beim Lesen
 * entfernt und beim Schreiben ohne Recht erhalten. Reines PHP (ohne Shopware), damit es ohne Shop testbar ist;
 * angewendet von PurchaseProtectionSubscriber.
 *
 * - Skalare EK-Felder (z. B. `pricePurchase`): beim Lesen null; Schreiben ohne Recht wird abgelehnt
 *   (Neuanlage: EK wird nicht übernommen).
 * - JSON mit EK und VK nebeneinander (`priceComponents` [{type, purchase, sale}], `tiers` alt [{from, purchase,
 *   sale}] oder neu {purchase: {...}, sale: {...}}, Schnappschuss `breakdown` {purchase, sale}): beim Lesen nur
 *   der EK-Teil entfernt; beim Schreiben ohne Recht wird der EK-Teil aus dem gespeicherten Stand übernommen,
 *   damit eine VK-Änderung keine Einkaufspreise löscht.
 */
final class PurchaseFieldGuard
{
    /** Kontext-Zustand für interne Berechnungen der Administration (EK wird gebraucht, aber nicht ausgeliefert). */
    public const STATE_INTERNAL = 'ld-mensura.purchase-internal';

    public const PRIVILEGE = 'ld_mensura.purchase';

    /**
     * Entity → [Eigenschaft → Spalte, Art]; Art: scalar | components | tiers | breakdown
     *
     * @var array<string, array<string, array{0: string, 1: string}>>
     */
    public const FIELDS = [
        'ld_mensura_material' => [
            'pricePurchase' => ['price_purchase', 'scalar'],
            'minimumPricePurchase' => ['minimum_price_purchase', 'scalar'],
            'priceComponents' => ['price_components', 'components'],
            'tiers' => ['tiers', 'tiers'],
        ],
        'ld_mensura_calculation' => [
            'minimumPricePurchase' => ['minimum_price_purchase', 'scalar'],
            'setupPricePurchase' => ['setup_price_purchase', 'scalar'],
            'priceComponents' => ['price_components', 'components'],
            'tiers' => ['tiers', 'tiers'],
        ],
        'ld_mensura_calculation_option_value' => [
            'priceComponents' => ['price_components', 'components'],
            'tiers' => ['tiers', 'tiers'],
        ],
        'ld_mensura_price_table_row' => [
            'pricePurchase' => ['price_purchase', 'scalar'],
        ],
        'ld_mensura_order_line_item' => [
            'purchaseTotal' => ['purchase_total', 'scalar'],
            'breakdown' => ['breakdown', 'breakdown'],
        ],
    ];

    /** Felder, nach denen ohne Recht nicht gefiltert, sortiert oder aggregiert werden darf. */
    private const SEARCH_BLOCKED = ['pricePurchase', 'minimumPricePurchase', 'setupPricePurchase', 'purchaseTotal'];
    private const SEARCH_BLOCKED_JSON = ['priceComponents', 'tiers', 'breakdown'];

    public static function isProtectedEntity(string $entity): bool
    {
        return isset(self::FIELDS[$entity]);
    }

    /**
     * Gelesene Werte ohne EK.
     *
     * @param array<string, mixed> $values Eigenschaft → Wert (nur vorhandene Felder)
     *
     * @return array<string, mixed>
     */
    public static function strip(string $entity, array $values): array
    {
        $out = [];
        foreach (self::FIELDS[$entity] ?? [] as $property => [, $kind]) {
            if (!\array_key_exists($property, $values)) {
                continue;
            }
            $out[$property] = self::stripValue($kind, $values[$property]);
        }

        return $out;
    }

    public static function stripValue(string $kind, mixed $value): mixed
    {
        if ($kind === 'scalar') {
            return null;
        }
        if (!\is_array($value)) {
            return $value;
        }
        if ($kind === 'components') {
            return array_map(static function ($row) {
                if (\is_array($row)) {
                    $row['purchase'] = null;
                }

                return $row;
            }, $value);
        }
        if ($kind === 'tiers') {
            if (array_is_list($value)) {
                return array_map(static function ($step) {
                    if (\is_array($step)) {
                        unset($step['purchase']);
                    }

                    return $step;
                }, $value);
            }
            unset($value['purchase']);

            return $value;
        }
        // breakdown
        unset($value['purchase'], $value['contributionMargin']);

        return $value;
    }

    /**
     * Neuer JSON-Wert ohne Recht: EK-Teil aus dem gespeicherten Wert übernehmen.
     *
     * @param mixed       $new         neuer Wert (dekodiert)
     * @param mixed       $old         gespeicherter Wert (dekodiert) oder null bei Neuanlage
     * @param string|null $legacyBasis gespeicherte Staffel-Basis (`tier_basis`) für altes Staffelformat
     */
    public static function mergeJson(string $kind, mixed $new, mixed $old, ?string $legacyBasis = null): mixed
    {
        if ($kind === 'components') {
            return self::mergeComponents($new, $old);
        }
        if ($kind === 'tiers') {
            return self::mergeTiers($new, $old, $legacyBasis);
        }

        // breakdown wird nur vom Bestellprozess geschrieben – EK aus dem Bestand
        if (!\is_array($new)) {
            return $new;
        }
        unset($new['purchase'], $new['contributionMargin']);
        if (\is_array($old)) {
            foreach (['purchase', 'contributionMargin'] as $key) {
                if (\array_key_exists($key, $old)) {
                    $new[$key] = $old[$key];
                }
            }
        }

        return $new;
    }

    /**
     * @param list<string> $fields Feldpfade einer Suche (Criteria::getAllFields())
     */
    public static function touchesProtectedField(string $rootEntity, array $fields): bool
    {
        $isMensura = str_starts_with($rootEntity, 'ld_mensura_');
        foreach ($fields as $path) {
            $segments = explode('.', $path);
            foreach ($segments as $segment) {
                if (\in_array($segment, self::SEARCH_BLOCKED, true)) {
                    return true;
                }
                if (($isMensura || str_contains($path, 'ldMensura')) && \in_array($segment, self::SEARCH_BLOCKED_JSON, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function mergeComponents(mixed $new, mixed $old): mixed
    {
        if (!\is_array($new)) {
            return $new;
        }
        $oldRows = \is_array($old) ? array_values(array_filter($old, 'is_array')) : [];
        $used = [];
        $result = [];
        foreach ($new as $index => $row) {
            if (!\is_array($row)) {
                $result[] = $row;
                continue;
            }
            $match = null;
            // gleiche Stelle und gleicher Typ, sonst erster unbenutzter Anteil gleichen Typs
            if (isset($oldRows[$index]) && !isset($used[$index]) && ($oldRows[$index]['type'] ?? null) === ($row['type'] ?? null)) {
                $match = $index;
            } else {
                foreach ($oldRows as $oldIndex => $oldRow) {
                    if (!isset($used[$oldIndex]) && ($oldRow['type'] ?? null) === ($row['type'] ?? null)) {
                        $match = $oldIndex;
                        break;
                    }
                }
            }
            if ($match !== null) {
                $used[$match] = true;
            }
            $row['purchase'] = $match !== null ? ($oldRows[$match]['purchase'] ?? null) : null;
            $result[] = $row;
        }

        return $result;
    }

    private static function mergeTiers(mixed $new, mixed $old, ?string $legacyBasis): mixed
    {
        $oldPurchase = self::purchaseSide($old, $legacyBasis);

        if (\is_array($new) && $new !== [] && array_is_list($new)) {
            // altes Format: EK je Schritt (gleiche Schwelle) übernehmen
            $byFrom = [];
            foreach ($oldPurchase['steps'] ?? [] as $step) {
                if (isset($step['price'])) {
                    $byFrom[(string) (float) $step['from']] = $step['price'];
                }
            }

            return array_map(static function ($step) use ($byFrom) {
                if (!\is_array($step)) {
                    return $step;
                }
                unset($step['purchase']);
                $key = (string) (float) ($step['from'] ?? 0);
                if (isset($byFrom[$key])) {
                    $step['purchase'] = $byFrom[$key];
                }

                return $step;
            }, $new);
        }

        $result = \is_array($new) ? $new : [];
        unset($result['purchase']);
        if ($oldPurchase !== null) {
            $result['purchase'] = $oldPurchase;
        }

        return $result === [] ? ($new === null ? null : []) : $result;
    }

    /**
     * EK-Seite eines gespeicherten Staffelwerts im neuen Format.
     *
     * @return array{basis: string, steps: list<array<string, mixed>>}|null
     */
    private static function purchaseSide(mixed $tiers, ?string $legacyBasis): ?array
    {
        if (!\is_array($tiers) || $tiers === []) {
            return null;
        }
        if (!array_is_list($tiers)) {
            return \is_array($tiers['purchase'] ?? null) ? $tiers['purchase'] : null;
        }
        $steps = [];
        foreach ($tiers as $step) {
            if (!\is_array($step) || !isset($step['from'])) {
                continue;
            }
            if (isset($step['purchase']) && is_numeric($step['purchase'])) {
                $steps[] = ['from' => $step['from'], 'price' => (float) $step['purchase']];
            } elseif (isset($step['discount']) && is_numeric($step['discount'])) {
                $steps[] = ['from' => $step['from'], 'discount' => (float) $step['discount']];
            }
        }

        return $steps === [] ? null : ['basis' => $legacyBasis ?? 'none', 'steps' => $steps];
    }
}
