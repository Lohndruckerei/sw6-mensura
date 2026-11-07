<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Tests\Backend;

use Lohndruckerei\Mensura\Service\Calculation\Catalog;
use Lohndruckerei\Mensura\Service\Calculation\CalculationContext;
use Lohndruckerei\Mensura\Service\Calculation\DeliveryCalendar;
use Lohndruckerei\Mensura\Service\Calculation\PriceEngine;
use Lohndruckerei\Mensura\Service\Calculation\Tiers;
use Lohndruckerei\Mensura\Service\Demo\DemoCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Einzelregeln der Engine (KONZEPT 4, PRODUKTBEISPIELE) auf Basis der Demo-Daten, gezielt abgewandelt.
 */
final class PriceEngineTest extends TestCase
{
    private const ALU = DemoCatalog::MATERIAL_ALU;
    private const ACRYL = DemoCatalog::MATERIAL_ACRYL;

    public function testComdatekPurchaseSideRunsInParallel(): void
    {
        $result = $this->calc(['format' => ['width' => 1000, 'height' => 500], 'perMotif' => 10, 'options' => ['farbigkeit' => '4-4', 'veredelung' => 'ag-2', 'bohrung' => 'ja', 'schnitt' => 'kontur']]);

        // EK: (26 + 6 + 22 + 1,50) €/m² × 0,5 m² + 0,45 + 0,20 + 0,25 = 28,65 €/Stk → 286,50 €
        self::assertSame(353.0, $result->getSaleTotal());
        self::assertSame(286.5, $result->getPurchaseTotal());
        self::assertSame(66.5, $result->toSnapshot()['contributionMargin']);
        ContractTest::assertNoPurchaseData($result->toStoreApi());
    }

    public function testMotifsMultiplyQuantity(): void
    {
        $result = $this->calc(['format' => ['width' => 1000, 'height' => 500], 'motifs' => 2, 'perMotif' => 5]);
        // 29 × 0,5 + 0,50 = 15,00 €/Stk × 10
        self::assertSame(150.0, $result->getSaleTotal());
        self::assertSame(10, $result->toStoreApi()['price']['quantity']);
    }

    public function testRotationIsAllowed(): void
    {
        $api = self::plain($this->calc(['format' => ['width' => 2500, 'height' => 1000]])->toStoreApi());
        self::assertTrue($api['valid']);
        self::assertSame(['width' => 2500, 'height' => 1000], $api['configuration']['format']);
    }

    public function testCustomerGroupSurchargeAppliesToSaleOnlyAndToMinimum(): void
    {
        $data = DemoCatalog::catalog();
        $data['customerGroupRules'][] = ['calculationId' => null, 'customerGroupId' => 'cg-gewerbe', 'surchargePercent' => 12.5, 'priceRounding' => null];
        $catalog = new Catalog($data);

        $result = (new PriceEngine())->calculate($this->config(['format' => ['width' => 1000, 'height' => 500], 'perMotif' => 10]), $catalog, ContractTest::context('cg-gewerbe'));
        self::assertSame(168.75, $result->getSaleTotal()); // 150 × 1,125
        self::assertSame(135.5, $result->getPurchaseTotal()); // (26 × 0,5 + 0,45 + 0,10) × 10, ohne Aufschlag

        $minimum = self::plain((new PriceEngine())->calculate($this->config([]), $catalog, ContractTest::context('cg-gewerbe'))->toStoreApi());
        self::assertSame(14.06, $minimum['price']['total']); // 12,50 × 1,125 = 14,0625
        self::assertSame('Mindestpreis 14,06 € je Position', $minimum['hints'][0]['message']);
    }

    public function testLegacyAndSplitTiersAreRead(): void
    {
        $legacy = Tiers::forSide([['from' => 0, 'purchase' => 4.8, 'sale' => 5.55], ['from' => 10, 'purchase' => 4.5, 'sale' => 5.25], ['from' => 25, 'discount' => 5]], 'total_area', 'sale');
        self::assertSame('total_area', $legacy['basis']);
        self::assertSame(5.25, Tiers::pick($legacy, 15.0)['price']);
        self::assertSame(5.0, Tiers::pick($legacy, 30.0)['discount']);

        $split = ['purchase' => ['basis' => 'piece_area', 'steps' => [['from' => 0, 'price' => 7.5], ['from' => 1, 'price' => 6.5]]], 'sale' => ['basis' => 'none']];
        self::assertNull(Tiers::forSide($split, null, 'sale'));
        self::assertSame(6.5, Tiers::pick(Tiers::forSide($split, null, 'purchase'), 2.0)['price']);
    }

    public function testMaterialAreaTierWithSeparatePurchaseBasisAndBestPriceHint(): void
    {
        // Banner-artig: VK Flächenstaffel über die Position, EK nach Stückfläche (Blockout, Lücke 1)
        $data = DemoCatalog::catalog();
        $data['materials'][self::ALU]['tiers'] = [
            'sale' => ['basis' => 'total_area', 'steps' => [['from' => 0, 'price' => 29.0], ['from' => 10, 'price' => 20.0]]],
            'purchase' => ['basis' => 'piece_area', 'steps' => [['from' => 0, 'price' => 26.0], ['from' => 1, 'price' => 18.0]]],
        ];
        $result = (new PriceEngine())->calculate($this->config(['format' => ['width' => 1000, 'height' => 1000], 'perMotif' => 9]), new Catalog($data), ContractTest::context());
        $api = self::plain($result->toStoreApi());

        self::assertSame(265.5, $result->getSaleTotal()); // 9 m² × 29 + 9 × 0,50
        self::assertSame(166.95, $result->getPurchaseTotal()); // 1 m² je Stück → 18 €/m²: 9 × (18 + 0,45 + 0,10)
        self::assertSame('price.best_price', $api['hints'][0]['code']); // 10 Stk = 10 m² × 20 + 5 = 205 € < 265,50 €
        self::assertSame(['perMotif' => 10], $api['hints'][0]['fix']);
        self::assertStringContainsString('205,00 €', $api['hints'][0]['message']);

        $data['calculations'][DemoCatalog::id('calculation.aluverbund')]['bestPriceMode'] = 'apply';
        $applied = (new PriceEngine())->calculate($this->config(['format' => ['width' => 1000, 'height' => 1000], 'perMotif' => 9]), new Catalog($data), ContractTest::context());
        self::assertSame(205.0, $applied->getSaleTotal());
        self::assertSame('price.best_price_applied', $applied->toStoreApi()['hints'][0]['code']);
    }

    public function testBaseAreaReplacesMaterialPrice(): void
    {
        // Sticker-Zuschnitt (Lücke 9): Wert „kontur“ ersetzt die Preisbasis durch 40 €/m² fest
        $data = DemoCatalog::catalog();
        foreach ($data['calculations'][DemoCatalog::id('calculation.aluverbund')]['optionValues'] as &$row) {
            if ($row['optionValueId'] === DemoCatalog::id('value.schnitt.kontur')) {
                $row['priceComponents'] = [['type' => 'base_area', 'purchase' => 30.0, 'sale' => 40.0]];
            }
        }
        unset($row);
        $result = (new PriceEngine())->calculate($this->config(['format' => ['width' => 1000, 'height' => 1000], 'options' => ['schnitt' => 'kontur']]), new Catalog($data), ContractTest::context());
        self::assertSame(40.5, $result->getSaleTotal()); // 40 × 1 m² + 0,50
    }

    public function testPriceTableWithIntermediateQuantityAndSetupFee(): void
    {
        // Flyer-artig (Lücke 2): Gesamtpreis je Auflage, Zwischenmengen über die Zeile darunter
        $data = DemoCatalog::catalog();
        $calcId = DemoCatalog::id('calculation.aluverbund');
        $data['calculations'][$calcId]['priceTables'] = [[
            'id' => 't1', 'name' => 'A4 4/0', 'materialId' => null, 'optionValueIds' => [DemoCatalog::id('value.farbigkeit.4-0')],
            'priceType' => 'total', 'active' => true, 'position' => 0,
            'rows' => [
                ['quantity' => 100, 'pricePurchase' => 8.0, 'priceSale' => 11.0],
                ['quantity' => 250, 'pricePurchase' => 18.0, 'priceSale' => 25.0],
                ['quantity' => 500, 'pricePurchase' => 30.0, 'priceSale' => 45.0],
            ],
        ]];
        $data['calculations'][$calcId]['priceComponents'] = [];
        $data['calculations'][$calcId]['minimumPriceSale'] = null;
        $data['calculations'][$calcId]['setupPriceSale'] = 5.0;
        $data['calculations'][$calcId]['setupPricePurchase'] = 2.0;
        $data['calculations'][$calcId]['bestPriceMode'] = 'none';

        $result = (new PriceEngine())->calculate($this->config(['format' => ['width' => 210, 'height' => 297], 'perMotif' => 300]), new Catalog($data), ContractTest::context());
        self::assertSame(35.0, $result->getSaleTotal()); // 300 × 0,10 + 5,00 Einrichtung
        self::assertSame(53.6, $result->getPurchaseTotal()); // 300 × (0,072 + 0,10 EK rechteckig) + 2,00

        // keine passende Tabelle (4/4 hat keine) → kein Preis
        $none = self::plain((new PriceEngine())->calculate($this->config(['options' => ['farbigkeit' => '4-4']]), new Catalog($data), ContractTest::context())->toStoreApi());
        self::assertFalse($none['valid']);
        self::assertSame('price.not_available', $none['errors'][0]['code']);
    }

    public function testProfileRulesFixedHiddenRestrictAndSurcharge(): void
    {
        // Duschrückwand-artig (KONZEPT 3.6): Veredelung fest, Lochbohrung ausgeblendet = Nein, Schnitt nur rechteckig, +10 %
        $data = DemoCatalog::catalog();
        $data['profile']['surchargePercent'] = 10.0;
        $data['profile']['options'] = [
            ['optionGroupId' => DemoCatalog::id('group.veredelung'), 'materialId' => null, 'mode' => 'fixed', 'valueIds' => [], 'defaultValueId' => DemoCatalog::id('value.veredelung.lam-matt'), 'position' => null],
            ['optionGroupId' => DemoCatalog::id('group.bohrung'), 'materialId' => null, 'mode' => 'hidden', 'valueIds' => [], 'defaultValueId' => DemoCatalog::id('value.bohrung.nein'), 'position' => null],
            ['optionGroupId' => DemoCatalog::id('group.schnitt'), 'materialId' => null, 'mode' => 'restrict', 'valueIds' => [DemoCatalog::id('value.schnitt.recht')], 'defaultValueId' => null, 'position' => null],
        ];
        $api = self::plain((new PriceEngine())->calculate(
            $this->config(['format' => ['width' => 1000, 'height' => 1000], 'options' => ['veredelung' => 'ag-2', 'bohrung' => 'ja', 'schnitt' => 'kontur']]),
            new Catalog($data),
            ContractTest::context(),
        )->toStoreApi());

        self::assertSame(['farbigkeit', 'veredelung', 'schnitt'], $api['activeGroups']);
        self::assertSame(['farbigkeit' => '4-0', 'veredelung' => 'lam-matt', 'schnitt' => 'recht', 'bohrung' => 'nein'], $api['configuration']['options']);
        self::assertSame(['option.fixed', 'option.reset'], array_column($api['hints'], 'code'));
        self::assertSame(41.25, $api['price']['total']); // (29 + 8) × 1 + 0,50 = 37,50 × 1,10
    }

    public function testConditionsExcludeAndRequire(): void
    {
        $data = DemoCatalog::catalog();
        foreach ($data['calculations'][DemoCatalog::id('calculation.aluverbund')]['optionValues'] as &$row) {
            if ($row['optionValueId'] === DemoCatalog::id('value.veredelung.ag-2')) {
                // Anti-Graffiti nicht mit unbedruckt
                $row['conditions'] = ['excludes' => [DemoCatalog::id('value.farbigkeit.0-0')]];
            }
            if ($row['optionValueId'] === DemoCatalog::id('value.schnitt.kontur')) {
                // Konturschnitt setzt eine Veredelung „Laminat matt“ voraus
                $row['conditions'] = ['requires' => [DemoCatalog::id('value.veredelung.lam-matt')]];
            }
        }
        unset($row);
        $api = self::plain((new PriceEngine())->calculate(
            $this->config(['options' => ['farbigkeit' => '0-0', 'veredelung' => 'ag-2', 'schnitt' => 'kontur']]),
            new Catalog($data),
            ContractTest::context(),
        )->toStoreApi());

        self::assertSame('keine', $api['configuration']['options']['veredelung']);
        self::assertSame('recht', $api['configuration']['options']['schnitt']);
        self::assertSame(['option.excluded', 'option.excluded', 'price.minimum_applied'], array_column($api['hints'], 'code'));
    }

    public function testSplitModeSharesQuantityAndChargesPerMotif(): void
    {
        $data = DemoCatalog::catalog();
        $calcId = DemoCatalog::id('calculation.aluverbund');
        $data['calculations'][$calcId]['motifMode'] = 'split';
        $data['calculations'][$calcId]['priceComponents'][] = ['type' => 'per_motif', 'purchase' => 1.0, 'sale' => 2.0];
        $data['calculations'][$calcId]['minimumPriceSale'] = null;
        $result = (new PriceEngine())->calculate($this->config(['format' => ['width' => 100, 'height' => 100], 'motifs' => 5, 'perMotif' => 100]), new Catalog($data), ContractTest::context());
        // 100 Stk gesamt × (29 × 0,01 + 0,50) + 5 Sorten × 2,00
        self::assertSame(89.0, $result->getSaleTotal());
        self::assertSame(100, $result->toStoreApi()['price']['quantity']);
    }

    public function testQuantityAndMotifLimits(): void
    {
        $data = DemoCatalog::catalog();
        $data['profile']['maxQuantity'] = 20;
        $data['calculations'][DemoCatalog::id('calculation.aluverbund')]['maxMotifs'] = 3;
        $api = self::plain((new PriceEngine())->calculate($this->config(['motifs' => 4, 'perMotif' => 10]), new Catalog($data), ContractTest::context())->toStoreApi());
        self::assertFalse($api['valid']);
        self::assertSame(['quantity.motifs_max', 'quantity.max'], array_column($api['errors'], 'code'));
        self::assertSame(['perMotif' => 5], $api['errors'][1]['fix']);
        self::assertArrayNotHasKey('price', $api);
    }

    public function testDeliveryAvailabilityAndReset(): void
    {
        $api = self::plain($this->calc(['perMotif' => 60, 'deliveryValue' => 'express'])->toStoreApi());
        self::assertSame('standard', $api['configuration']['deliveryValue']);
        self::assertSame('delivery.reset', $api['hints'][0]['code']);
        self::assertFalse($api['delivery'][1]['available']);
        self::assertSame('nur bis 50 Stück', $api['delivery'][1]['reason']);

        $express = $this->calc(['format' => ['width' => 1000, 'height' => 500], 'perMotif' => 10, 'deliveryValue' => 'express']);
        self::assertSame(195.0, $express->getSaleTotal()); // 150 × 1,30
    }

    public function testMaterialSwitchKeepsSharedGroupsAndUnknownMaterialFallsBack(): void
    {
        $api = self::plain($this->calc(['materialId' => self::ACRYL, 'options' => ['farbigkeit' => '0-0', 'bohrung' => 'ja', 'kanten' => 'ja']])->toStoreApi());
        self::assertSame(['farbigkeit' => '0-0', 'kanten' => 'ja', 'bohrung' => 'ja', 'schnitt' => 'recht'], $api['configuration']['options']);
        self::assertSame([], $api['hints']);

        $fallback = self::plain($this->calc(['materialId' => str_repeat('f', 32)])->toStoreApi());
        self::assertSame(self::ALU, $fallback['configuration']['materialId']);
        self::assertSame('material.reset', $fallback['hints'][0]['code']);
    }

    public function testDeliveryCalendarRespectsCutoffWeekendAndHolidays(): void
    {
        $tz = new \DateTimeZone('Europe/Berlin');
        $before = new DeliveryCalendar(new CalculationContext(now: new \DateTimeImmutable('2026-11-06 11:00', $tz)));
        self::assertSame('2026-11-09', $before->dateFor(1)->format('Y-m-d'));
        $after = new DeliveryCalendar(new CalculationContext(now: new \DateTimeImmutable('2026-11-06 11:45', $tz)));
        self::assertSame('2026-11-10', $after->dateFor(1)->format('Y-m-d'));
        $holiday = new DeliveryCalendar(new CalculationContext(now: new \DateTimeImmutable('2026-12-23 09:00', $tz), holidays: ['2026-12-24', '2026-12-25']));
        self::assertSame('2026-12-28', $holiday->dateFor(1)->format('Y-m-d'));
    }

    public function testAreaPerPositionWithMinimumAndSteps(): void
    {
        // Folie (Entscheidung 07.11.2026): Fläche = Länge × Breite × Stück der ganzen Position, bis 1 m² gleicher
        // Grundpreis, darüber in Schritten (hier 0,1 m²); Optionen je m² nutzen dieselbe abgerechnete Fläche.
        $data = DemoCatalog::catalog();
        $calcId = DemoCatalog::id('calculation.aluverbund');
        $data['materials'][self::ALU]['priceSale'] = 12.0;
        $data['materials'][self::ALU]['pricePurchase'] = 7.0;
        $data['calculations'][$calcId]['priceComponents'] = [['type' => 'per_piece', 'purchase' => 0.05, 'sale' => 0.10]];
        $data['calculations'][$calcId]['minimumPriceSale'] = null;
        $data['calculations'][$calcId]['minimumPricePurchase'] = null;
        $data['calculations'][$calcId]['minArea'] = 1.0;
        $data['calculations'][$calcId]['areaRounding'] = 0.1;
        $data['calculations'][$calcId]['areaScope'] = 'total';

        // 5 × 0,1 m² = 0,5 m² → abgerechnet 1,0 m²: 12,00 + 5 × 0,10
        $small = (new PriceEngine())->calculate($this->config(['format' => ['width' => 250, 'height' => 400], 'perMotif' => 5]), new Catalog($data), ContractTest::context());
        self::assertSame(12.5, $small->getSaleTotal());
        self::assertSame(['piece' => 0.1, 'total' => 0.5, 'billed' => 1.0], array_intersect_key($small->getAreas(), ['piece' => 0, 'total' => 0, 'billed' => 0]));
        self::assertSame(0.5, self::plain($small->toStoreApi())['price']['area']);

        // 3 × 0,41 m² = 1,23 m² → 1,3 m²; Laminat matt (+8 €/m²) auf dieselbe Fläche: (12 + 8) × 1,3 + 3 × 0,10
        $config = $this->config(['format' => ['width' => 410, 'height' => 1000], 'perMotif' => 3, 'options' => ['veredelung' => 'lam-matt']]);
        $large = (new PriceEngine())->calculate($config, new Catalog($data), ContractTest::context());
        self::assertSame(26.3, $large->getSaleTotal());
        self::assertSame(1.3, $large->getAreas()['billed']);
        self::assertSame(16.7, $large->getPurchaseTotal()); // (7 + 5,5) × 1,3 + 3 × 0,05 + 3 × 0,10 EK rechteckig

        // zum Vergleich je Stück: jedes Stück mindestens 1 m² → 3 m²
        $data['calculations'][$calcId]['areaScope'] = 'piece';
        $perPiece = (new PriceEngine())->calculate($config, new Catalog($data), ContractTest::context());
        self::assertSame(60.3, $perPiece->getSaleTotal());
    }

    public function testBasePriceMinimumAreaAndMinimumPricePerMaterial(): void
    {
        // Grundpreise und Mindestflächen je Material variabel: Folie A ab 1 m² (Kalkulation), Folie B ab 0,5 m² (Material)
        $data = DemoCatalog::catalog();
        $calcId = DemoCatalog::id('calculation.aluverbund');
        $data['calculations'][$calcId]['priceComponents'] = [];
        $data['calculations'][$calcId]['minimumPriceSale'] = null;
        $data['calculations'][$calcId]['minArea'] = 1.0;
        $data['calculations'][$calcId]['areaRounding'] = 0.1;
        $data['materials'][self::ALU]['priceSale'] = 10.0;
        $config = $this->config(['format' => ['width' => 200, 'height' => 300], 'perMotif' => 5]); // 5 × 0,06 = 0,3 m²

        $folieA = (new PriceEngine())->calculate($config, new Catalog($data), ContractTest::context());
        self::assertSame(1.0, $folieA->getAreas()['billed']);
        self::assertSame(10.0, $folieA->getSaleTotal());

        $data['materials'][self::ALU]['minArea'] = 0.5;
        $folieB = (new PriceEngine())->calculate($config, new Catalog($data), ContractTest::context());
        self::assertSame(0.5, $folieB->getAreas()['billed']);
        self::assertSame(0.5, $folieB->getAreas()['minArea']);
        self::assertSame(5.0, $folieB->getSaleTotal());

        // Grundpreis je Stück am Material (Mesh-Banner 0,20 €/Stk) und eigener Mindestpreis des Materials
        $data['materials'][self::ALU]['priceComponents'] = [['type' => 'per_piece', 'purchase' => 0.1, 'sale' => 0.20]];
        $data['materials'][self::ALU]['minimumPriceSale'] = 8.90;
        $minimum = (new PriceEngine())->calculate($config, new Catalog($data), ContractTest::context());
        self::assertSame(8.9, $minimum->getSaleTotal()); // 0,5 × 10 + 5 × 0,20 = 6,00 < 8,90
        self::assertSame('price.minimum_applied', self::plain($minimum->toStoreApi())['hints'][0]['code']);
        $larger = (new PriceEngine())->calculate($this->config(['format' => ['width' => 1000, 'height' => 1000], 'perMotif' => 2]), new Catalog($data), ContractTest::context());
        self::assertSame(20.4, $larger->getSaleTotal()); // 2 m² × 10 + 2 × 0,20 – Mindestpreis greift nicht mehr
    }

    public function testEnglishTexts(): void
    {
        $api = self::plain((new PriceEngine())->calculate($this->config([]), new Catalog(DemoCatalog::catalog('en')), ContractTest::context(null, 'en'))->toStoreApi());
        self::assertSame('Minimum price 12.50 € per item', $api['hints'][0]['message']);
    }

    /** Store-API-Antwort als reines Array (wie nach JSON). */
    private static function plain(array $api): array
    {
        return json_decode((string) json_encode($api), true);
    }

    private function calc(array $overrides): \Lohndruckerei\Mensura\Service\Calculation\CalculationResult
    {
        return (new PriceEngine())->calculate($this->config($overrides), new Catalog(DemoCatalog::catalog()), ContractTest::context(), true);
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
