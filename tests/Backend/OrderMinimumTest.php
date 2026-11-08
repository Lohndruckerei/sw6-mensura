<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Tests\Backend;

use Lohndruckerei\Mensura\Service\Calculation\CalculationResult;
use Lohndruckerei\Mensura\Service\Calculation\Catalog;
use Lohndruckerei\Mensura\Service\Calculation\OrderMinimum;
use Lohndruckerei\Mensura\Service\Calculation\PriceEngine;
use Lohndruckerei\Mensura\Service\Demo\DemoCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Mindestbetrag je Bestellung (KONZEPT 3d.3): Verteilung im Warenkorb, so wie CartProcessor und Bestell-Schnappschuss
 * sie anwenden (erst jede Position allein, dann mit verteilten Anteilen).
 */
final class OrderMinimumTest extends TestCase
{
    private const ALU = DemoCatalog::MATERIAL_ALU;
    private const ACRYL = DemoCatalog::MATERIAL_ACRYL;

    public function testSinglePositionKeepsTheFullMinimum(): void
    {
        $results = $this->cart(['a' => $this->express(self::ALU, 1000, 1000)]);
        self::assertSame([], OrderMinimum::overrides($results));
        self::assertSame(25.0, $results['a']->getLogistics()['lines'][0]['amount']);
    }

    public function testMinimumIsSharedAndCartNeverGetsMoreExpensive(): void
    {
        $alone = $this->cart(['a' => $this->express(self::ALU, 1000, 1000), 'b' => $this->express(self::ALU, 1000, 1000, 2)]);
        $overrides = OrderMinimum::overrides($alone);

        // linear 4,18 € (1 m²) und 8,36 € (2 m²) → Ziel max(25; 12,54) = 25 €, verteilt 1 : 2
        self::assertEqualsWithDelta(8.333333, $overrides['a']['express'], 1e-6);
        self::assertEqualsWithDelta(16.666667, $overrides['b']['express'], 1e-6);

        $shared = $this->cart(['a' => $this->express(self::ALU, 1000, 1000), 'b' => $this->express(self::ALU, 1000, 1000, 2)], $overrides);
        foreach (['a', 'b'] as $key) {
            self::assertLessThanOrEqual($alone[$key]->getSaleTotal(), $shared[$key]->getSaleTotal());
            self::assertTrue($shared[$key]->getLogistics()['lines'][0]['distributed']);
        }
        $sumLogistics = $shared['a']->getLogistics()['amount'] + $shared['b']->getLogistics()['amount'];
        self::assertEqualsWithDelta(25.0, $sumLogistics, 1e-5);
        // Summe der Positionen sinkt um 25 € (einmal statt zweimal Mindestbetrag), Express +30 % wirkt nicht auf Logistik
        self::assertEqualsWithDelta(
            $alone['a']->getSaleTotal() + $alone['b']->getSaleTotal() - 25.0,
            $shared['a']->getSaleTotal() + $shared['b']->getSaleTotal(),
            0.011,
        );
    }

    public function testAboveTheMinimumEveryPositionKeepsItsLinearShare(): void
    {
        // 4 m² + 5 m² → linear 16,72 € + 20,90 € = 37,62 € > 25 €
        $results = $this->cart(['a' => $this->express(self::ALU, 1000, 1000, 4), 'b' => $this->express(self::ALU, 1000, 1000, 5)]);
        $overrides = OrderMinimum::overrides($results);
        self::assertEqualsWithDelta(16.72, $overrides['a']['express'], 1e-6);
        self::assertEqualsWithDelta(20.9, $overrides['b']['express'], 1e-6);
    }

    public function testOtherOptionValuesAndStandardDeliveryAreNotMixed(): void
    {
        $results = $this->cart([
            'a' => $this->express(self::ALU, 1000, 1000),
            'b' => ['materialId' => self::ALU, 'format' => ['width' => 1000, 'height' => 1000], 'perMotif' => 1],
        ]);
        self::assertSame([], OrderMinimum::overrides($results));
    }

    public function testSupplierScopeSeparatesSuppliers(): void
    {
        $data = $this->catalogData();
        $data['groups'][DemoCatalog::id('group.lieferzeit')]['scope'] = 'supplier';
        $data['materials'][self::ALU]['supplierId'] = 'lieferant-a';
        $data['materials'][self::ACRYL]['supplierId'] = 'lieferant-b';

        $mixed = $this->cart(['a' => $this->express(self::ALU, 1000, 1000), 'b' => $this->express(self::ACRYL, 1000, 1000)], [], $data);
        self::assertSame([], OrderMinimum::overrides($mixed));

        $same = $this->cart(['a' => $this->express(self::ALU, 1000, 1000), 'b' => $this->express(self::ALU, 500, 500)], [], $data);
        self::assertCount(2, OrderMinimum::overrides($same));
    }

    /** @return array<string, mixed> */
    private function express(string $materialId, int $width, int $height, int $perMotif = 1): array
    {
        return ['materialId' => $materialId, 'format' => ['width' => $width, 'height' => $height], 'perMotif' => $perMotif, 'deliveryValue' => 'express'];
    }

    /** @return array<string, mixed> */
    private function catalogData(): array
    {
        $data = DemoCatalog::catalog();
        $data['logisticsRules'] = [[
            'id' => 'express',
            'calculationId' => null,
            'materialId' => null,
            'optionValueId' => DemoCatalog::id('value.lieferzeit.express'),
            'costType' => 'express',
            'form' => 'per_sqm',
            'value' => 4.18,
            'rampFrom' => null,
            'rampTo' => null,
            'thresholdBasis' => null,
            'thresholdFrom' => null,
            'thresholdTo' => null,
            'minimumAmount' => 25.0,
            'minimumScope' => 'order',
            'validFrom' => null,
            'active' => true,
        ]];

        return $data;
    }

    /**
     * @param array<string, array<string, mixed>>       $positions
     * @param array<string, array<string, float>>       $overrides
     * @param array<string, mixed>|null                 $data
     *
     * @return array<string, CalculationResult>
     */
    private function cart(array $positions, array $overrides = [], ?array $data = null): array
    {
        $catalog = new Catalog($data ?? $this->catalogData());
        $results = [];
        foreach ($positions as $key => $position) {
            $input = $position + ['schema' => 1, 'productId' => DemoCatalog::PRODUCT_ID, 'motifs' => 1, 'options' => [], 'deliveryValue' => 'standard'];
            $results[$key] = (new PriceEngine())->calculate($input, $catalog, ContractTest::context(), false, $overrides[$key] ?? []);
            self::assertTrue($results[$key]->isValid(), "Position $key gültig");
        }

        return $results;
    }
}
