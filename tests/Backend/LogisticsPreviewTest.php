<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Tests\Backend;

use Lohndruckerei\Mensura\Service\Calculation\Catalog;
use Lohndruckerei\Mensura\Service\Calculation\LogisticsPreview;
use Lohndruckerei\Mensura\Service\Calculation\PriceEngine;
use Lohndruckerei\Mensura\Service\Demo\DemoCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Vorschau der Admin-Karte „Logistikanteil“ (KONZEPT 3d.6): Entwurf neben gespeicherten Regeln, Warnschwelle.
 */
final class LogisticsPreviewTest extends TestCase
{
    public function testDraftChangesTheSampleButNotTheSavedPrice(): void
    {
        $catalog = new Catalog(DemoCatalog::catalog());
        $draft = [[
            'id' => DemoCatalog::id('logistics.acryl.bulky'),
            'materialId' => DemoCatalog::MATERIAL_ACRYL,
            'costType' => 'bulky',
            'form' => 'per_sqm',
            'value' => '20',          // aus dem Browser als Text
            'rampFrom' => 1000,
            'rampTo' => 1400,
            'unknownField' => 'x',    // wird verworfen
        ]];
        $samples = [
            ['materialId' => DemoCatalog::MATERIAL_ACRYL, 'format' => ['width' => 1500, 'height' => 1000]],
            ['materialId' => DemoCatalog::MATERIAL_ACRYL, 'format' => ['width' => 600, 'height' => 400]],
        ];

        $out = (new LogisticsPreview(new PriceEngine()))->preview($catalog, $draft, $samples, ContractTest::context(), 15.0);
        $big = $out['samples'][0];

        self::assertTrue($big['valid']);
        self::assertSame(120.16, $big['totalSaved']);           // gespeicherte Demo-Regel 17,77 €/m²
        self::assertSame(123.5, $big['total']);                 // Entwurf 20 €/m² × 1,5 m² + 93,50 €
        self::assertSame(30.0, $big['logistics']);
        self::assertSame(93.5, $big['totalWithoutLogistics']);
        self::assertTrue($big['warn']);                         // 30 / 123,50 = 24 % > 15 %
        self::assertSame(1500.0, $big['longestSide']);

        $small = $out['samples'][1];
        self::assertSame(0.0, $small['logistics']);
        self::assertFalse($small['warn']);
        self::assertSame(15.0, $out['warnPercent']);
    }

    public function testNewDraftReplacesSavedRuleOfTheSameCostType(): void
    {
        // neuer Entwurf (andere ID) für dieselbe Kostenart: gilt in der Vorschau statt der gespeicherten Regel
        $draft = [['materialId' => DemoCatalog::MATERIAL_ACRYL, 'costType' => 'bulky', 'form' => 'per_sqm', 'value' => 20, 'rampFrom' => 1000, 'rampTo' => 1400]];
        $samples = [['materialId' => DemoCatalog::MATERIAL_ACRYL, 'format' => ['width' => 1500, 'height' => 1000]]];
        $out = (new LogisticsPreview(new PriceEngine()))->preview(new Catalog(DemoCatalog::catalog()), $draft, $samples, ContractTest::context(), 15.0);

        self::assertSame(123.5, $out['samples'][0]['total']);
        self::assertSame(120.16, $out['samples'][0]['totalSaved']);
        self::assertCount(1, $out['samples'][0]['lines']);
    }

    public function testSanitizeKeepsOnlyKnownFields(): void
    {
        $clean = LogisticsPreview::sanitize(['value' => '1.5', 'rampFrom' => '1000', 'validFrom' => '2026-12-01T00:00:00+00:00', 'evil' => 'x']);
        self::assertSame(1.5, $clean['value']);
        self::assertSame(1000, $clean['rampFrom']);
        self::assertSame('2026-12-01', $clean['validFrom']);
        self::assertTrue($clean['active']);
        self::assertArrayNotHasKey('evil', $clean);
    }
}
