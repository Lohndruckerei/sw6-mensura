<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Service\Calculation;

/**
 * Ergebnis einer Berechnung.
 *
 * - `toStoreApi()`: Antwort laut docs/contract/schema/calculation-result.schema.json – ohne Einkaufspreise
 * - `toSnapshot()`: Rechenweg mit EK und VK für `ld_mensura_order_line_item` (nur Admin-API)
 */
final class CalculationResult
{
    /** @var array<string, mixed>|null */
    private ?array $sale = null;
    /** @var array<string, mixed>|null */
    private ?array $purchase = null;
    /** @var list<array<string, mixed>> */
    private array $delivery = [];
    /** @var list<array<string, mixed>>|null */
    private ?array $matrix = null;

    public function __construct(
        private readonly NormalizedConfiguration $configuration,
        private readonly Catalog $catalog,
        private readonly CalculationContext $context,
    ) {
    }

    /**
     * @param array<string, mixed>      $sale
     * @param array<string, mixed>|null $purchase
     */
    public function setPrices(array $sale, ?array $purchase): void
    {
        $this->sale = $sale;
        $this->purchase = $purchase;
    }

    /** @param list<array<string, mixed>> $delivery */
    public function setDelivery(array $delivery): void
    {
        $this->delivery = $delivery;
    }

    /** @param list<array<string, mixed>> $matrix */
    public function setMatrix(array $matrix): void
    {
        $this->matrix = $matrix;
    }

    public function isValid(): bool
    {
        return $this->configuration->isValid() && $this->sale !== null;
    }

    public function getConfiguration(): NormalizedConfiguration
    {
        return $this->configuration;
    }

    public function getSaleTotal(): ?float
    {
        return $this->sale['total'] ?? null;
    }

    public function getPurchaseTotal(): ?float
    {
        return $this->purchase['total'] ?? null;
    }

    /** Lieferantenvorschlag (Material vor Kalkulation), ID aus dem LieferLotse ohne Fremdschlüssel. */
    public function getSupplierId(): ?string
    {
        $material = $this->catalog->material($this->configuration->materialId) ?? [];
        $calculation = $this->catalog->calculation($this->configuration->calculationId) ?? [];

        return $material['supplierId'] ?? $calculation['supplierId'] ?? null;
    }

    /**
     * Logistikanteil der Position (KONZEPT 3d) – nur intern: Admin-Rechenweg mit Recht `ld_mensura.logistics`
     * und Logistik-Schnappschuss. Null, wenn keine Regel wirkt.
     *
     * @return array{amount: float, goodsSubtotal: float, sale: float, sharePercent: float, lines: list<array<string, mixed>>}|null
     */
    public function getLogistics(): ?array
    {
        return $this->sale['logistics'] ?? null;
    }

    /**
     * Logistik-Schnappschuss für `ld_mensura_order_line_item_logistics` (KONZEPT 3d.5, INTEGRATION.md 7.1):
     * Logistikanteil und Produktionszeit-Aufschlag im Endpreis getrennt, Zeilen mit den gemeinsamen Schlüsseln
     * (calculationKey, materialKey, deliveryKey bzw. optionKey) statt Fremdschlüsseln. Nur Admin-API.
     *
     * @return array<string, mixed>|null
     */
    public function toLogisticsSnapshot(): ?array
    {
        if ($this->sale === null) {
            return null;
        }
        $logistics = $this->sale['logistics'] ?? null;
        $logisticsSale = round((float) ($logistics['sale'] ?? 0), 2);
        $sale = (float) $this->sale['total'];
        $purchase = $this->purchase['total'] ?? null;

        $lines = [];
        foreach ($logistics['lines'] ?? [] as $line) {
            $keys = [];
            if ($line['optionValueId'] !== null) {
                $value = $this->catalog->value($line['optionValueId']) ?? [];
                $type = $this->catalog->group($value['optionGroupId'] ?? null)['type'] ?? 'standard';
                $keys[$type === 'delivery' ? 'deliveryKey' : 'optionKey'] = $value['technicalName'] ?? null;
            } elseif ($line['materialId'] !== null) {
                $keys['materialKey'] = $this->catalog->material($line['materialId'])['technicalName'] ?? null;
            }
            if ($line['calculationId'] !== null) {
                $keys['calculationKey'] = $this->catalog->calculation($line['calculationId'])['technicalName'] ?? null;
            }
            unset($line['calculationId'], $line['materialId'], $line['optionValueId']);
            $lines[] = $keys + $line;
        }

        return [
            'schemaVersion' => 1,
            'logisticsSale' => $logisticsSale,
            'productionTimeSale' => $this->sale['productionTime'] ?? null,
            'sharePercent' => (float) ($logistics['sharePercent'] ?? 0.0),
            'goodsContributionMargin' => $purchase !== null ? round($sale - $logisticsSale - (float) $purchase, 2) : null,
            'lines' => $lines,
        ];
    }

    /**
     * @param array<string, mixed>|null $side
     *
     * @return array<string, mixed>|null
     */
    private static function withoutLogistics(?array $side): ?array
    {
        if ($side !== null) {
            // productionTime verrät zusammen mit den Summen den Express-Logistikanteil
            unset($side['logistics'], $side['productionTime']);
        }

        return $side;
    }

    /**
     * Gewicht der Position in kg (Material: Gewicht je m² × Fläche), null wenn am Material nicht gepflegt.
     *
     * @return array{perSqm: float, piece: float, total: float}|null
     */
    public function getWeight(): ?array
    {
        return $this->sale['weight'] ?? null;
    }

    /**
     * Flächenwerte der Position (m²): je Stück, gesamt (L × B × Stück), abgerechnet (nach Mindestfläche/Rundung),
     * dazu Mindestfläche, Schrittweite und Bezug. Für Payload, Formeln und spätere Plugins (z. B. Versand).
     *
     * @return array{piece: float, total: float, billed: float, minArea: float|null, rounding: float|null, scope: string, width: float, height: float, perimeter: float}|null
     */
    public function getAreas(): ?array
    {
        if ($this->sale === null) {
            return null;
        }

        return [
            'piece' => (float) $this->sale['rawPieceArea'],
            'total' => (float) $this->sale['area'],
            'billed' => (float) $this->sale['billedArea'],
            'minArea' => $this->sale['minArea'],
            'rounding' => $this->sale['areaRounding'],
            'scope' => (string) $this->sale['areaScope'],
            'width' => (float) $this->sale['width'],
            'height' => (float) $this->sale['height'],
            'perimeter' => (float) $this->sale['perimeter'],
        ];
    }

    /** @return array<string, mixed> */
    public function toStoreApi(): array
    {
        $config = $this->configuration;
        $response = [
            'apiVersion' => 1,
            'valid' => $this->isValid(),
            'errors' => $config->errors,
            'hints' => $config->hints,
            'configuration' => $config->toArray($this->catalog),
            'activeGroups' => array_values(array_map(fn (string $id) => (string) ($this->catalog->group($id)['technicalName'] ?? $id), $config->activeGroupIds)),
        ];
        if ($this->isValid() && $this->sale !== null) {
            $quantity = (int) $this->sale['quantity'];
            $area = (float) $this->sale['area'];
            $total = (float) $this->sale['total'];
            $response['price'] = [
                'total' => $total,
            ];
            // Bruttopreis nur, wenn der Steuersatz bekannt ist (Store-API: Steuer des Produkts für den Kunden)
            if ($this->context->taxRate !== null) {
                $response['price']['totalGross'] = round($total * (1 + $this->context->taxRate / 100), 2);
            }
            $response['price'] += [
                'perPiece' => round($total / max(1, $quantity), 2),
                'perSqm' => $area > 0 ? round($total / $area, 2) : 0.0,
                'quantity' => $quantity,
                'area' => round($area, 4),
                'areas' => [
                    'piece' => round((float) $this->sale['rawPieceArea'], 6),
                    'total' => round($area, 6),
                    'billed' => round((float) $this->sale['billedArea'], 6),
                    'minArea' => $this->sale['minArea'],
                    'rounding' => $this->sale['areaRounding'],
                ],
                'minimumApplied' => (bool) $this->sale['minimumApplied'],
                'currency' => $this->context->currency,
            ];
        }
        if ($this->delivery !== []) {
            $response['delivery'] = $this->delivery;
        }
        if ($this->matrix !== null) {
            $response['matrix'] = $this->matrix;
        }

        return $response;
    }

    /**
     * Rechenweg-Schnappschuss (INTEGRATION.md Abschnitt 2) – enthält Einkaufspreise, nie in die Store-API.
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        $calculation = $this->catalog->calculation($this->configuration->calculationId) ?? [];
        $material = $this->catalog->material($this->configuration->materialId) ?? [];
        $sale = $this->sale['total'] ?? null;
        $purchase = $this->purchase['total'] ?? null;

        return [
            'schema' => 1,
            'calculationId' => $this->configuration->calculationId,
            'calculationTechnicalName' => $calculation['technicalName'] ?? null,
            'materialId' => $this->configuration->materialId,
            'supplierId' => $material['supplierId'] ?? $calculation['supplierId'] ?? null,
            'customerGroupId' => $this->context->customerGroupId,
            'configuration' => json_decode((string) json_encode($this->configuration->toArray($this->catalog)), true),
            // Logistikanteil nicht im allgemeinen Rechenweg (KONZEPT 3d.4 Nr. 4): Zwischensumme enthält ihn bereits
            'breakdown' => ['sale' => self::withoutLogistics($this->sale), 'purchase' => self::withoutLogistics($this->purchase)],
            'saleTotal' => $sale,
            'purchaseTotal' => $purchase,
            'contributionMargin' => $sale !== null && $purchase !== null ? round($sale - $purchase, 2) : null,
        ];
    }
}
