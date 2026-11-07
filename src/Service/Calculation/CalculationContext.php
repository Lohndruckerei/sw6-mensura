<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Service\Calculation;

/**
 * Rahmen einer Berechnung: Kundengruppe, Sprache, Zeitpunkt und Plugin-Einstellungen.
 * Ohne Shopware-Abhängigkeit, damit die Engine im Unit-Test läuft (fester Zeitpunkt).
 */
final class CalculationContext
{
    public const DISPLAY_DEFAULTS = [
        'showPriceHints' => true,
        'showArea' => true,
        'showPerSqm' => true,
        'showPerPiece' => true,
        'showFromPrice' => true,
    ];

    /**
     * @param list<int>            $workingDays ISO-Wochentage 1 (Mo) … 7 (So)
     * @param list<string>         $holidays    YYYY-MM-DD
     * @param array<string, bool>  $display     Anzeige im Shop (Plugin-Einstellung), je Schalter true = anzeigen;
     *                                          das Produktprofil kann überschreiben
     */
    public function __construct(
        public readonly ?string $customerGroupId = null,
        public readonly string $language = 'de',
        public readonly \DateTimeImmutable $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin')),
        public readonly string $currency = 'EUR',
        public readonly ?string $orderCutoff = '11:30',
        public readonly array $workingDays = [1, 2, 3, 4, 5],
        public readonly array $holidays = [],
        public readonly string $storefrontLayout = 'compact',
        public readonly bool $allowLayoutSwitch = true,
        public readonly int $layoutTestShare = 0,
        public readonly array $display = self::DISPLAY_DEFAULTS,
        /** Steuersatz in % für `price.totalGross` (null = unbekannt, dann ohne Bruttopreis) */
        public readonly ?float $taxRate = null,
    ) {
    }

    public static function languageFromLocale(?string $locale): string
    {
        return $locale !== null && str_starts_with(strtolower($locale), 'en') ? 'en' : 'de';
    }
}
