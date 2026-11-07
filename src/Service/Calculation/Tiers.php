<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Service\Calculation;

/**
 * Staffeln lesen – altes und neues Format (PRODUKTBEISPIELE Lücke 1):
 *
 *  alt (v0.2/v0.3, eine Basis aus der Spalte `tierBasis` des Besitzers):
 *    [{"from": 10, "purchase": 4.80, "sale": 5.25}, {"from": 50, "discount": 5}]
 *  neu (v0.4.0, EK und VK getrennt, je eigene Basis):
 *    {"purchase": {"basis": "piece_area", "steps": [{"from": 0, "price": 7.5}, {"from": 1, "price": 6.5}]},
 *     "sale":     {"basis": "none"}}
 *
 * Ein Schritt ersetzt den Preis (`price`, im alten Format `purchase`/`sale`) oder gibt Rabatt % (`discount`).
 * Basis: none, quantity, total_area, piece_area, total_price. Es gilt der letzte Schritt mit `from` ≤ Messwert.
 */
final class Tiers
{
    public const BASES = ['none', 'quantity', 'total_area', 'piece_area', 'total_price'];

    /**
     * @param mixed $tiers JSON-Inhalt (Array) oder null
     *
     * @return array{basis: string, steps: list<array{from: float, price?: float, discount?: float}>}|null
     *         null = keine Staffel für diese Seite
     */
    public static function forSide(mixed $tiers, ?string $legacyBasis, string $side): ?array
    {
        if (!\is_array($tiers) || $tiers === []) {
            return null;
        }

        if (array_is_list($tiers)) {
            $basis = $legacyBasis ?? 'none';
            if ($basis === 'none') {
                return null;
            }
            $steps = [];
            foreach ($tiers as $step) {
                if (!\is_array($step) || !isset($step['from'])) {
                    continue;
                }
                $normalized = ['from' => (float) $step['from']];
                if (isset($step[$side]) && is_numeric($step[$side])) {
                    $normalized['price'] = (float) $step[$side];
                } elseif (isset($step['price']) && is_numeric($step['price'])) {
                    $normalized['price'] = (float) $step['price'];
                } elseif (isset($step['discount']) && is_numeric($step['discount'])) {
                    $normalized['discount'] = (float) $step['discount'];
                } else {
                    continue;
                }
                $steps[] = $normalized;
            }

            return self::sorted($basis, $steps);
        }

        $sideData = $tiers[$side] ?? null;
        if (!\is_array($sideData)) {
            return null;
        }
        $basis = (string) ($sideData['basis'] ?? 'none');
        if ($basis === 'none' || !\in_array($basis, self::BASES, true)) {
            return null;
        }
        $steps = [];
        foreach ($sideData['steps'] ?? [] as $step) {
            if (!\is_array($step) || !isset($step['from'])) {
                continue;
            }
            $normalized = ['from' => (float) $step['from']];
            if (isset($step['price']) && is_numeric($step['price'])) {
                $normalized['price'] = (float) $step['price'];
            } elseif (isset($step['discount']) && is_numeric($step['discount'])) {
                $normalized['discount'] = (float) $step['discount'];
            } else {
                continue;
            }
            $steps[] = $normalized;
        }

        return self::sorted($basis, $steps);
    }

    /**
     * Gültiger Schritt für den Messwert (oder null, wenn der Messwert unter dem ersten Schritt liegt).
     *
     * @param array{basis: string, steps: list<array{from: float, price?: float, discount?: float}>} $tier
     *
     * @return array{from: float, price?: float, discount?: float}|null
     */
    public static function pick(array $tier, float $measure): ?array
    {
        $found = null;
        foreach ($tier['steps'] as $step) {
            if ($step['from'] <= $measure + 1e-9) {
                $found = $step;
            }
        }

        return $found;
    }

    /**
     * @param list<array{from: float, price?: float, discount?: float}> $steps
     *
     * @return array{basis: string, steps: list<array{from: float, price?: float, discount?: float}>}|null
     */
    private static function sorted(string $basis, array $steps): ?array
    {
        if ($steps === []) {
            return null;
        }
        usort($steps, static fn (array $a, array $b) => $a['from'] <=> $b['from']);

        return ['basis' => $basis, 'steps' => $steps];
    }
}
