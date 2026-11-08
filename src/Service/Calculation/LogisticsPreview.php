<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Service\Calculation;

/**
 * Vorschau für die Admin-Karte „Logistikanteil“ (KONZEPT 3d.6): rechnet Beispielkonfigurationen mit den gespeicherten
 * Regeln und mit einem Entwurf (geänderte oder neue Regeln, noch nicht gespeichert) – mit derselben Engine wie Shop
 * und Warenkorb. Liefert je Beispiel VK mit und ohne Logistik, den Anteil am VK und eine Warnung über der Schwelle.
 * Nur für die Administration mit Recht `ld_mensura.logistics`, nie Store-API.
 */
final class LogisticsPreview
{
    private const FIELDS = [
        'id' => 'string', 'calculationId' => 'string', 'materialId' => 'string', 'optionValueId' => 'string',
        'costType' => 'string', 'form' => 'string', 'value' => 'float', 'rampFrom' => 'int', 'rampTo' => 'int',
        'thresholdBasis' => 'string', 'thresholdFrom' => 'float', 'thresholdTo' => 'float', 'minimumAmount' => 'float',
        'minimumScope' => 'string', 'validFrom' => 'string', 'active' => 'bool',
    ];

    public function __construct(private readonly PriceEngine $engine)
    {
    }

    /**
     * @param list<mixed>                $draft   Regeln im Entwurf (ersetzen gespeicherte mit gleicher ID, sonst neu)
     * @param list<array<string, mixed>> $samples Beispielkonfigurationen {materialId?, format{width,height}, perMotif?, options?, deliveryValue?}
     *
     * @return array{warnPercent: float, samples: list<array<string, mixed>>}
     */
    public function preview(Catalog $catalog, array $draft, array $samples, CalculationContext $context, float $warnPercent): array
    {
        $rules = [];
        foreach ($catalog->logisticsRules() as $rule) {
            $rules[(string) ($rule['id'] ?? \count($rules))] = $rule;
        }
        foreach ($draft as $index => $rule) {
            if (!\is_array($rule)) {
                continue;
            }
            $clean = self::sanitize($rule);
            $clean['id'] ??= 'draft-' . $index;
            if ($clean['active'] !== false) {
                // „als gälte der Entwurf schon heute“: gespeicherte Regeln derselben Kostenart dieses Ziels treten zurück
                foreach ($rules as $id => $saved) {
                    if ($id !== $clean['id'] && self::sameTarget($saved, $clean) && ($saved['costType'] ?? null) === $clean['costType']) {
                        $rules[$id]['active'] = false;
                    }
                }
            }
            $rules[$clean['id']] = $clean;
        }
        $draftCatalog = $catalog->withLogisticsRules(array_values($rules));

        $rows = [];
        foreach (\array_slice($samples, 0, 10) as $sample) {
            if (!\is_array($sample)) {
                continue;
            }
            $input = [
                'schema' => 1,
                'productId' => $catalog->productId(),
                'materialId' => \is_string($sample['materialId'] ?? null) ? $sample['materialId'] : null,
                'format' => [
                    'width' => (int) ($sample['format']['width'] ?? 1000),
                    'height' => (int) ($sample['format']['height'] ?? 1000),
                ],
                'motifs' => 1,
                'perMotif' => max(1, (int) ($sample['perMotif'] ?? 1)),
                'options' => \is_array($sample['options'] ?? null) ? $sample['options'] : [],
            ];
            if (\is_string($sample['deliveryValue'] ?? null)) {
                $input['deliveryValue'] = $sample['deliveryValue'];
            }

            $result = $this->engine->calculate($input, $draftCatalog, $context);
            if (!$result->isValid()) {
                $rows[] = [
                    'input' => $input,
                    'valid' => false,
                    'errors' => array_column($result->toStoreApi()['errors'], 'message'),
                ];
                continue;
            }
            $saved = $this->engine->calculate($input, $catalog, $context);
            $total = (float) $result->getSaleTotal();
            $logistics = $result->getLogistics();
            $logisticsSale = (float) ($logistics['sale'] ?? 0);
            $share = (float) ($logistics['sharePercent'] ?? 0);
            $configuration = $result->toStoreApi()['configuration'];
            $rows[] = [
                'input' => $configuration,
                'valid' => true,
                'material' => $catalog->material($configuration['materialId'] ?? null)['name'] ?? null,
                'quantity' => $result->toStoreApi()['price']['quantity'],
                'area' => $result->getAreas()['billed'] ?? null,
                'longestSide' => max((float) ($result->getAreas()['width'] ?? 0), (float) ($result->getAreas()['height'] ?? 0)),
                'totalWithoutLogistics' => round($total - $logisticsSale, 2),
                'logistics' => round($logisticsSale, 2),
                'total' => $total,
                'totalSaved' => $saved->isValid() ? $saved->getSaleTotal() : null,
                'sharePercent' => $share,
                'warn' => $share > $warnPercent,
                'lines' => array_map(static fn (array $line) => [
                    'ruleId' => $line['ruleId'],
                    'costType' => $line['costType'],
                    'amount' => round((float) $line['amount'], 2),
                    'fRamp' => $line['fRamp'],
                    'fThreshold' => $line['fThreshold'],
                    'minimumApplied' => $line['minimumApplied'],
                ], $logistics['lines'] ?? []),
            ];
        }

        return ['warnPercent' => $warnPercent, 'samples' => $rows];
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    private static function sameTarget(array $a, array $b): bool
    {
        foreach (['calculationId', 'materialId', 'optionValueId'] as $field) {
            if (($a[$field] ?? null) !== ($b[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Nur bekannte Felder mit festem Typ übernehmen (Entwurf kommt aus dem Browser).
     *
     * @param array<string, mixed> $rule
     *
     * @return array<string, mixed>
     */
    public static function sanitize(array $rule): array
    {
        $clean = [];
        foreach (self::FIELDS as $field => $type) {
            $value = $rule[$field] ?? null;
            if ($value === null || $value === '') {
                $clean[$field] = $field === 'active' ? true : null;
                continue;
            }
            $clean[$field] = match ($type) {
                'int' => (int) $value,
                'float' => (float) $value,
                'bool' => (bool) $value,
                default => \is_scalar($value) ? (string) $value : null,
            };
        }
        if ($clean['validFrom'] !== null) {
            $clean['validFrom'] = substr($clean['validFrom'], 0, 10);
        }

        return $clean;
    }
}
