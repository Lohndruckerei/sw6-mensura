<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Service\Calculation;

/**
 * Texte zu den festen `code`s des Vertrags (docs/contract/README.md Regel 5), de und en.
 * Das Frontend entscheidet nach `code`; der Text ist nur zur Anzeige.
 */
final class Messages
{
    private const TEXTS = [
        'de' => [
            'product.not_configurable' => 'Dieses Produkt ist nicht konfigurierbar',
            'cart.configuration_required' => 'Bitte zuerst im Konfigurator auf der Produktseite Material, Maße und Ausführung wählen',
            'material.reset' => 'Das gewählte Material gibt es hier nicht – jetzt „{new}“',
            'material.unavailable' => 'Für dieses Produkt ist kein Material verfügbar',
            'format.width_min' => '{material}: Breite mindestens {limit} mm',
            'format.width_max' => '{material}: Breite höchstens {limit} mm',
            'format.height_min' => '{material}: Höhe mindestens {limit} mm',
            'format.height_max' => '{material}: Höhe höchstens {limit} mm',
            'format.fixed_required' => 'Bitte ein Format aus der Liste wählen',
            'format.value_reset' => 'Das gewählte Format gibt es hier nicht – bitte Maße prüfen',
            'quantity.min' => 'Mindestmenge {limit} Stück',
            'quantity.max' => 'Höchstmenge {limit} Stück',
            'quantity.motifs_max' => 'Höchstens {limit} Motive',
            'quantity.reset' => 'Die Angabe „{old}“ ist keine gültige Anzahl – jetzt {new}',
            'option.removed' => '{group} entfällt bei {calculation}',
            'option.reset' => '{group} „{old}“ gibt es bei {calculation} nicht – jetzt „{new}“',
            'option.fixed' => '{group} ist bei diesem Produkt fest „{new}“',
            'option.unknown' => 'Unbekannte Option „{group}“ wurde entfernt',
            'option.excluded' => '{group} „{old}“ passt nicht zu „{other}“ – jetzt „{new}“',
            'option.unavailable' => '{group}: Für diese Kombination gibt es keine Auswahl',
            'option.choose' => 'Bitte {group} wählen',
            'delivery.reset' => 'Lieferzeit „{old}“ ist für diese Bestellung nicht möglich – jetzt „{new}“',
            'delivery.max_quantity' => 'nur bis {limit} Stück',
            'delivery.max_area' => 'nur bis {limit} m²',
            'delivery.material' => 'nicht für {material}',
            'price.minimum_applied' => 'Mindestpreis {price} je Position',
            'price.best_price' => 'Ab {quantity} Stück kostet die Position nur {price} – mehr bestellen lohnt sich',
            'price.best_price_applied' => 'Bestpreis: Sie zahlen den günstigeren Preis von {quantity} Stück ({price})',
            'price.not_available' => 'Für diese Kombination gibt es keinen Preis – bitte anfragen',
            'hint.none' => 'ohne Aufpreis',
            'unit.piece' => 'Stk',
            'unit.motif' => 'Motiv',
            'unit.perimeter' => 'lfm',
            'step.material' => 'Material',
            'step.format' => 'Format',
            'step.options' => 'Ausführung',
            'step.quantity' => 'Menge & Lieferzeit',
        ],
        'en' => [
            'product.not_configurable' => 'This product cannot be configured',
            'cart.configuration_required' => 'Please choose material, size and finish in the configurator on the product page first',
            'material.reset' => 'The selected material is not available here – now “{new}”',
            'material.unavailable' => 'No material is available for this product',
            'format.width_min' => '{material}: width at least {limit} mm',
            'format.width_max' => '{material}: width at most {limit} mm',
            'format.height_min' => '{material}: height at least {limit} mm',
            'format.height_max' => '{material}: height at most {limit} mm',
            'format.fixed_required' => 'Please choose a format from the list',
            'format.value_reset' => 'The selected format is not available here – please check the size',
            'quantity.min' => 'Minimum quantity {limit} pcs',
            'quantity.max' => 'Maximum quantity {limit} pcs',
            'quantity.motifs_max' => 'At most {limit} motifs',
            'quantity.reset' => '“{old}” is not a valid number – now {new}',
            'option.removed' => '{group} is not available for {calculation}',
            'option.reset' => '{group} “{old}” is not available for {calculation} – now “{new}”',
            'option.fixed' => '{group} is fixed to “{new}” for this product',
            'option.unknown' => 'Unknown option “{group}” was removed',
            'option.excluded' => '{group} “{old}” does not match “{other}” – now “{new}”',
            'option.unavailable' => '{group}: no choice available for this combination',
            'option.choose' => 'Please choose {group}',
            'delivery.reset' => 'Delivery “{old}” is not possible for this order – now “{new}”',
            'delivery.max_quantity' => 'only up to {limit} pcs',
            'delivery.max_area' => 'only up to {limit} m²',
            'delivery.material' => 'not for {material}',
            'price.minimum_applied' => 'Minimum price {price} per item',
            'price.best_price' => 'From {quantity} pcs the item costs only {price} – ordering more pays off',
            'price.best_price_applied' => 'Best price: you pay the lower price of {quantity} pcs ({price})',
            'price.not_available' => 'No price for this combination – please ask for a quote',
            'hint.none' => 'no surcharge',
            'unit.piece' => 'pc',
            'unit.motif' => 'motif',
            'unit.perimeter' => 'rm',
            'step.material' => 'Material',
            'step.format' => 'Size',
            'step.options' => 'Options',
            'step.quantity' => 'Quantity & delivery',
        ],
    ];

    public function __construct(private readonly string $language = 'de')
    {
    }

    /**
     * @param array<string, string|int|float> $params
     */
    public function text(string $code, array $params = []): string
    {
        $text = self::TEXTS[$this->language][$code] ?? self::TEXTS['de'][$code] ?? $code;
        foreach ($params as $key => $value) {
            $text = str_replace('{' . $key . '}', (string) $value, $text);
        }

        return $text;
    }

    /**
     * @param array<string, string|int|float> $params
     *
     * @return array{code: string, message: string, field: string|null, fix?: array<string, mixed>}
     */
    public function message(string $code, array $params = [], ?string $field = null, ?array $fix = null): array
    {
        $message = ['code' => $code, 'message' => $this->text($code, $params), 'field' => $field];
        if ($fix !== null) {
            $message['fix'] = $fix;
        }

        return $message;
    }

    public function money(float $amount): string
    {
        return $this->language === 'en'
            ? number_format($amount, 2, '.', ',') . ' €'
            : number_format($amount, 2, ',', '.') . ' €';
    }

    public function number(float $value, int $decimals = 0): string
    {
        return $this->language === 'en'
            ? number_format($value, $decimals, '.', ',')
            : number_format($value, $decimals, ',', '.');
    }

    /** Zahl ohne überflüssige Nachkommastellen (30 → „30“, 12,5 → „12,5“). */
    public function plain(float $value): string
    {
        $decimals = abs($value - round($value)) < 1e-9 ? 0 : (abs($value * 10 - round($value * 10)) < 1e-9 ? 1 : 2);

        return $this->number($value, $decimals);
    }
}
