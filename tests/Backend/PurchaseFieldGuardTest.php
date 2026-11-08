<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Tests\Backend;

use Lohndruckerei\Mensura\Service\Security\PurchaseFieldGuard;
use PHPUnit\Framework\TestCase;

/**
 * EK-Schutz (KONZEPT 3b): Lesen ohne EK, Schreiben ohne Recht erhält gespeicherte EK, Suche nach EK gesperrt.
 * Der Subscriber (Shopware-Anbindung) wird zusätzlich über die Admin-API im Shop geprüft.
 */
final class PurchaseFieldGuardTest extends TestCase
{
    public function testStripRemovesOnlyPurchaseParts(): void
    {
        $stripped = PurchaseFieldGuard::strip('ld_mensura_material', [
            'pricePurchase' => 26.0,
            'minimumPricePurchase' => 9.8,
            'priceComponents' => [['type' => 'per_piece', 'purchase' => 0.2, 'sale' => 0.3]],
            'tiers' => ['purchase' => ['basis' => 'quantity', 'steps' => [['from' => 10, 'price' => 20]]], 'sale' => ['basis' => 'none']],
        ]);

        self::assertNull($stripped['pricePurchase']);
        self::assertNull($stripped['minimumPricePurchase']);
        self::assertSame([['type' => 'per_piece', 'purchase' => null, 'sale' => 0.3]], $stripped['priceComponents']);
        self::assertSame(['sale' => ['basis' => 'none']], $stripped['tiers']);
        self::assertArrayNotHasKey('name', $stripped, 'nur geschützte Felder werden angefasst');
    }

    public function testStripLegacyTiersAndBreakdown(): void
    {
        self::assertSame(
            [['from' => 10, 'sale' => 5.25], ['from' => 50, 'discount' => 5]],
            PurchaseFieldGuard::stripValue('tiers', [['from' => 10, 'purchase' => 4.8, 'sale' => 5.25], ['from' => 50, 'discount' => 5]]),
        );
        self::assertSame(
            ['sale' => ['total' => 353.0]],
            PurchaseFieldGuard::stripValue('breakdown', ['purchase' => ['total' => 286.5], 'sale' => ['total' => 353.0], 'contributionMargin' => 66.5]),
        );
    }

    public function testSaleChangeKeepsStoredPurchaseComponents(): void
    {
        $old = [['type' => 'per_area', 'purchase' => 3.0, 'sale' => 4.0], ['type' => 'per_piece', 'purchase' => 0.5, 'sale' => 0.7]];
        // Benutzer ohne EK-Recht ändert einen VK, entfernt den ersten Anteil und fügt einen neuen hinzu
        $new = [['type' => 'per_piece', 'purchase' => null, 'sale' => 0.9], ['type' => 'per_order', 'purchase' => 99, 'sale' => 5.0]];

        self::assertSame(
            [['type' => 'per_piece', 'purchase' => 0.5, 'sale' => 0.9], ['type' => 'per_order', 'purchase' => null, 'sale' => 5.0]],
            PurchaseFieldGuard::mergeJson('components', $new, $old),
        );
    }

    public function testNewComponentsWithoutStoredValueGetNoPurchase(): void
    {
        self::assertSame(
            [['type' => 'per_piece', 'purchase' => null, 'sale' => 1.0]],
            PurchaseFieldGuard::mergeJson('components', [['type' => 'per_piece', 'purchase' => 0.8, 'sale' => 1.0]], null),
        );
    }

    public function testTiersKeepStoredPurchaseSide(): void
    {
        $old = ['purchase' => ['basis' => 'quantity', 'steps' => [['from' => 10, 'price' => 20]]], 'sale' => ['basis' => 'none']];
        $new = ['purchase' => ['basis' => 'none'], 'sale' => ['basis' => 'quantity', 'steps' => [['from' => 10, 'price' => 25]]]];

        self::assertSame(
            ['sale' => ['basis' => 'quantity', 'steps' => [['from' => 10, 'price' => 25]]], 'purchase' => $old['purchase']],
            PurchaseFieldGuard::mergeJson('tiers', $new, $old),
        );
    }

    public function testLegacyStoredTiersBecomePurchaseSide(): void
    {
        $old = [['from' => 10, 'purchase' => 4.8, 'sale' => 5.25]];
        $new = ['sale' => ['basis' => 'quantity', 'steps' => [['from' => 10, 'price' => 5.5]]]];

        self::assertSame(
            ['purchase' => ['basis' => 'quantity', 'steps' => [['from' => 10, 'price' => 4.8]]]],
            array_intersect_key(PurchaseFieldGuard::mergeJson('tiers', $new, $old, 'quantity'), ['purchase' => true]),
        );
    }

    public function testClearedSaleTiersKeepPurchase(): void
    {
        $old = ['purchase' => ['basis' => 'quantity', 'steps' => [['from' => 10, 'price' => 20]]]];

        self::assertSame(['purchase' => $old['purchase']], PurchaseFieldGuard::mergeJson('tiers', null, $old));
        self::assertNull(PurchaseFieldGuard::mergeJson('tiers', null, null));
    }

    public function testSearchOnPurchaseFieldsIsDetected(): void
    {
        self::assertTrue(PurchaseFieldGuard::touchesProtectedField('ld_mensura_material', ['ld_mensura_material.pricePurchase']));
        self::assertTrue(PurchaseFieldGuard::touchesProtectedField('ld_mensura_calculation', ['materials.minimumPricePurchase']));
        self::assertTrue(PurchaseFieldGuard::touchesProtectedField('ld_mensura_order_line_item', ['purchaseTotal']));
        self::assertTrue(PurchaseFieldGuard::touchesProtectedField('ld_mensura_material', ['priceComponents']));
        self::assertTrue(PurchaseFieldGuard::touchesProtectedField('product', ['ldMensura.materials.material.pricePurchase']));
        self::assertFalse(PurchaseFieldGuard::touchesProtectedField('ld_mensura_material', ['priceSale', 'active', 'translations.name']));
        self::assertFalse(PurchaseFieldGuard::touchesProtectedField('ld_mensura_order_line_item', ['saleTotal', 'orderLineItem.order.orderDateTime']));
        self::assertFalse(PurchaseFieldGuard::touchesProtectedField('product', ['tiers', 'price']));
    }
}
