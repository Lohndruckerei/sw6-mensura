<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Service\Calculation;

/**
 * Mindestbetrag je Bestellung (KONZEPT 3d.3, Geltung `order`, z. B. 24h Express mindestens 25 €).
 *
 * Produktseite und Preismatrix rechnen eine Position allein, also mit dem vollen Mindestbetrag. Im Warenkorb gilt
 * er einmal je Optionswert (bei Lieferzeit-Gruppen mit `scope = supplier` zusätzlich je Lieferant):
 *
 *   Ziel   = max(Mindestbetrag, Σ lineare Anteile der Positionen)
 *   Anteil = Ziel × Gewicht ÷ Σ Gewichte     Gewicht = linearer Anteil, ersatzweise abgerechnete Fläche, sonst gleich
 *
 * Damit ist der Preis im Warenkorb nie höher als auf der Produktseite. Eine einzelne Position behält den vollen
 * Betrag. Reines PHP; angewendet vom CartProcessor und vom Bestell-Schnappschuss (dieselbe Rechnung).
 */
final class OrderMinimum
{
    /**
     * @param array<array-key, CalculationResult> $results Positionen des Warenkorbs (erste Rechnung, ohne Verteilung)
     *
     * @return array<array-key, array<string, float>> je Position: Logistikregel-ID → verteilter Betrag
     */
    public static function overrides(array $results): array
    {
        $groups = [];
        foreach ($results as $key => $result) {
            $logistics = $result->getLogistics();
            if ($logistics === null) {
                continue;
            }
            foreach ($logistics['lines'] as $line) {
                if (($line['minimumScope'] ?? null) !== 'order' || ($line['minimumAmount'] ?? null) === null
                    || ($line['ruleId'] ?? null) === null || ($line['optionValueId'] ?? null) === null) {
                    continue;
                }
                if ((float) $line['fRamp'] * (float) $line['fThreshold'] <= 0) {
                    continue;
                }
                $supplier = ($line['groupScope'] ?? 'position') === 'supplier' ? (string) $result->getSupplierId() : '';
                $groupKey = $line['optionValueId'] . '|' . $supplier;
                $groups[$groupKey]['minimum'] = max((float) ($groups[$groupKey]['minimum'] ?? 0), (float) $line['minimumAmount']);
                $groups[$groupKey]['members'][] = [
                    'key' => $key,
                    'ruleId' => (string) $line['ruleId'],
                    'linear' => max(0.0, (float) $line['linearAmount']),
                    'area' => (float) ($result->getAreas()['billed'] ?? 0),
                ];
            }
        }

        $overrides = [];
        foreach ($groups as $group) {
            $members = $group['members'];
            if (\count($members) < 2) {
                continue;
            }
            $sumLinear = array_sum(array_column($members, 'linear'));
            $target = max($group['minimum'], $sumLinear);
            $weightKey = $sumLinear > 0 ? 'linear' : (array_sum(array_column($members, 'area')) > 0 ? 'area' : null);
            $sumWeights = $weightKey !== null ? array_sum(array_column($members, $weightKey)) : \count($members);
            foreach ($members as $member) {
                $weight = $weightKey !== null ? $member[$weightKey] : 1.0;
                $overrides[$member['key']][$member['ruleId']] = round($target * $weight / $sumWeights, 6);
            }
        }

        return $overrides;
    }
}
