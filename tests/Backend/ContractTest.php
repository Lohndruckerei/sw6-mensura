<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Tests\Backend;

use Lohndruckerei\Mensura\Service\Calculation\CalculationContext;
use Lohndruckerei\Mensura\Service\Calculation\Catalog;
use Lohndruckerei\Mensura\Service\Calculation\ConfiguratorBuilder;
use Lohndruckerei\Mensura\Service\Calculation\PriceEngine;
use Lohndruckerei\Mensura\Service\Demo\DemoCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Vertragstest (docs/contract): Die Engine rechnet jeden Beispielfall aus fixtures/calculate und muss das
 * Sollergebnis treffen – Preise auf den Cent. Die Antworten landen zusätzlich in build/contract/ und werden
 * von check-all.sh bzw. der GitHub-Prüfung gegen die JSON-Schemas validiert.
 */
final class ContractTest extends TestCase
{
    private const CONTRACT = __DIR__ . '/../../docs/contract';
    private const BUILD = __DIR__ . '/../../build/contract';

    /** Felder, die das Backend zusätzlich liefern darf, wenn ein Beispielfall sie nicht vorgibt. */
    private const OPTIONAL = ['delivery', 'matrix'];

    /** @return iterable<string, array{string}> */
    public static function calculateFixtures(): iterable
    {
        foreach (glob(self::CONTRACT . '/fixtures/calculate/*.request.json') ?: [] as $file) {
            $name = basename($file, '.request.json');
            yield $name => [$name];
        }
    }

    #[DataProvider('calculateFixtures')]
    public function testCalculateFixture(string $name): void
    {
        $request = self::json(self::CONTRACT . "/fixtures/calculate/$name.request.json");
        $expected = self::json(self::CONTRACT . "/fixtures/calculate/$name.expected.json");

        $result = (new PriceEngine())->calculate($request, new Catalog(DemoCatalog::catalog('de', $request['productId'])), self::context(), true);
        $actual = self::roundTrip($result->toStoreApi());
        self::write("calculate/$name.json", $result->toStoreApi());

        foreach (self::OPTIONAL as $key) {
            if (!\array_key_exists($key, $expected)) {
                unset($actual[$key]);
            }
        }
        self::assertEquals($expected, $actual, "Beispielfall $name");
        self::assertSame(
            json_encode($expected['price'] ?? null),
            json_encode($actual['price'] ?? null),
            "Preise $name auf den Cent",
        );
        self::assertNoPurchaseData($result->toStoreApi());
    }

    /** @return iterable<string, array{string}> */
    public static function configuratorFixtures(): iterable
    {
        foreach (glob(self::CONTRACT . '/fixtures/configurator/*.json') ?: [] as $file) {
            yield basename($file, '.json') => [basename($file, '.json')];
        }
    }

    #[DataProvider('configuratorFixtures')]
    public function testConfiguratorFixture(string $name): void
    {
        $expected = self::json(self::CONTRACT . "/fixtures/configurator/$name.json");
        $raw = (new ConfiguratorBuilder())->build(new Catalog(DemoCatalog::catalog('de', $expected['productId'])), self::context());
        $actual = self::roundTrip($raw);
        self::write("configurator/$name.json", $raw);

        self::assertEquals($expected, $actual);
        self::assertNoPurchaseData($actual);
    }

    public static function context(?string $customerGroupId = null, string $language = 'de'): CalculationContext
    {
        return new CalculationContext(
            customerGroupId: $customerGroupId,
            language: $language,
            now: new \DateTimeImmutable('2026-11-06 10:00', new \DateTimeZone('Europe/Berlin')),
        );
    }

    /** Keine Einkaufspreise in Store-API-Antworten (CLAUDE.md, KONZEPT 3b). */
    public static function assertNoPurchaseData(mixed $data, string $path = ''): void
    {
        if (\is_object($data)) {
            $data = (array) $data;
        }
        if (!\is_array($data)) {
            return;
        }
        foreach ($data as $key => $value) {
            self::assertDoesNotMatchRegularExpression('/purchase|marg|contribution/i', (string) $key, "EK-Feld in Store-API-Antwort: $path.$key");
            self::assertNoPurchaseData($value, "$path.$key");
        }
    }

    /** @return array<string, mixed> */
    private static function json(string $file): array
    {
        return json_decode((string) file_get_contents($file), true, 512, \JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    private static function roundTrip(array $data): array
    {
        return json_decode((string) json_encode($data, \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR), true);
    }

    private static function write(string $relative, array $data): void
    {
        $file = (getenv('MENSURA_BUILD') ?: self::BUILD) . '/' . $relative;
        if (!is_dir(\dirname($file))) {
            mkdir(\dirname($file), 0777, true);
        }
        file_put_contents($file, (string) json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION));
    }
}
