<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Service\Calculation;

/**
 * Logistikanteil (KONZEPT 3d): unsichtbarer Aufschlag im VK für die Versand- und Servicekosten der Lieferanten.
 * Reines PHP ohne DAL, wie die Engine.
 *
 * Auswahl der wirksamen Regeln (3d.1/3d.2):
 *  - nur aktive Regeln mit `validFrom` ≤ Rechentag; je Ziel und Kostenart die mit dem jüngsten `validFrom`
 *  - Kalkulation und Material: je Kostenart gilt die Material-Regel vor der Kalkulations-Regel
 *  - Optionswerte (alle gewählten, auch die Lieferzeit) kommen dazu; je Wert und Kostenart gilt die auf diese
 *    Kalkulation beschränkte Regel vor der allgemeinen
 *
 * Betrag je Regel: Wert × Basis × fRampe × fSchwelle, mindestens `minimumAmount` (nur wenn der Faktor > 0 ist,
 * damit z. B. eine Sperrgut-Regel mit Mindestbetrag kleine Formate nicht trifft).
 */
final class Logistics
{
    /**
     * @param list<string> $valueIds gewählte Optionswerte inkl. Lieferzeit
     *
     * @return list<array<string, mixed>>
     */
    public static function effectiveRules(Catalog $catalog, string $calculationId, string $materialId, array $valueIds, \DateTimeImmutable $now): array
    {
        $today = $now->format('Y-m-d');
        $latest = [];
        foreach ($catalog->logisticsRules() as $rule) {
            if (($rule['active'] ?? true) === false) {
                continue;
            }
            $validFrom = self::date($rule['validFrom'] ?? null);
            if ($validFrom !== null && $validFrom > $today) {
                continue;
            }
            $key = self::targetKey($rule) . '#' . ($rule['costType'] ?? 'other');
            if (!isset($latest[$key]) || (string) $validFrom > (string) self::date($latest[$key]['validFrom'] ?? null)) {
                $latest[$key] = $rule;
            }
        }

        $byCostType = [];
        // Kalkulation, dann Material (überschreibt je Kostenart)
        foreach ($latest as $rule) {
            if (($rule['optionValueId'] ?? null) === null && ($rule['materialId'] ?? null) === null && ($rule['calculationId'] ?? null) === $calculationId) {
                $byCostType[$rule['costType']] = $rule;
            }
        }
        foreach ($latest as $rule) {
            if (($rule['materialId'] ?? null) === $materialId) {
                $byCostType[$rule['costType']] = $rule;
            }
        }
        $result = array_values($byCostType);

        // Optionswerte kommen dazu; beschränkte Regel vor allgemeiner
        foreach (array_values(array_unique($valueIds)) as $valueId) {
            $perCostType = [];
            foreach ($latest as $rule) {
                if (($rule['optionValueId'] ?? null) !== $valueId) {
                    continue;
                }
                $restriction = $rule['calculationId'] ?? null;
                if ($restriction !== null && $restriction !== $calculationId) {
                    continue;
                }
                $current = $perCostType[$rule['costType']] ?? null;
                if ($current === null || (($current['calculationId'] ?? null) === null && $restriction !== null)) {
                    $perCostType[$rule['costType']] = $rule;
                }
            }
            array_push($result, ...array_values($perCostType));
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $rule
     * @param array{longestSide: float, billedArea: float, quantity: float, goodsPurchase: float} $measures
     *
     * @return array<string, mixed>
     */
    public static function line(array $rule, array $measures): array
    {
        $fRamp = 1.0;
        if (isset($rule['rampFrom'], $rule['rampTo']) && $rule['rampTo'] > $rule['rampFrom']) {
            $fRamp = self::clamp(($measures['longestSide'] - (float) $rule['rampFrom']) / ((float) $rule['rampTo'] - (float) $rule['rampFrom']));
        }
        $fThreshold = 1.0;
        if (isset($rule['thresholdBasis'], $rule['thresholdFrom'], $rule['thresholdTo']) && $rule['thresholdTo'] > $rule['thresholdFrom']) {
            $measure = $rule['thresholdBasis'] === 'quantity' ? $measures['quantity'] : $measures['billedArea'];
            $fThreshold = self::clamp(($measure - (float) $rule['thresholdFrom']) / ((float) $rule['thresholdTo'] - (float) $rule['thresholdFrom']));
        }
        $basis = match ($rule['form'] ?? 'per_position') {
            'percent' => $measures['goodsPurchase'] / 100,
            'per_sqm' => $measures['billedArea'],
            'per_piece' => $measures['quantity'],
            default => 1.0,
        };
        $amount = (float) ($rule['value'] ?? 0) * $basis * $fRamp * $fThreshold;
        $minimumApplied = false;
        $minimum = isset($rule['minimumAmount']) ? (float) $rule['minimumAmount'] : null;
        if ($minimum !== null && $fRamp * $fThreshold > 0 && $amount < $minimum) {
            $amount = $minimum;
            $minimumApplied = true;
        }

        return [
            'ruleId' => $rule['id'] ?? null,
            'costType' => $rule['costType'] ?? 'other',
            'form' => $rule['form'] ?? null,
            'value' => (float) ($rule['value'] ?? 0),
            'calculationId' => $rule['calculationId'] ?? null,
            'materialId' => $rule['materialId'] ?? null,
            'optionValueId' => $rule['optionValueId'] ?? null,
            'rampFrom' => $rule['rampFrom'] ?? null,
            'rampTo' => $rule['rampTo'] ?? null,
            'longestSide' => $measures['longestSide'],
            'fRamp' => round($fRamp, 6),
            'fThreshold' => round($fThreshold, 6),
            'minimumScope' => $minimum !== null ? ($rule['minimumScope'] ?? 'position') : null,
            'minimumApplied' => $minimumApplied,
            'amount' => round($amount, 6),
        ];
    }

    /**
     * Linearer Teil der Regeln als VK-Preisanteile (für Aufpreis-Hinweise und „ab“-Preis, KONZEPT 3d.4 Nr. 2):
     * nur Regeln ohne Rampe, Mengenschwelle und Mindestbetrag in den Formen per_sqm/per_piece/per_position.
     * Alles andere passt in keinen festen Text und bleibt außen vor.
     *
     * @param list<array<string, mixed>> $rules
     *
     * @return list<array{type: string, sale: float}>
     */
    public static function linearComponents(array $rules): array
    {
        $components = [];
        foreach ($rules as $rule) {
            if (!self::isLinear($rule)) {
                continue;
            }
            $type = match ($rule['form'] ?? null) {
                'per_sqm' => 'per_area',
                'per_piece' => 'per_piece',
                'per_position' => 'per_order',
                default => null,
            };
            if ($type !== null) {
                $components[] = ['type' => $type, 'sale' => (float) ($rule['value'] ?? 0)];
            }
        }

        return $components;
    }

    /** @param array<string, mixed> $rule */
    public static function isLinear(array $rule): bool
    {
        return \in_array($rule['form'] ?? null, ['per_sqm', 'per_piece', 'per_position'], true)
            && ($rule['rampFrom'] ?? null) === null
            && ($rule['thresholdBasis'] ?? null) === null
            && ($rule['minimumAmount'] ?? null) === null;
    }

    private static function clamp(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }

    /** @param array<string, mixed> $rule */
    private static function targetKey(array $rule): string
    {
        if (($rule['optionValueId'] ?? null) !== null) {
            return 'v:' . $rule['optionValueId'] . '|' . ($rule['calculationId'] ?? '*');
        }
        if (($rule['materialId'] ?? null) !== null) {
            return 'm:' . $rule['materialId'];
        }

        return 'c:' . ($rule['calculationId'] ?? '');
    }

    private static function date(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return \is_string($value) && $value !== '' ? substr($value, 0, 10) : null;
    }
}
