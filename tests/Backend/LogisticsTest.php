<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Tests\Backend;

use Lohndruckerei\Mensura\Service\Calculation\CalculationResult;
use Lohndruckerei\Mensura\Service\Calculation\Catalog;
use Lohndruckerei\Mensura\Service\Calculation\ConfiguratorBuilder;
use Lohndruckerei\Mensura\Service\Calculation\PriceEngine;
use Lohndruckerei\Mensura\Service\Demo\DemoCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Logistikanteil (KONZEPT 3d): Regeln, Rechenweg-Schritt und Unsichtbarkeit.
 * Rechentag der Tests: 06.11.2026 (ContractTest::context()).
 */
final class LogisticsTest extends TestCase
{
    private const ALU = DemoCatalog::MATERIAL_ALU;
    private const ACRYL = DemoCatalog::MATERIAL_ACRYL;

    public function testSmallFormatsAreUnchangedByTheRamp(): void
    {
        $with = $this->calc(['materialId' => self::ACRYL, 'format' => ['width' => 600, 'height' => 400]]);
        $without = $this->calc(['materialId' => self::ACRYL, 'format' => ['width' => 600, 'height' => 400]], []);

        self::assertSame($without->getSaleTotal(), $with->getSaleTotal());
        self::assertSame(0.0, $with->getLogistics()['amount']);
    }

    public function testRampIsLinearOverTheLongestSide(): void
    {
        // 1.200 mm liegt in der Mitte der Rampe 1.000 → 1.400: 17,77 €/m² × 1,2 m² × 0,5 = 10,662 €
        $half = $this->calc(['materialId' => self::ACRYL, 'format' => ['width' => 1000, 'height' => 1200]]);
        $plain = $this->calc(['materialId' => self::ACRYL, 'format' => ['width' => 1000, 'height' => 1200]], []);
        self::assertEqualsWithDelta(10.66, $half->getSaleTotal() - $plain->getSaleTotal(), 0.011);
        self::assertSame(0.5, $half->getLogistics()['lines'][0]['fRamp']);

        // ab 1.400 mm voller Wert, auch weit darüber (kein zweiter Sprung): 17,77 × 1,5 = 26,655 €
        $full = $this->calc(['materialId' => self::ACRYL, 'format' => ['width' => 1500, 'height' => 1000]]);
        $fullPlain = $this->calc(['materialId' => self::ACRYL, 'format' => ['width' => 1500, 'height' => 1000]], []);
        self::assertEqualsWithDelta(26.66, $full->getSaleTotal() - $fullPlain->getSaleTotal(), 0.011);
        self::assertSame(1.0, $full->getLogistics()['lines'][0]['fRamp']);
        self::assertSame(1500.0, $full->getLogistics()['lines'][0]['longestSide']);
    }

    public function testEkSideHasNoLogistics(): void
    {
        $with = $this->calc(['materialId' => self::ACRYL, 'format' => ['width' => 1500, 'height' => 1000]]);
        $without = $this->calc(['materialId' => self::ACRYL, 'format' => ['width' => 1500, 'height' => 1000]], []);
        self::assertSame($without->getPurchaseTotal(), $with->getPurchaseTotal());
    }

    public function testMaterialRuleReplacesCalculationRuleOfTheSameCostTypeOnly(): void
    {
        $calculation = DemoCatalog::id('calculation.aluverbund');
        $rules = [
            self::rule(['id' => 'c-base', 'calculationId' => $calculation, 'costType' => 'base', 'form' => 'per_position', 'value' => 5.0]),
            self::rule(['id' => 'c-other', 'calculationId' => $calculation, 'costType' => 'other', 'form' => 'per_position', 'value' => 1.0]),
            self::rule(['id' => 'm-base', 'materialId' => self::ALU, 'costType' => 'base', 'form' => 'per_position', 'value' => 2.0]),
        ];
        $result = $this->calc(['format' => ['width' => 1000, 'height' => 500], 'perMotif' => 10], $rules);

        self::assertSame(['m-base', 'c-other'], array_column($result->getLogistics()['lines'], 'ruleId'));
        self::assertSame(153.0, $result->getSaleTotal()); // 150 + 2 + 1
    }

    public function testValidFromPicksTheLatestPastRule(): void
    {
        $rules = [
            self::rule(['id' => 'old', 'materialId' => self::ALU, 'value' => 1.0, 'validFrom' => '2026-01-01']),
            self::rule(['id' => 'current', 'materialId' => self::ALU, 'value' => 2.0, 'validFrom' => '2026-11-01']),
            self::rule(['id' => 'planned', 'materialId' => self::ALU, 'value' => 9.0, 'validFrom' => '2026-12-01']),
            self::rule(['id' => 'inactive', 'materialId' => self::ALU, 'costType' => 'other', 'value' => 9.0, 'active' => false]),
        ];
        $result = $this->calc(['format' => ['width' => 1000, 'height' => 500], 'perMotif' => 10], $rules);

        self::assertSame(['current'], array_column($result->getLogistics()['lines'], 'ruleId'));
        self::assertSame(152.0, $result->getSaleTotal());
    }

    public function testDeliveryRuleWithMinimumAndCalculationRestriction(): void
    {
        $express = DemoCatalog::id('value.lieferzeit.express');
        $rules = [
            self::rule(['id' => 'general', 'optionValueId' => $express, 'costType' => 'express', 'form' => 'per_sqm', 'value' => 9.0]),
            self::rule(['id' => 'alu', 'optionValueId' => $express, 'calculationId' => DemoCatalog::id('calculation.aluverbund'), 'costType' => 'express', 'form' => 'per_sqm', 'value' => 4.18, 'minimumAmount' => 25.0, 'minimumScope' => 'order']),
            self::rule(['id' => 'acryl-only', 'optionValueId' => $express, 'calculationId' => DemoCatalog::id('calculation.acryl'), 'costType' => 'other', 'form' => 'per_position', 'value' => 50.0]),
        ];
        // 1 m² Alu Express: 4,18 € < 25 € → Mindestbetrag (Produktseite rechnet die Position allein)
        $small = $this->calc(['format' => ['width' => 1000, 'height' => 500], 'perMotif' => 2, 'deliveryValue' => 'express'], $rules);
        $line = $small->getLogistics()['lines'][0];
        self::assertSame('alu', $line['ruleId']);
        self::assertTrue($line['minimumApplied']);
        self::assertSame('order', $line['minimumScope']);
        self::assertSame(25.0, $line['amount']);
        self::assertCount(1, $small->getLogistics()['lines']);

        // 10 m²: 41,80 € > 25 € → linear
        $large = $this->calc(['format' => ['width' => 1000, 'height' => 1000], 'perMotif' => 10, 'deliveryValue' => 'express'], $rules);
        self::assertEqualsWithDelta(41.8, $large->getLogistics()['lines'][0]['amount'], 1e-6);

        // Standard-Lieferzeit: keine Express-Regel
        self::assertNull($this->calc(['format' => ['width' => 1000, 'height' => 500], 'perMotif' => 2], $rules)->getLogistics());
    }

    public function testPercentUsesGoodsPurchase(): void
    {
        $rules = [self::rule(['materialId' => self::ALU, 'form' => 'percent', 'value' => 10.0])];
        $result = $this->calc(['format' => ['width' => 1000, 'height' => 500], 'perMotif' => 10], $rules);

        // Waren-EK (26 × 0,5 + 0,45 + 0,10) × 10 = 135,50 € → 10 % = 13,55 €
        self::assertSame(135.5, $result->getPurchaseTotal());
        self::assertSame(163.55, $result->getSaleTotal());
    }

    public function testThresholdIsGradual(): void
    {
        $rules = [self::rule(['materialId' => self::ALU, 'costType' => 'quantity', 'form' => 'per_sqm', 'value' => 1.0, 'thresholdBasis' => 'total_area', 'thresholdFrom' => 4.0, 'thresholdTo' => 8.0])];
        // 5 m² (10 × 0,5 m²): Faktor 0,25 → 5 × 1 × 0,25 = 1,25 €
        $result = $this->calc(['format' => ['width' => 1000, 'height' => 500], 'perMotif' => 10], $rules);
        self::assertSame(0.25, $result->getLogistics()['lines'][0]['fThreshold']);
        self::assertSame(151.25, $result->getSaleTotal());
    }

    public function testMinimumPriceContainsTheShareProportionallyAndCustomerGroupCarriesIt(): void
    {
        $rules = [self::rule(['materialId' => self::ALU, 'form' => 'per_position', 'value' => 2.0])];
        // 600 × 400, 1 Stk: 29 × 0,24 + 0,50 = 7,46 € + 2,00 € = 9,46 € < Mindestpreis 12,50 €
        $result = $this->calc([], $rules);
        self::assertSame(12.5, $result->getSaleTotal());
        self::assertEqualsWithDelta(12.5 * 2.0 / 9.46, $result->getLogistics()['sale'], 0.006);

        $data = DemoCatalog::catalog();
        $data['logisticsRules'] = $rules;
        $data['customerGroupRules'][] = ['calculationId' => null, 'customerGroupId' => 'cg-gewerbe', 'surchargePercent' => 10.0, 'priceRounding' => null];
        $gewerbe = (new PriceEngine())->calculate($this->config(['format' => ['width' => 1000, 'height' => 500], 'perMotif' => 10]), new Catalog($data), ContractTest::context('cg-gewerbe'));
        self::assertSame(167.2, $gewerbe->getSaleTotal()); // (150 + 2) × 1,10
        self::assertSame(2.2, $gewerbe->getLogistics()['sale']);
        self::assertEqualsWithDelta(2.2 / 167.2 * 100, $gewerbe->getLogistics()['sharePercent'], 0.01);
    }

    public function testLogisticsNeverLeavesTheEngine(): void
    {
        $result = $this->calc(['materialId' => self::ACRYL, 'format' => ['width' => 1500, 'height' => 1000]]);
        self::assertNotNull($result->getLogistics());

        self::assertNoLogistics($result->toStoreApi());
        $snapshot = $result->toSnapshot();
        self::assertNoLogistics($snapshot['breakdown']);
        // Die Zwischensumme im allgemeinen Rechenweg enthält den Anteil schon (keine Differenz ablesbar)
        $subtotal = array_values(array_filter($snapshot['breakdown']['sale']['lines'], static fn (array $l) => $l['key'] === 'subtotal'))[0]['amount'];
        self::assertEqualsWithDelta($result->getSaleTotal(), $subtotal, 0.006);
    }

    public function testLinearOptionShareIsPartOfThePriceHintAndAddsUp(): void
    {
        $drill = DemoCatalog::id('value.bohrung.ja');
        $linear = [self::rule(['optionValueId' => $drill, 'costType' => 'other', 'form' => 'per_piece', 'value' => 0.20])];

        // ein zusammengefasster Aufpreis statt „+ 0,30 €/Stk + 0,20 €/Stk“
        self::assertSame('+ 0,50 €/Stk', $this->hint('bohrung', 'ja', $linear));

        // die Summe geht auf: Mehrpreis in der Engine = Hinweis × Stück
        $with = $this->calc(['format' => ['width' => 1000, 'height' => 500], 'perMotif' => 10, 'options' => ['bohrung' => 'ja']], $linear);
        $without = $this->calc(['format' => ['width' => 1000, 'height' => 500], 'perMotif' => 10], $linear);
        self::assertEqualsWithDelta(5.0, $with->getSaleTotal() - $without->getSaleTotal(), 0.001);

        // nicht linear (Mindestbetrag) → nicht im Hinweis
        $nonLinear = [self::rule(['optionValueId' => $drill, 'costType' => 'other', 'form' => 'per_piece', 'value' => 0.20, 'minimumAmount' => 5.0])];
        self::assertSame('+ 0,30 €/Stk', $this->hint('bohrung', 'ja', $nonLinear));
    }

    public function testFromPriceContainsLinearMaterialShareOnly(): void
    {
        self::assertSame(29.0, $this->fromPrice(self::ALU, []));
        self::assertSame(30.5, $this->fromPrice(self::ALU, [self::rule(['materialId' => self::ALU, 'form' => 'per_sqm', 'value' => 1.5])]));
        self::assertSame(29.0, $this->fromPrice(self::ALU, [self::rule(['materialId' => self::ALU, 'form' => 'per_sqm', 'value' => 1.5, 'rampFrom' => 1000, 'rampTo' => 1400])]));
        // Demo-Regel Acryl hat eine Rampe → „ab“-Preis unverändert
        self::assertSame(62.0, $this->fromPrice(self::ACRYL, null));
    }

    public function testLogisticsSnapshotSeparatesShareAndProductionTime(): void
    {
        $express = DemoCatalog::id('value.lieferzeit.express');
        $rules = [
            self::rule(['id' => 'alu-base', 'materialId' => self::ALU, 'form' => 'per_position', 'value' => 2.0]),
            self::rule(['id' => 'express', 'optionValueId' => $express, 'costType' => 'express', 'form' => 'per_sqm', 'value' => 4.18, 'minimumAmount' => 25.0, 'minimumScope' => 'order']),
        ];
        // 5 m² Alu Express: Waren 150 € × 1,30 (Express +30 %) = 195 €; Logistik 2 € + max(25, 20,90) = 27 €
        $result = $this->calc(['format' => ['width' => 1000, 'height' => 500], 'perMotif' => 10, 'deliveryValue' => 'express'], $rules);
        $snapshot = $result->toLogisticsSnapshot();

        self::assertSame(222.0, $result->getSaleTotal());
        self::assertSame(27.0, $snapshot['logisticsSale']);
        self::assertSame(45.0, $snapshot['productionTimeSale']); // 195 − 150, ohne Logistik
        self::assertSame(round(27 / 222 * 100, 2), $snapshot['sharePercent']);
        // EK Express: 135,50 × 1,20 = 162,60 → Waren-DB = 222 − 27 − 162,60
        self::assertSame(32.4, $snapshot['goodsContributionMargin']);
        self::assertSame(['materialKey' => 'alu-3mm'], array_intersect_key($snapshot['lines'][0], ['materialKey' => 1, 'deliveryKey' => 1]));
        self::assertSame('express', $snapshot['lines'][1]['deliveryKey']);
        self::assertTrue($snapshot['lines'][1]['minimumApplied']);
        self::assertArrayNotHasKey('materialId', $snapshot['lines'][0]);

        // Standard-Lieferzeit: kein Produktionszeit-Aufschlag; allgemeiner Schnappschuss ohne beides
        self::assertSame(0.0, $this->calc(['format' => ['width' => 1000, 'height' => 500], 'perMotif' => 10], $rules)->toLogisticsSnapshot()['productionTimeSale']);
        self::assertNoLogistics($result->toSnapshot());
        self::assertArrayNotHasKey('productionTime', $result->toSnapshot()['breakdown']['sale']);
    }

    public function testLogisticsSnapshotWithoutRules(): void
    {
        $snapshot = $this->calc(['format' => ['width' => 1000, 'height' => 500], 'perMotif' => 10], [])->toLogisticsSnapshot();
        self::assertSame(0.0, $snapshot['logisticsSale']);
        self::assertSame([], $snapshot['lines']);
        self::assertSame(14.5, $snapshot['goodsContributionMargin']); // 150 − 135,50
    }

    /** Kein Feld und kein Text mit „logistic“ (KONZEPT 3d.4 Nr. 5). */
    public static function assertNoLogistics(mixed $data, string $path = ''): void
    {
        $json = (string) json_encode($data, \JSON_UNESCAPED_UNICODE);
        self::assertDoesNotMatchRegularExpression('/logisti/i', $json, 'Logistikanteil sichtbar in ' . ($path ?: 'Ausgabe'));
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private static function rule(array $values): array
    {
        return $values + [
            'id' => 'rule-' . md5(serialize($values)),
            'calculationId' => null,
            'materialId' => null,
            'optionValueId' => null,
            'costType' => 'base',
            'form' => 'per_position',
            'value' => 0.0,
            'rampFrom' => null,
            'rampTo' => null,
            'thresholdBasis' => null,
            'thresholdFrom' => null,
            'thresholdTo' => null,
            'minimumAmount' => null,
            'minimumScope' => null,
            'validFrom' => null,
            'active' => true,
        ];
    }

    /** @param list<array<string, mixed>>|null $rules */
    private function configurator(?array $rules): array
    {
        $data = DemoCatalog::catalog();
        if ($rules !== null) {
            $data['logisticsRules'] = $rules;
        }
        $out = json_decode((string) json_encode((new ConfiguratorBuilder())->build(new Catalog($data), ContractTest::context())), true);
        self::assertNoLogistics($out, 'configurator');

        return $out;
    }

    /** @param list<array<string, mixed>>|null $rules */
    private function hint(string $group, string $value, ?array $rules): string
    {
        foreach ($this->configurator($rules)['groups'] as $g) {
            if ($g['technicalName'] !== $group) {
                continue;
            }
            foreach ($g['values'] as $v) {
                if ($v['technicalName'] === $value) {
                    return (string) $v['priceHint'];
                }
            }
        }
        self::fail("Wert $group.$value fehlt");
    }

    /** @param list<array<string, mixed>>|null $rules */
    private function fromPrice(string $materialId, ?array $rules): ?float
    {
        foreach ($this->configurator($rules)['materials'] as $material) {
            if ($material['id'] === $materialId) {
                return $material['fromPricePerSqm'] === null ? null : (float) $material['fromPricePerSqm'];
            }
        }
        self::fail("Material $materialId fehlt");
    }

    /** @param list<array<string, mixed>>|null $rules null = Demo-Regeln */
    private function calc(array $overrides, ?array $rules = null): CalculationResult
    {
        $data = DemoCatalog::catalog();
        if ($rules !== null) {
            $data['logisticsRules'] = $rules;
        }

        return (new PriceEngine())->calculate($this->config($overrides), new Catalog($data), ContractTest::context());
    }

    /** @return array<string, mixed> */
    private function config(array $overrides): array
    {
        return $overrides + [
            'schema' => 1,
            'productId' => DemoCatalog::PRODUCT_ID,
            'materialId' => self::ALU,
            'format' => ['width' => 600, 'height' => 400],
            'motifs' => 1,
            'perMotif' => 1,
            'options' => [],
            'deliveryValue' => 'standard',
        ];
    }
}
