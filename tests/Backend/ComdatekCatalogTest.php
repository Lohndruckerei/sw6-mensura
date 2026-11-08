<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Tests\Backend;

use Lohndruckerei\Mensura\Service\Demo\ComdatekCatalog;
use Lohndruckerei\Mensura\Service\Demo\DemoCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Comdatek-Demo (mensura:demo:comdatek): VK-Regel, Logistikpauschale und Verweise der Daten untereinander.
 * Gerechnet wird im Shop (Store-API, tests/Backend/Shop/comdatek-demo.php).
 */
final class ComdatekCatalogTest extends TestCase
{
    public function testSalePriceIsPurchaseWith25PercentMargin(): void
    {
        self::assertSame(34.67, ComdatekCatalog::vk(26.0));
        self::assertSame(-5.33, ComdatekCatalog::vk(-4.0));
        self::assertSame(0.47, ComdatekCatalog::vk(0.35));
        self::assertSame(16.67, ComdatekCatalog::vk(12.50));
        self::assertSame(['type' => 'per_area', 'purchase' => 6.0, 'sale' => 8.0], ComdatekCatalog::component('per_area', 6.0));
    }

    public function testLogisticsComponentFromVecturaProposal(): void
    {
        self::assertSame(
            ['type' => 'per_area', 'purchase' => 0.5665, 'sale' => 0.71, 'label' => 'Logistikpauschale'],
            ComdatekCatalog::logistics('alu'),
        );
        self::assertSame(10.6842, ComdatekCatalog::logistics('acryl')['purchase']);
    }

    public function testTiersHaveSalePerStep(): void
    {
        $tiers = ComdatekCatalog::tiers(['total_area', [0 => 5.5, 10 => 4.95]]);

        self::assertSame(['basis' => 'total_area', 'steps' => [['from' => 0.0, 'price' => 5.5], ['from' => 10.0, 'price' => 4.95]]], $tiers['purchase']);
        self::assertSame([['from' => 0.0, 'price' => 7.33], ['from' => 10.0, 'price' => 6.6]], $tiers['sale']['steps']);
    }

    public function testReferencesAreConsistent(): void
    {
        $values = DemoCatalog::valueDefinitions();
        foreach (ComdatekCatalog::newValues() as $group => $newValues) {
            $values[$group] = ($values[$group] ?? []) + $newValues;
        }
        $groups = ComdatekCatalog::groups();
        $materialKeys = [];
        $technicalNames = [];

        foreach (ComdatekCatalog::calculations() as $key => $calculation) {
            self::assertArrayHasKey($calculation['logistics'], ComdatekCatalog::LOGISTICS, $key);
            foreach ($calculation['sets'] as $group) {
                self::assertArrayHasKey($group, $groups, "$key: Set-Gruppe $group");
            }
            foreach ($calculation['materials'] as $material => $definition) {
                self::assertNotContains($material, $materialKeys, "Material $material doppelt");
                $materialKeys[] = $material;
                $technicalNames[] = 'cd-' . $material;
                self::assertGreaterThan(0, $definition[2], $material);
            }
            foreach ($calculation['options'] as $group => $options) {
                self::assertContains($group, $calculation['sets'], "$key: Gruppe $group fehlt im Set");
                foreach ($options as $value => $spec) {
                    self::assertArrayHasKey($value, $values[$group] ?? [], "$key: Wert $group.$value");
                    foreach (array_merge($spec['only'] ?? [], $spec['except'] ?? []) as $material) {
                        self::assertArrayHasKey($material, $calculation['materials'], "$key: $group.$value → $material");
                    }
                }
                $defaults = array_filter($options, static fn (array $spec) => ($spec['default'] ?? false) === true);
                self::assertLessThanOrEqual(1, \count($defaults), "$key: mehrere Standardwerte in $group");
            }
            $ids = array_column(ComdatekCatalog::assignments($key), 'id');
            self::assertSame(\count($ids), \count(array_unique($ids)), "$key: Zuordnungs-IDs eindeutig");
        }
        foreach ($technicalNames as $name) {
            self::assertLessThanOrEqual(64, \strlen($name));
        }

        $numbers = [];
        foreach (ComdatekCatalog::products() as $key => [$number, , , $layout, $entries]) {
            self::assertNotContains($number, $numbers);
            $numbers[] = $number;
            self::assertContains($layout, ['compact', 'guided']);
            foreach ($entries as [$calculation, $material]) {
                self::assertArrayHasKey($calculation, ComdatekCatalog::calculations(), "$key → $calculation");
                if ($material !== null) {
                    self::assertArrayHasKey($material, ComdatekCatalog::calculations()[$calculation]['materials'], "$key → $material");
                }
            }
        }
    }

    public function testExceptBecomesRowsPerAllowedMaterial(): void
    {
        $rows = array_filter(
            ComdatekCatalog::assignments('banner'),
            static fn (array $row) => $row['optionValueId'] === ComdatekCatalog::valueId('konfektion', 'saum-oesen'),
        );
        $materials = array_column($rows, 'materialId');

        // Saum + Ösen nicht bei Blockout und Linoleum, keine allgemeine Zeile
        self::assertCount(3, $materials);
        self::assertNotContains(null, $materials);
        self::assertNotContains(ComdatekCatalog::materialId('banner-blockout-610'), $materials);
        self::assertSame(DemoCatalog::id('value.farbigkeit.4-4'), ComdatekCatalog::valueId('farbigkeit', '4-4'), 'gemeinsame Bibliothek');
    }
}
