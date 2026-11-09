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
        $supplierId = $material['supplierId'] ?? $calculation['supplierId'] ?? null;
        $numbers = $this->supplierProductNumbers();

        return [
            'schema' => 1,
            'calculationId' => $this->configuration->calculationId,
            'calculationTechnicalName' => $calculation['technicalName'] ?? null,
            'materialId' => $this->configuration->materialId,
            'supplierId' => $supplierId,
            'supplierProductNumber' => $supplierId !== null ? ($numbers[$supplierId] ?? null) : null,
            // je Lieferant (LieferLotse wählt beim Bestellen den passenden): supplierId => „124-29.1-57-3“
            'supplierProductNumbers' => $numbers,
            'customerGroupId' => $this->context->customerGroupId,
            'configuration' => json_decode((string) json_encode($this->configuration->toArray($this->catalog)), true),
            'breakdown' => ['sale' => $this->sale, 'purchase' => $this->purchase],
            'saleTotal' => $sale,
            'purchaseTotal' => $purchase,
            'contributionMargin' => $sale !== null && $purchase !== null ? round($sale - $purchase, 2) : null,
        ];
    }

    /**
     * Lieferanten-Artikelnummer je Lieferant aus den Lieferantencodes (INTEGRATION.md 3):
     * Kalkulation-Material-Optionswerte (in Reihenfolge der Gruppen), z. B. „124-29.1-57-3-134“.
     * Nur für Lieferanten, für die die Kalkulation einen Code hat; fehlende Teile werden ausgelassen.
     *
     * @return array<string, string>
     */
    public function supplierProductNumbers(): array
    {
        $codes = static function (?array $entity): array {
            $map = [];
            foreach (\is_array($entity['supplierCodes'] ?? null) ? $entity['supplierCodes'] : [] as $row) {
                $supplier = \is_array($row) ? (string) ($row['supplierId'] ?? '') : '';
                $code = \is_array($row) ? trim((string) ($row['code'] ?? '')) : '';
                if ($supplier !== '' && $code !== '') {
                    $map[$supplier] = $code;
                }
            }

            return $map;
        };

        $calculationCodes = $codes($this->catalog->calculation($this->configuration->calculationId));
        $materialCodes = $codes($this->catalog->material($this->configuration->materialId));
        $valueIds = array_values(array_filter([...array_values($this->configuration->options), $this->configuration->deliveryValueId]));
        $valueCodes = array_map(fn (string $id) => $codes($this->catalog->value($id)), $valueIds);

        $numbers = [];
        foreach ($calculationCodes as $supplierId => $calculationCode) {
            $parts = [$calculationCode];
            if (isset($materialCodes[$supplierId])) {
                $parts[] = $materialCodes[$supplierId];
            }
            foreach ($valueCodes as $map) {
                if (isset($map[$supplierId])) {
                    $parts[] = $map[$supplierId];
                }
            }
            $numbers[$supplierId] = implode('-', $parts);
        }

        return $numbers;
    }
}
