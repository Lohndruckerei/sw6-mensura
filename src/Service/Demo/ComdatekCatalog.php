<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Service\Demo;

/**
 * Demo-Sortiment aus der Comdatek-Einkaufspreisliste 2026 (Excel der Lohndruckerei, KONZEPT 2a,
 * Quelle `Verwaltung/kalkulation/comdatek_preise_Lohndruckerei_2026.xlsx`). Die Werte stehen hier im Code –
 * keine Laufzeitabhängigkeit vom Netzlaufwerk. Geschrieben vom Befehl `mensura:demo:comdatek`.
 *
 * - EK = Excel (je Kalkulation eine Excel-Gruppe), VK = EK ÷ 0,75 (25 % Marge, Mitte des Korridors 20–30 %),
 *   auf Cent gerundet – für alle Preisanteile, Staffeln und Mindestpreise.
 * - Logistikpauschale (Vectura-Konzept 5.1, Modell D) als eigener Preisanteil `per_area` je Kalkulation,
 *   VK = Vorschlag „je m²“, EK = Versand-EK ÷ Fläche der 250 Comdatek-Aufträge. Der Wert kommt später aus
 *   Vectura (dort gibt es noch keinen Code); für den Kunden nicht getrennt ausgewiesen.
 * - Optionsgruppen sind eine gemeinsame Bibliothek: Gruppen und Werte, die der Basis-Demo (DemoCatalog) schon kennt,
 *   werden mit deren IDs wiederverwendet; neue Werte und die Gruppe „Konfektion“ kommen hinzu.
 * - IDs sind fest (md5 aus Schlüssel), der Befehl ist wiederholbar.
 */
final class ComdatekCatalog
{
    public const MARGIN = 0.25;

    /** Lieferant Comdatek im LieferLotse (feste ID, lose gekoppelt) */
    public const SUPPLIER_ID = '0194e1a00000700080000000c0d00001';
    public const SUPPLIER_NUMBER = 'COMDATEK';

    public const CATEGORY_ID = '0194e1a00000700080000000c0d00002';

    /**
     * Logistikpauschale je Materialgruppe: [VK €/m², Versand-EK €, Fläche m²] (Vectura KONZEPT 5.1).
     */
    public const LOGISTICS = [
        'alu' => [0.71, 247.0, 436.0],
        'hartschaum' => [6.26, 983.0, 196.0],
        'banner' => [3.62, 293.0, 101.0],
        'papier' => [0.90, 280.0, 389.0],
        'ps' => [1.94, 228.0, 147.0],
        'acryl' => [13.06, 203.0, 19.0],
        'hohlkammer' => [0.24, 22.0, 112.0],
    ];

    public static function id(string $key): string
    {
        return md5('ld-mensura-comdatek:' . $key);
    }

    /** VK aus EK mit 25 % Marge, auf Cent gerundet. */
    public static function vk(float $ek): float
    {
        return round($ek / (1 - self::MARGIN), 2);
    }

    /**
     * Preisanteil mit EK und VK (VK aus EK, außer angegeben).
     *
     * @return array<string, mixed>
     */
    public static function component(string $type, float $ek, ?float $vk = null, ?string $label = null): array
    {
        $component = ['type' => $type, 'purchase' => $ek, 'sale' => $vk ?? self::vk($ek)];
        if ($label !== null) {
            $component['label'] = $label;
        }

        return $component;
    }

    /** @return array<string, mixed> Preisanteil „Logistikpauschale“ einer Materialgruppe */
    public static function logistics(string $group): array
    {
        [$sale, $shipping, $area] = self::LOGISTICS[$group];

        return self::component('per_area', round($shipping / $area, 4), $sale, 'Logistikpauschale');
    }

    /**
     * Optionsgruppen: technicalName → [position, type, displayType, de, en, Hilfe de, Hilfe en].
     * Gruppen der Basis-Demo werden nicht verändert, nur neue angelegt.
     *
     * @return array<string, array{0: int, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string}>
     */
    public static function groups(): array
    {
        return DemoCatalog::groupDefinitions() + [
            'konfektion' => [60, 'standard', 'select', 'Konfektion', 'Finishing (banner)', 'Wie der Rand verarbeitet wird, z. B. Saum mit Ösen alle 50 cm zum Aufhängen.', 'How the edge is finished, e.g. hem with eyelets every 50 cm for hanging.'],
        ];
    }

    /**
     * Neue Optionswerte (zusätzlich zu DemoCatalog::valueDefinitions()): group → value → [position, de, en].
     *
     * @return array<string, array<string, array{0: int, 1: string, 2: string}>>
     */
    public static function newValues(): array
    {
        return [
            'farbigkeit' => [
                '4-0w-partiell' => [5, '4/0 + partiell weiß hinterdruckt', '4/0 + partial white underprint'],
                '4-0w-voll' => [6, '4/0 + vollflächig weiß hinterdruckt', '4/0 + full white underprint'],
                '5-0-dn' => [7, '5/0 Day & Night (4C + weiß vollflächig)', '5/0 day & night (4C + full white)'],
                '5-5-partiell' => [8, '5/5 beidseitig (4C + weiß partiell)', '5/5 both sides (4C + partial white)'],
                '5-5-voll' => [9, '5/5 beidseitig (4C + weiß vollflächig)', '5/5 both sides (4C + full white)'],
            ],
            'veredelung' => [
                'uv-1' => [4, 'UV-Lack einseitig (matt/glänzend)', 'UV varnish one side (matt/gloss)'],
                'uv-2' => [5, 'UV-Lack beidseitig (matt/glänzend)', 'UV varnish both sides (matt/gloss)'],
                'lam-1' => [6, 'Laminat einseitig (matt/glänzend)', 'Laminate one side (matt/gloss)'],
                'ag-1' => [7, 'Anti-Graffiti-Laminat einseitig', 'Anti-graffiti laminate one side'],
                'sonderlaminat' => [8, 'Anti-Graffiti / Steinschlagfolie / Whiteboard', 'Anti-graffiti / stone chip film / whiteboard'],
            ],
            'konfektion' => [
                'ohne' => [1, 'ohne (nur geschnitten)', 'none (cut only)'],
                'saum-oesen' => [2, 'Saum + Ösen', 'Hem + eyelets'],
                'nur-oesen' => [3, 'nur Ösen', 'Eyelets only'],
                'nur-saum' => [4, 'nur Saum', 'Hem only'],
                'hohlsaum' => [5, 'Hohlsaum', 'Pole pocket'],
                'keder' => [6, 'Rund-/Zeltkeder', 'Keder (round/tent)'],
                'flauschband' => [7, 'Flauschband (je lfm)', 'Velcro tape (per running metre)'],
                'gummilippe' => [8, 'Gummilippe 9/12/14 mm (je lfm)', 'Rubber lip 9/12/14 mm (per running metre)'],
            ],
        ];
    }

    public static function groupId(string $group): string
    {
        return DemoCatalog::id('group.' . $group);
    }

    public static function valueId(string $group, string $value): string
    {
        // Werte der Basis-Demo behalten deren ID (gemeinsame Bibliothek)
        if (isset(DemoCatalog::valueDefinitions()[$group][$value])) {
            return DemoCatalog::id('value.' . $group . '.' . $value);
        }

        return self::id('value.' . $group . '.' . $value);
    }

    /**
     * Kalkulationen je Excel-Gruppe. Optionswert: [Preisanteile EK…, Flags]; Kurzschreibweise
     * ['area' => €/m², 'piece' => €/Stk, 'perimeter' => €/lfm, 'default' => true, 'only' => [Material…], 'except' => [Material…]].
     * Material: [de, en, EK €/m², Optionen: code, supplierCode, minimum (EK), tiers [basis, [ab => EK]], minArea].
     *
     * @return array<string, array<string, mixed>>
     */
    public static function calculations(): array
    {
        $print = static fn (float $both, ?float $blank = null, array $extra = []) => array_filter([
            '4-0' => ['default' => true],
            '4-4' => $both !== 0.0 ? ['area' => $both] : null,
            '0-0' => $blank !== null ? ['area' => $blank] : null,
        ]) + $extra;
        $cut = static fn (float $rect, float $contourArea, float $contourPiece) => [
            'recht' => ['piece' => $rect, 'default' => true],
            'kontur' => ['area' => $contourArea, 'piece' => $contourPiece],
        ];
        $holes = static fn (float $piece) => ['nein' => ['default' => true], 'ja' => ['piece' => $piece]];
        $plates = ['farbigkeit', 'veredelung', 'schnitt', 'bohrung'];

        return [
            'aluverbund' => [
                'name' => 'Aluverbundplatten', 'size' => [1500, 3000], 'minimum' => 12.50, 'logistics' => 'alu', 'supplierCode' => '124',
                'sets' => $plates,
                'materials' => [
                    'alu-3mm' => ['Aluverbund 3 mm', 'Aluminium composite 3 mm', 26.0, ['supplierCode' => '29']],
                    'alu-4mm' => ['Aluverbund 4 mm', 'Aluminium composite 4 mm', 28.0, ['supplierCode' => '214']],
                    'alu-6mm' => ['Aluverbund 6 mm', 'Aluminium composite 6 mm', 44.0, ['supplierCode' => '30']],
                    'alu-gebuerstet-silber' => ['Aluverbund 3 mm gebürstet silber', 'Brushed aluminium composite 3 mm silver', 56.0, ['supplierCode' => '31']],
                    'alu-gebuerstet-gold' => ['Aluverbund 3 mm gebürstet gold', 'Brushed aluminium composite 3 mm gold', 56.0, ['supplierCode' => '244']],
                    'alu-gebuerstet-kupfer' => ['Aluverbund 3 mm gebürstet kupfer', 'Brushed aluminium composite 3 mm copper', 56.0, ['supplierCode' => '245']],
                    'alu-gebuerstet-bronze' => ['Aluverbund 3 mm gebürstet bronze', 'Brushed aluminium composite 3 mm bronze', 56.0, ['supplierCode' => '246']],
                    'alu-gebuerstet-schwarz' => ['Aluverbund 3 mm gebürstet schwarz', 'Brushed aluminium composite 3 mm black', 56.0, ['supplierCode' => '481']],
                ],
                'options' => [
                    'farbigkeit' => $print(6.0, -4.0, ['4-0w-partiell' => ['area' => 5.0]]),
                    'veredelung' => [
                        'keine' => ['default' => true],
                        'uv-1' => ['area' => 5.0], 'uv-2' => ['area' => 10.0], 'lam-1' => ['area' => 8.0], 'ag-1' => ['area' => 15.0],
                    ],
                    'schnitt' => $cut(0.35, 3.0, 0.50),
                    'bohrung' => $holes(0.20),
                ],
            ],
            'kaschiert' => [
                'name' => 'Kaschierte Platten', 'size' => [1500, 3000], 'minimum' => 15.00, 'logistics' => 'alu', 'supplierCode' => '40',
                // Druck 4/0 auf Orajet 3551 inkl. Laminat ist fester Bestandteil – die Trägerplatte ist das Material
                'components' => [self::component('per_area', 15.0, null, 'Druck 4/0 + Laminat')],
                'sets' => ['veredelung', 'schnitt', 'bohrung'],
                'materials' => [
                    'kasch-alu-3mm' => ['Kaschierung auf 3 mm Alu', 'Mounted on 3 mm aluminium composite', 23.0],
                    'kasch-alu-4mm' => ['Kaschierung auf 4 mm Alu', 'Mounted on 4 mm aluminium composite', 25.0],
                    'kasch-alu-6mm' => ['Kaschierung auf 6 mm Alu', 'Mounted on 6 mm aluminium composite', 41.0],
                    'kasch-hs-3mm' => ['Kaschierung auf 3 mm Hartschaum', 'Mounted on 3 mm foam PVC', 10.0],
                    'kasch-hs-5mm' => ['Kaschierung auf 5 mm Hartschaum', 'Mounted on 5 mm foam PVC', 14.0],
                    'kasch-hs-10mm' => ['Kaschierung auf 10 mm Hartschaum', 'Mounted on 10 mm foam PVC', 32.0],
                ],
                'options' => [
                    'veredelung' => ['keine' => ['default' => true], 'sonderlaminat' => ['area' => 8.0]],
                    'schnitt' => $cut(0.20, 6.0, 0.20),
                    'bohrung' => $holes(0.25),
                ],
            ],
            'acryl' => [
                'name' => 'Acrylglasplatten', 'size' => [1500, 3000], 'minimum' => 12.50, 'logistics' => 'acryl', 'supplierCode' => '122',
                'sets' => ['farbigkeit', 'schnitt', 'bohrung'],
                'materials' => [
                    'acryl-3mm' => ['Acrylglas 3 mm', 'Acrylic glass 3 mm', 40.0, ['supplierCode' => '36']],
                    'acryl-5mm' => ['Acrylglas 5 mm', 'Acrylic glass 5 mm', 53.0, ['supplierCode' => '37']],
                    'acryl-8mm' => ['Acrylglas 8 mm', 'Acrylic glass 8 mm', 82.0, ['supplierCode' => '291']],
                    'acryl-10mm' => ['Acrylglas 10 mm', 'Acrylic glass 10 mm', 95.0, ['supplierCode' => '38']],
                ],
                'options' => [
                    'farbigkeit' => $print(0.0, -4.0, ['4-0w-partiell' => ['area' => 6.0], '4-0w-voll' => ['area' => 8.0], '5-0-dn' => ['area' => 15.0]]),
                    'schnitt' => $cut(0.30, 5.0, 0.30),
                    'bohrung' => $holes(0.20),
                ],
            ],
            'hartschaum-1-3' => [
                'name' => 'Hartschaum 1–3 mm', 'size' => [2000, 3000], 'minimum' => 12.50, 'logistics' => 'hartschaum', 'supplierCode' => '123',
                'sets' => ['farbigkeit', 'schnitt', 'bohrung'],
                'materials' => [
                    'hs-1mm' => ['Hartschaum 1 mm', 'Foam PVC 1 mm', 10.8],
                    'hs-2mm' => ['Hartschaum 2 mm', 'Foam PVC 2 mm', 11.5],
                    'hs-3mm' => ['Hartschaum 3 mm', 'Foam PVC 3 mm', 13.0],
                ],
                'options' => ['farbigkeit' => $print(6.0, -2.0), 'schnitt' => $cut(0.10, 3.0, 0.10), 'bohrung' => $holes(0.20)],
            ],
            'hartschaum-5-10' => [
                'name' => 'Hartschaum 5–10 mm', 'size' => [2000, 3000], 'minimum' => 12.50, 'logistics' => 'hartschaum', 'supplierCode' => '123',
                'sets' => ['farbigkeit', 'schnitt', 'bohrung'],
                'materials' => [
                    'hs-5mm' => ['Hartschaum 5 mm', 'Foam PVC 5 mm', 16.0],
                    'hs-8mm' => ['Hartschaum 8 mm', 'Foam PVC 8 mm', 29.5],
                    'hs-10mm' => ['Hartschaum 10 mm', 'Foam PVC 10 mm', 34.0],
                ],
                'options' => ['farbigkeit' => $print(8.0, -2.0), 'schnitt' => $cut(0.20, 6.0, 0.20), 'bohrung' => $holes(0.20)],
            ],
            'hartschaum-schwarz' => [
                'name' => 'Hartschaum schwarz', 'size' => [2000, 3000], 'minimum' => 12.50, 'logistics' => 'hartschaum', 'supplierCode' => '123',
                'sets' => ['farbigkeit', 'schnitt', 'bohrung'],
                'materials' => [
                    'hs-schwarz-3mm' => ['Hartschaum schwarz 3 mm', 'Black foam PVC 3 mm', 17.0],
                    'hs-schwarz-5mm' => ['Hartschaum schwarz 5 mm', 'Black foam PVC 5 mm', 21.0],
                ],
                'options' => [
                    // auf schwarzer Platte immer mit Weiß hinterlegt
                    'farbigkeit' => [
                        '4-0w-partiell' => ['area' => 5.0, 'default' => true], '4-0w-voll' => ['area' => 8.0],
                        '5-5-partiell' => ['area' => 12.0], '5-5-voll' => ['area' => 15.0], '0-0' => ['area' => -2.0],
                    ],
                    'schnitt' => $cut(0.20, 6.0, 0.20),
                    'bohrung' => $holes(0.20),
                ],
            ],
            'pvc-ps' => [
                'name' => 'Hart-PVC & Polystyrol', 'size' => [1500, 3000], 'minimum' => 12.50, 'logistics' => 'ps', 'supplierCode' => '126',
                'sets' => ['farbigkeit', 'schnitt', 'bohrung'],
                'materials' => [
                    'hart-pvc-025' => ['Hart-PVC 0,25 mm', 'Rigid PVC 0.25 mm', 10.0],
                    'hart-pvc-050' => ['Hart-PVC 0,50 mm', 'Rigid PVC 0.50 mm', 12.0],
                    'ps-050' => ['Polystyrol 0,5 mm', 'Polystyrene 0.5 mm', 8.0],
                    'ps-100' => ['Polystyrol 1,0 mm', 'Polystyrene 1.0 mm', 10.0],
                ],
                'options' => ['farbigkeit' => $print(5.0), 'schnitt' => $cut(0.10, 3.0, 0.10), 'bohrung' => $holes(0.10)],
            ],
            'hohlkammer' => [
                'name' => 'Hohlkammerplatten', 'size' => [1500, 3000], 'minimum' => 12.50, 'logistics' => 'hohlkammer', 'supplierCode' => '128',
                'sets' => ['farbigkeit', 'schnitt', 'bohrung'],
                'materials' => [
                    'hohlkammer-welle-3mm' => ['Hohlkammer Welle 3 mm', 'Twin-wall sheet flute 3 mm', 8.0],
                    'hohlkammer-wabe-29mm' => ['Hohlkammer Wabe 2,9 mm', 'Twin-wall sheet honeycomb 2.9 mm', 6.0],
                ],
                'options' => ['farbigkeit' => $print(3.0), 'schnitt' => $cut(0.05, 2.0, 0.05), 'bohrung' => $holes(0.05)],
            ],
            'displaykarton' => [
                'name' => 'Displaykarton & DISPA', 'size' => [1500, 3000], 'minimum' => 9.80, 'logistics' => 'papier', 'supplierCode' => '127',
                'sets' => ['farbigkeit', 'schnitt', 'bohrung'],
                'materials' => [
                    'displaykarton-1mm' => ['Displaykarton 1 mm', 'Display board 1 mm', 7.0],
                    'displaykarton-2mm' => ['Displaykarton 2 mm', 'Display board 2 mm', 8.0],
                    'displaykarton-3mm' => ['Displaykarton 3 mm', 'Display board 3 mm', 9.5],
                    'dispa-38mm' => ['DISPA Papierplatte 3,8 mm', 'DISPA paper board 3.8 mm', 9.0],
                ],
                'options' => ['farbigkeit' => $print(4.0), 'schnitt' => $cut(0.10, 2.0, 0.10), 'bohrung' => $holes(0.05)],
            ],
            'reboard' => [
                'name' => 'Re-Board Wabenplatten', 'size' => [1500, 3000], 'minimum' => 9.80, 'logistics' => 'papier', 'supplierCode' => '127',
                'sets' => ['farbigkeit', 'schnitt', 'bohrung'],
                'materials' => [
                    'reboard-10mm' => ['Re-Board 10 mm', 'Re-Board 10 mm', 21.0],
                    'reboard-16mm' => ['Re-Board 16 mm', 'Re-Board 16 mm', 27.5],
                ],
                'options' => ['farbigkeit' => $print(4.0), 'schnitt' => $cut(0.20, 3.0, 0.20), 'bohrung' => $holes(0.25)],
            ],
            'stadur' => [
                'name' => 'Stadur Leichtschaumplatten', 'size' => [1500, 3000], 'minimum' => 9.80, 'logistics' => 'hartschaum', 'supplierCode' => '125',
                'sets' => ['farbigkeit', 'schnitt', 'bohrung'],
                'materials' => [
                    'stadur-5mm' => ['Stadur 5 mm', 'Stadur 5 mm', 21.0],
                    'stadur-10mm' => ['Stadur 10 mm', 'Stadur 10 mm', 25.5],
                    'stadur-19mm' => ['Stadur 19 mm', 'Stadur 19 mm', 31.0],
                    'stadur-40mm' => ['Stadur 40 mm', 'Stadur 40 mm', 63.0],
                ],
                // Konturschnitt Stadur nur €/m² (Excel ohne Stückpreis)
                'options' => ['farbigkeit' => $print(5.0), 'schnitt' => $cut(0.10, 5.0, 0.0), 'bohrung' => $holes(0.25)],
            ],
            'banner' => [
                'name' => 'Banner', 'size' => [5000, 50000], 'minimum' => 7.15, 'logistics' => 'banner', 'supplierCode' => '1',
                'quantitySteps' => [1, 2, 3, 5, 10, 20, 50],
                'sets' => ['farbigkeit', 'schnitt', 'konfektion'],
                'materials' => [
                    'banner-frontlit-510' => ['PVC-Banner Frontlit 510 g/m²', 'PVC banner frontlit 510 g/m²', 5.5, [
                        'supplierCode' => '14', 'minimum' => 7.15,
                        'tiers' => ['total_area', [0 => 5.5, 10 => 4.95, 25 => 4.75, 50 => 4.40, 100 => 4.25, 200 => 4.0]],
                    ]],
                    'banner-mesh-300' => ['Mesh-Banner 300 g/m²', 'Mesh banner 300 g/m²', 5.5, [
                        'supplierCode' => '16', 'minimum' => 7.25,
                        'tiers' => ['total_area', [0 => 5.5, 10 => 4.95, 25 => 4.75, 50 => 4.40, 100 => 4.25, 200 => 4.0]],
                    ]],
                    // Staffel nach Fläche je Stück: unter 1 m² 7,50, ab 1 m² 6,50
                    'banner-blockout-610' => ['Blockout-Banner 610 g/m²', 'Blockout banner 610 g/m²', 7.5, [
                        'supplierCode' => '17', 'minimum' => 8.90,
                        'tiers' => ['piece_area', [0 => 7.5, 1 => 6.5]],
                    ]],
                    'textil-greyback-280' => ['Textil-Banner Greyback 280 g/m² Airtech', 'Fabric banner greyback 280 g/m² Airtech', 11.0, ['supplierCode' => '553', 'minimum' => 7.45]],
                    'pvc-linoleum-1000' => ['PVC-Linoleum Fußboden 1000 g/m²', 'PVC linoleum floor 1000 g/m²', 20.0, ['minimum' => 8.90, 'minArea' => 1.0]],
                ],
                'options' => [
                    'farbigkeit' => ['4-0' => ['default' => true], '4-4' => ['area' => 5.0, 'only' => ['banner-blockout-610']]],
                    'schnitt' => ['recht' => ['default' => true], 'kontur' => ['area' => 4.0, 'only' => ['pvc-linoleum-1000']]],
                    'konfektion' => [
                        // kostenpflichtige Optionen nie vorauswählen (KONZEPT/WETTBEWERB)
                        'ohne' => ['default' => true],
                        'saum-oesen' => ['area' => 1.0, 'except' => ['banner-blockout-610', 'pvc-linoleum-1000']],
                        'nur-oesen' => ['area' => 0.5, 'except' => ['pvc-linoleum-1000']],
                        'nur-saum' => ['area' => 0.5, 'except' => ['banner-blockout-610', 'pvc-linoleum-1000']],
                        'hohlsaum' => ['area' => 3.0, 'except' => ['banner-blockout-610', 'pvc-linoleum-1000']],
                        'keder' => ['area' => 3.0, 'except' => ['banner-blockout-610', 'pvc-linoleum-1000']],
                    ],
                ],
            ],
            'textilbanner' => [
                'name' => 'Textilbanner', 'size' => [3200, 50000], 'minimum' => 8.30, 'logistics' => 'banner', 'supplierCode' => '41',
                'quantitySteps' => [1, 2, 3, 5, 10, 20, 50],
                'sets' => ['konfektion'],
                'materials' => [
                    'textil-auflicht-210' => ['Textil-Banner Auflicht 210 g/m²', 'Fabric banner frontlit 210 g/m²', 12.0, ['tiers' => ['total_area', [0 => 12.0, 10 => 9.0, 50 => 7.5]]]],
                    'textil-blockout-260' => ['Textil-Banner Blockout 260 g/m² (black back)', 'Fabric banner blockout 260 g/m² (black back)', 15.0, ['tiers' => ['total_area', [0 => 15.0, 10 => 13.0, 50 => 12.0]]]],
                    'textil-prolight-200' => ['Textil-Banner Pro-Light 200 g/m² (Durchlicht)', 'Fabric banner Pro-Light 200 g/m² (backlit)', 12.0, ['tiers' => ['total_area', [0 => 12.0, 10 => 11.0, 50 => 10.0]]]],
                ],
                'options' => [
                    'konfektion' => [
                        'ohne' => ['default' => true],
                        'saum-oesen' => ['area' => 1.5, 'except' => ['textil-prolight-200']],
                        'hohlsaum' => ['area' => 3.0, 'except' => ['textil-prolight-200']],
                        'flauschband' => ['perimeter' => 1.5, 'except' => ['textil-prolight-200']],
                        'gummilippe' => ['perimeter' => 1.0],
                    ],
                ],
            ],
        ];
    }

    /**
     * Demo-Produkte: Schlüssel → [Nummer, Name de, Name en, Darstellung, [[Kalkulation, Material|null] …], Comdatek-Produkt-ID|null, Vorgabe Format].
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: list<array{0: string, 1: string|null}>, 5: string|null, 6: array{0: int, 1: int}}>
     */
    public static function products(): array
    {
        $plates = [600, 400];
        $banner = [2000, 1000];

        return [
            'aluverbund' => ['CD-ALU', 'Aluverbundplatten', 'Aluminium composite panels', 'compact', [['aluverbund', null]], '124', $plates],
            'kaschiert' => ['CD-KASCH', 'Kaschierte Platten', 'Laminated mounted boards', 'compact', [['kaschiert', null]], '40', $plates],
            'acryl' => ['CD-ACRYL', 'Acrylglasplatten', 'Acrylic glass panels', 'compact', [['acryl', null]], '122', $plates],
            'hartschaum' => ['CD-HARTSCHAUM', 'Hartschaumplatten', 'Foam PVC boards', 'compact', [['hartschaum-1-3', null], ['hartschaum-5-10', null], ['hartschaum-schwarz', null]], '123', $plates],
            'pvc-ps' => ['CD-PVC-PS', 'Hart-PVC- & Polystyrolplatten', 'Rigid PVC & polystyrene sheets', 'compact', [['pvc-ps', null]], '126', $plates],
            'hohlkammer' => ['CD-HOHLKAMMER', 'Hohlkammerplatten', 'Twin-wall sheets', 'compact', [['hohlkammer', null]], '128', $plates],
            'displaykarton' => ['CD-DISPLAYKARTON', 'Displaykarton & DISPA-Papierplatten', 'Display board & DISPA paper boards', 'compact', [['displaykarton', null]], '127', $plates],
            'reboard' => ['CD-REBOARD', 'Re-Board Wabenplatten', 'Re-Board honeycomb boards', 'compact', [['reboard', null]], '127', $plates],
            'stadur' => ['CD-STADUR', 'Stadur Leichtschaumplatten', 'Stadur lightweight foam boards', 'compact', [['stadur', null]], '125', $plates],
            'plattendirektdruck' => ['CD-PLATTEN', 'Plattendirektdruck', 'Direct board printing', 'compact', [
                ['aluverbund', null], ['acryl', null], ['hartschaum-1-3', null], ['hartschaum-5-10', null], ['hartschaum-schwarz', null],
                ['pvc-ps', null], ['hohlkammer', null], ['displaykarton', null], ['reboard', null], ['stadur', null],
            ], null, $plates],
            'pvc-banner' => ['CD-BANNER', 'PVC-Banner', 'PVC banners', 'guided', [['banner', 'banner-frontlit-510'], ['banner', 'banner-mesh-300'], ['banner', 'banner-blockout-610']], '1', $banner],
            'textil-greyback' => ['CD-GREYBACK', 'Textil-Banner Greyback', 'Fabric banner greyback', 'guided', [['banner', 'textil-greyback-280']], '1', $banner],
            'pvc-linoleum' => ['CD-LINOLEUM', 'PVC-Linoleum-Fußboden', 'PVC linoleum floor', 'guided', [['banner', 'pvc-linoleum-1000']], '1', $banner],
            'textilbanner' => ['CD-TEXTIL', 'Textilbanner', 'Fabric banners', 'guided', [['textilbanner', null]], '41', $banner],
        ];
    }

    /**
     * Kundentext der Materialien je Kalkulation [de, en] – im Shop sichtbar (Material-Kachel), daher ohne Hinweis
     * auf Lieferant oder Einkaufspreise.
     *
     * @return array{0: string, 1: string}
     */
    public static function customerText(string $calculation): array
    {
        return match ($calculation) {
            'aluverbund' => ['Formstabile Verbundplatte für Schilder innen und außen, wetterfest.', 'Rigid composite panel for indoor and outdoor signs, weatherproof.'],
            'kaschiert' => ['Digitaldruck auf Folie mit Schutzlaminat, auf die Trägerplatte kaschiert.', 'Digital print on film with protective laminate, mounted on the carrier board.'],
            'acryl' => ['Glasklare Platte mit edler Tiefenwirkung, ideal für Praxis- und Firmenschilder.', 'Crystal-clear panel with an elegant depth effect, ideal for office signs.'],
            'hartschaum-1-3', 'hartschaum-5-10' => ['Leichte, glatte PVC-Hartschaumplatte für Innen und kurzfristig außen.', 'Light, smooth foam PVC board for indoor and short-term outdoor use.'],
            'hartschaum-schwarz' => ['Schwarze Hartschaumplatte, Druck mit weißer Unterlegung für kräftige Farben.', 'Black foam PVC board, printed with white underlay for vivid colours.'],
            'pvc-ps' => ['Dünne, flexible Platte für Displays, Aufsteller und Verpackungen.', 'Thin, flexible sheet for displays, stands and packaging.'],
            'hohlkammer' => ['Sehr leichte Kunststoffplatte, z. B. für Wahl- und Bauschilder.', 'Very light plastic sheet, e.g. for election and construction signs.'],
            'displaykarton' => ['Recyclingfähige Papier- und Kartonplatte für Aufsteller und Messen.', 'Recyclable paper and board for stands and trade fairs.'],
            'reboard' => ['Leichte, stabile Papierwabenplatte – nachhaltig für Messe und Ladenbau.', 'Light, stiff paper honeycomb board – sustainable for fairs and shopfitting.'],
            'stadur' => ['Leichtschaumplatte mit glatter Oberfläche, auch in großen Stärken.', 'Lightweight foam board with smooth surface, also in large thicknesses.'],
            'banner' => ['Reißfestes Bannermaterial für Werbung an Zaun, Gerüst und Fassade.', 'Tear-resistant banner material for fences, scaffolding and façades.'],
            'textilbanner' => ['Knitterarmer Stoff mit brillanten Farben, waschbar.', 'Crease-resistant fabric with brilliant colours, washable.'],
            default => ['', ''],
        };
    }

    public static function calculationId(string $key): string
    {
        return self::id('calculation.' . $key);
    }

    public static function materialId(string $key): string
    {
        return self::id('material.' . $key);
    }

    public static function productId(string $key): string
    {
        return self::id('product.' . $key);
    }

    public static function setId(string $calculation): string
    {
        return self::id('set.' . $calculation);
    }

    /**
     * Preisanteile eines Optionswerts aus der Kurzschreibweise.
     *
     * @param array<string, mixed> $spec
     *
     * @return list<array<string, mixed>>
     */
    public static function valueComponents(array $spec): array
    {
        $components = [];
        foreach (['area' => 'per_area', 'piece' => 'per_piece', 'perimeter' => 'per_perimeter'] as $key => $type) {
            if (isset($spec[$key]) && (float) $spec[$key] !== 0.0) {
                $components[] = self::component($type, (float) $spec[$key]);
            }
        }

        return $components;
    }

    /**
     * Staffel im getrennten Format (EK aus Excel, VK = EK ÷ 0,75).
     *
     * @param array{0: string, 1: array<int|string, float>} $tiers
     *
     * @return array<string, mixed>
     */
    public static function tiers(array $tiers): array
    {
        [$basis, $steps] = $tiers;
        $side = static fn (bool $sale) => [
            'basis' => $basis,
            'steps' => array_values(array_map(
                static fn ($from, float $ek) => ['from' => (float) $from, 'price' => $sale ? self::vk($ek) : $ek],
                array_keys($steps),
                $steps,
            )),
        ];

        return ['purchase' => $side(false), 'sale' => $side(true)];
    }

    /**
     * Zeilen „Optionswert in Kalkulation“ (allgemein + je Material bei only/except).
     *
     * @return list<array<string, mixed>>
     */
    public static function assignments(string $calculationKey): array
    {
        $calculation = self::calculations()[$calculationKey];
        $rows = [];
        $position = 0;
        foreach ($calculation['options'] as $group => $values) {
            foreach ($values as $value => $spec) {
                $base = [
                    'optionValueId' => self::valueId($group, $value),
                    'isDefault' => (bool) ($spec['default'] ?? false),
                    'position' => ++$position,
                    'priceComponents' => self::valueComponents($spec),
                ];
                // nur für bestimmte Materialien: Zeilen je Material, ohne allgemeine Zeile (Catalog::assignment)
                $only = $spec['only'] ?? (isset($spec['except'])
                    ? array_values(array_diff(array_keys($calculation['materials']), $spec['except']))
                    : null);
                if ($only !== null) {
                    foreach ($only as $material) {
                        $rows[] = $base + ['id' => self::id("assignment.$calculationKey.$group.$value.$material"), 'materialId' => self::materialId($material), 'active' => true];
                    }
                    continue;
                }
                $rows[] = $base + ['id' => self::id("assignment.$calculationKey.$group.$value"), 'materialId' => null, 'active' => true];
            }
        }

        return $rows;
    }
}
