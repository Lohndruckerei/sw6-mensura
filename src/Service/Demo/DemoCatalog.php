<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Service\Demo;

/**
 * Demo- und Testdaten passend zu docs/contract/fixtures: Produkt „Aluverbund“ (Aluverbund 3 mm und Acrylglas 5 mm)
 * und Produkt „Folie“ (Folie A).
 *
 * - Aluverbund = Comdatek-VK (KONZEPT 2): 29 €/m² + 0,50 €/Stk, min. 12,50 €; EK aus der Einkaufspreisliste 2026 (2a)
 * - Acrylglas  = Beispielwerte (62 €/m²)
 * - Folie A    = zweites Produkt „Folie (Mensura-Demo)“: VK wie Comdatek Green Stick 12,00 €/m² + 0,10 €/Stk, min. 8,00 €,
 *                Mindestfläche 1 m² je Position, Schrittweite 0,1 m² (Regel des Benutzers, 07.11.2026)
 *
 * Eine Quelle für beides: der Vertragstest rechnet direkt mit `catalog()`, der Befehl `mensura:demo:seed`
 * schreibt dieselben Daten (de + en) in die Datenbank. IDs sind fest, damit die Fixtures passen.
 */
final class DemoCatalog
{
    public const PRODUCT_ID = '0194e1a0000070008000000000000a01';
    public const PRODUCT_FOLIE = '0194e1a0000070008000000000000a02';
    public const MATERIAL_ALU = '0194e1a0000070008000000000000b01';
    public const MATERIAL_ACRYL = '0194e1a0000070008000000000000b02';
    public const MATERIAL_FOLIE = '0194e1a0000070008000000000000b03';

    /** Demo-Produkte: productId → [Produktnummer, Name de, Name en, Profil-Schlüssel, Netto-Anzeigepreis] */
    public const PRODUCTS = [
        self::PRODUCT_ID => ['MENSURA-DEMO-ALU', 'Aluverbund (Mensura-Demo)', 'Aluminium composite (Mensura demo)', 'aluverbund', 29.0],
        self::PRODUCT_FOLIE => ['MENSURA-DEMO-FOLIE', 'Folie (Mensura-Demo)', 'Film (Mensura demo)', 'folie', 12.0],
    ];

    public static function id(string $key): string
    {
        return md5('ld-mensura-demo:' . $key);
    }

    /**
     * Optionsgruppen: technicalName → [position, type, displayType, de-Name, en-Name, de-Hilfe, en-Hilfe]
     *
     * @return array<string, array{0: int, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string}>
     */
    public static function groupDefinitions(): array
    {
        return [
            'farbigkeit' => [10, 'standard', 'select', 'Farbigkeit', 'Colours', '„4/0“ heißt: vorne vierfarbig, hinten unbedruckt.', '“4/0” means: full colour front, blank back.'],
            'veredelung' => [20, 'standard', 'select', 'Veredelung', 'Finishing', 'Schützt den Druck. Anti-Graffiti lässt sich von Farbe reinigen.', 'Protects the print. Anti-graffiti can be cleaned of paint.'],
            'kanten' => [30, 'standard', 'select', 'Kantenpolitur', 'Edge polishing', 'Polierte Kanten wirken wie Glas.', 'Polished edges look like glass.'],
            'bohrung' => [40, 'standard', 'select', 'Lochbohrungen', 'Drill holes', 'Vier Bohrungen in den Ecken.', 'Four holes in the corners.'],
            'schnitt' => [50, 'standard', 'select', 'Schneiden', 'Cutting', 'Konturschnitt folgt der Schnittlinie in der Druckdatei.', 'Contour cut follows the cut line in the print file.'],
            'lieferzeit' => [90, 'delivery', 'radio', 'Produktion & Lieferung', 'Production & delivery', 'Bei Bestellung und Druckdaten bis 11:30 Uhr.', 'For orders with artwork until 11:30.'],
        ];
    }

    /**
     * Optionswerte: group → technicalName → [position, de-Name, en-Name, deliveryDays|null, availability|null]
     *
     * @return array<string, array<string, array{0: int, 1: string, 2: string, 3: int|null, 4: array<string, mixed>|null}>>
     */
    public static function valueDefinitions(): array
    {
        return [
            'farbigkeit' => [
                '4-0' => [1, '4/0 einseitig bedruckt', '4/0 printed one side', null, null],
                '4-4' => [2, '4/4 beidseitig bedruckt', '4/4 printed both sides', null, null],
                '4-0w' => [3, '4/0 + weiß hinterdruckt', '4/0 + white backing', null, null],
                '0-0' => [4, '0/0 unbedruckt (blanko)', '0/0 unprinted (blank)', null, null],
            ],
            'veredelung' => [
                'keine' => [1, 'keine', 'none', null, null],
                'lam-matt' => [2, 'Laminat matt', 'Matt laminate', null, null],
                'ag-2' => [3, 'Anti-Graffiti beidseitig', 'Anti-graffiti both sides', null, null],
            ],
            'kanten' => [
                'nein' => [1, 'ohne', 'none', null, null],
                'ja' => [2, 'Kanten poliert', 'Polished edges', null, null],
            ],
            'bohrung' => [
                'nein' => [1, 'Nein', 'No', null, null],
                'ja' => [2, 'Ja, 4 Ecken', 'Yes, 4 corners', null, null],
            ],
            'schnitt' => [
                'recht' => [1, 'rechteckig', 'rectangular', null, null],
                'kontur' => [2, 'Konturschnitt / Freiform', 'Contour cut / free form', null, null],
            ],
            'lieferzeit' => [
                'standard' => [1, 'Standard', 'Standard', 5, null],
                'express' => [2, 'Express', 'Express', 3, ['maxQuantity' => 50]],
            ],
        ];
    }

    /**
     * Preise je Kalkulation: calc → group → value → [isDefault, priceComponents]
     *
     * @return array<string, array<string, array<string, array{0: bool, 1: list<array<string, mixed>>}>>>
     */
    public static function assignmentDefinitions(): array
    {
        $area = static fn (float $sale, float $purchase) => [['type' => 'per_area', 'purchase' => $purchase, 'sale' => $sale]];
        $piece = static fn (float $sale, float $purchase) => [['type' => 'per_piece', 'purchase' => $purchase, 'sale' => $sale]];
        $none = [];
        $delivery = [
            'standard' => [true, $none],
            'express' => [false, [['type' => 'percent', 'purchase' => 20.0, 'sale' => 30.0]]],
        ];

        return [
            'aluverbund' => [
                'farbigkeit' => ['4-0' => [true, $none], '4-4' => [false, $area(9.0, 6.0)], '0-0' => [false, $area(-8.0, -6.0)]],
                'veredelung' => ['keine' => [true, $none], 'lam-matt' => [false, $area(8.0, 5.5)], 'ag-2' => [false, $area(29.0, 22.0)]],
                'bohrung' => ['nein' => [true, $none], 'ja' => [false, $piece(0.30, 0.20)]],
                'schnitt' => [
                    'recht' => [true, [['type' => 'per_piece', 'purchase' => 0.10, 'sale' => 0.0]]],
                    // EK: mehrere Preisanteile zugleich (Comdatek-EK Konturschnitt Alu: €/m² und €/Stk)
                    'kontur' => [false, [['type' => 'per_area', 'purchase' => 1.50, 'sale' => 2.0], ['type' => 'per_piece', 'purchase' => 0.25, 'sale' => 0.0]]],
                ],
                'lieferzeit' => $delivery,
            ],
            'acryl' => [
                'farbigkeit' => ['4-0' => [true, $none], '4-0w' => [false, $area(8.0, 5.0)], '0-0' => [false, $area(-8.0, -6.0)]],
                'kanten' => ['nein' => [true, $none], 'ja' => [false, [['type' => 'per_perimeter', 'purchase' => 2.5, 'sale' => 4.0]]]],
                'bohrung' => ['nein' => [true, $none], 'ja' => [false, $piece(0.30, 0.20)]],
                'schnitt' => ['recht' => [true, $none], 'kontur' => [false, $area(2.0, 1.5)]],
                'lieferzeit' => $delivery,
            ],
            'folie' => [
                'lieferzeit' => $delivery,
            ],
        ];
    }

    /**
     * Optionssets: technicalName → [de-Name, Gruppen]
     *
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function setDefinitions(): array
    {
        return [
            'plattenveredelung-alu' => ['Plattenveredelung Aluverbund', ['farbigkeit', 'veredelung', 'bohrung', 'schnitt']],
            'plattenveredelung-acryl' => ['Plattenveredelung Acrylglas', ['farbigkeit', 'kanten', 'bohrung', 'schnitt']],
        ];
    }

    /**
     * Kalkulationen (Felder wie die Entity).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function calculationDefinitions(): array
    {
        $common = [
            'active' => true,
            'unit' => 'area',
            'formatMode' => 'free',
            'widthMin' => 100,
            'widthMax' => 1500,
            'heightMin' => 100,
            'allowRotation' => true,
            'minArea' => null,
            'areaRounding' => null,
            'areaScope' => 'total',
            'maxMotifs' => null,
            'maxQuantity' => null,
            'optionSetId' => null,
            'motifMode' => 'multiply',
            'tierScope' => 'total',
            'bestPriceMode' => 'hint',
            'tierBasis' => 'none',
            'tiers' => null,
            'priceRounding' => null,
            'setupPricePurchase' => null,
            'setupPriceSale' => null,
            'quantitySteps' => [1, 2, 5, 10, 25, 50, 100],
            'saleRule' => 'margin',
            'saleRuleValue' => 25.0,
            'marginMin' => 20.0,
            'marginMax' => 30.0,
        ];

        return [
            'aluverbund' => $common + [
                'id' => self::id('calculation.aluverbund'),
                'technicalName' => 'aluverbund',
                'name' => 'Aluverbund',
                'heightMax' => 3000,
                'priceComponents' => [['type' => 'per_piece', 'purchase' => 0.45, 'sale' => 0.50]],
                'minimumPricePurchase' => 9.80,
                'minimumPriceSale' => 12.50,
                'materialIds' => [self::MATERIAL_ALU],
            ],
            'acryl' => $common + [
                'id' => self::id('calculation.acryl'),
                'technicalName' => 'acryl',
                'name' => 'Acrylglas',
                'heightMax' => 2000,
                'priceComponents' => [['type' => 'per_piece', 'purchase' => 0.40, 'sale' => 0.50]],
                'minimumPricePurchase' => 9.80,
                'minimumPriceSale' => 12.50,
                'materialIds' => [self::MATERIAL_ACRYL],
            ],
            'folie' => [
                'id' => self::id('calculation.folie'),
                'technicalName' => 'folie',
                'name' => 'Folie',
                'widthMin' => 10,
                'widthMax' => 1370,
                'heightMin' => 10,
                'heightMax' => 10000,
                'minArea' => 1.0,
                'areaRounding' => 0.1,
                'areaScope' => 'total',
                'priceComponents' => [['type' => 'per_piece', 'purchase' => 0.05, 'sale' => 0.10]],
                'minimumPricePurchase' => 6.0,
                'minimumPriceSale' => 8.0,
                'materialIds' => [self::MATERIAL_FOLIE],
            ] + $common,
        ];
    }

    /**
     * Materialien (Felder wie die Entity), mit Übersetzungen de/en.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function materialDefinitions(): array
    {
        return [
            self::MATERIAL_ALU => [
                'id' => self::MATERIAL_ALU,
                'technicalName' => 'alu-3mm',
                'code' => 'ALU3',
                'active' => true,
                'position' => 1,
                'pricePurchase' => 26.0,
                'priceSale' => 29.0,
                'minimumPricePurchase' => null,
                'minimumPriceSale' => null,
                'minArea' => null,
                'areaRounding' => null,
                'tierBasis' => null,
                'tiers' => null,
                'priceComponents' => null,
                'optionSetId' => self::id('set.plattenveredelung-alu'),
                'widthMax' => null,
                'heightMax' => null,
                'sheetWidth' => 1500,
                'sheetHeight' => 3050,
                'weightPerSqm' => 3.8,
                'translations' => [
                    'de' => ['name' => 'Aluverbund 3 mm', 'description' => 'Der Klassiker für Schilder innen und außen.', 'badges' => ['außen', 'Bestseller']],
                    'en' => ['name' => 'Aluminium composite 3 mm', 'description' => 'The classic for signs indoors and outdoors.', 'badges' => ['outdoor', 'Bestseller']],
                ],
            ],
            self::MATERIAL_ACRYL => [
                'id' => self::MATERIAL_ACRYL,
                'technicalName' => 'acryl-5mm',
                'code' => 'ACR5',
                'active' => true,
                'position' => 2,
                'pricePurchase' => 48.0,
                'priceSale' => 62.0,
                'minimumPricePurchase' => null,
                'minimumPriceSale' => null,
                'minArea' => null,
                'areaRounding' => null,
                'tierBasis' => null,
                'tiers' => null,
                'priceComponents' => null,
                'optionSetId' => self::id('set.plattenveredelung-acryl'),
                'widthMax' => null,
                'heightMax' => null,
                'sheetWidth' => 2050,
                'sheetHeight' => 3050,
                'weightPerSqm' => 6.0,
                'translations' => [
                    'de' => ['name' => 'Acrylglas 5 mm', 'description' => 'Beispielwerte – massiver Glaseffekt.', 'badges' => ['innen']],
                    'en' => ['name' => 'Acrylic glass 5 mm', 'description' => 'Sample values – solid glass effect.', 'badges' => ['indoor']],
                ],
            ],
            self::MATERIAL_FOLIE => [
                'id' => self::MATERIAL_FOLIE,
                'technicalName' => 'folie-a',
                'code' => 'FOLA',
                'active' => true,
                'position' => 1,
                'pricePurchase' => 8.5,
                'priceSale' => 12.0,
                'minimumPricePurchase' => null,
                'minimumPriceSale' => null,
                'minArea' => null,
                'areaRounding' => null,
                'tierBasis' => null,
                'tiers' => null,
                'priceComponents' => null,
                'optionSetId' => null,
                'widthMax' => null,
                'heightMax' => null,
                'sheetWidth' => 1370,
                'sheetHeight' => 50000,
                'weightPerSqm' => 0.12,
                'translations' => [
                    'de' => ['name' => 'Folie A (matt, PVC-frei)', 'description' => 'Beispiel: Mindestfläche 1 m² je Position, darüber in 0,1-m²-Schritten.', 'badges' => ['innen', 'außen']],
                    'en' => ['name' => 'Film A (matt, PVC-free)', 'description' => 'Example: minimum area 1 m² per item, above in 0.1 m² steps.', 'badges' => ['indoor', 'outdoor']],
                ],
            ],
        ];
    }

    /**
     * Produktprofil (Felder wie die Entity).
     *
     * @return array<string, mixed>
     */
    public static function profileDefinition(string $productId = self::PRODUCT_ID): array
    {
        if ($productId === self::PRODUCT_FOLIE) {
            return [
                'id' => self::id('profile.folie'),
                'productId' => self::PRODUCT_FOLIE,
                'type' => 'general',
                'active' => true,
                'layout' => null,
                'widthMin' => null,
                'widthMax' => null,
                'heightMin' => null,
                'heightMax' => null,
                'minQuantity' => null,
                'maxQuantity' => null,
                'minimumPriceSale' => null,
                'surchargePercent' => null,
                'priceComponents' => null,
                'quantitySteps' => [1, 5, 10, 25, 50, 100, 250],
                'steps' => null,
                'defaults' => [
                    'materialId' => self::MATERIAL_FOLIE,
                    'format' => ['width' => 250, 'height' => 400],
                    'motifs' => 1,
                    'perMotif' => 5,
                ],
                'materials' => [
                    ['id' => self::id('profile.material.folie'), 'calculationId' => self::id('calculation.folie'), 'materialId' => self::MATERIAL_FOLIE, 'position' => 1, 'surchargePercent' => null],
                ],
                'options' => [],
            ];
        }

        return [
            'id' => self::id('profile.aluverbund'),
            'productId' => self::PRODUCT_ID,
            'type' => 'general',
            'active' => true,
            'layout' => null,
            'widthMin' => null,
            'widthMax' => null,
            'heightMin' => null,
            'heightMax' => null,
            'minQuantity' => null,
            'maxQuantity' => null,
            'minimumPriceSale' => null,
            'surchargePercent' => null,
            'priceComponents' => null,
            'quantitySteps' => null,
            'steps' => null,
            'defaults' => [
                'materialId' => self::MATERIAL_ALU,
                'format' => ['width' => 600, 'height' => 400],
                'motifs' => 1,
                'perMotif' => 1,
            ],
            'materials' => [
                ['id' => self::id('profile.material.alu'), 'calculationId' => self::id('calculation.aluverbund'), 'materialId' => self::MATERIAL_ALU, 'position' => 1, 'surchargePercent' => null],
                ['id' => self::id('profile.material.acryl'), 'calculationId' => self::id('calculation.acryl'), 'materialId' => self::MATERIAL_ACRYL, 'position' => 2, 'surchargePercent' => null],
            ],
            'options' => [],
        ];
    }

    /**
     * Katalog in einer Sprache – genau das, was `CatalogLoader` aus der Datenbank liefert.
     *
     * @return array<string, mixed>
     */
    public static function catalog(string $language = 'de', string $productId = self::PRODUCT_ID): array
    {
        $groups = [];
        $values = [];
        foreach (self::groupDefinitions() as $name => [$position, $type, $displayType, $de, $en, $helpDe, $helpEn]) {
            $groupId = self::id('group.' . $name);
            $groups[$groupId] = [
                'id' => $groupId,
                'technicalName' => $name,
                'name' => $language === 'en' ? $en : $de,
                'helpText' => $language === 'en' ? $helpEn : $helpDe,
                'type' => $type,
                'scope' => 'position',
                'displayType' => $displayType,
                'required' => true,
                'position' => $position,
            ];
            foreach (self::valueDefinitions()[$name] as $valueName => [$valuePosition, $valueDe, $valueEn, $days, $availability]) {
                $valueId = self::id('value.' . $name . '.' . $valueName);
                $values[$valueId] = [
                    'id' => $valueId,
                    'optionGroupId' => $groupId,
                    'technicalName' => $valueName,
                    'name' => $language === 'en' ? $valueEn : $valueDe,
                    'description' => null,
                    'badges' => null,
                    'mediaUrl' => null,
                    'position' => $valuePosition,
                    'widthAdd' => 0,
                    'heightAdd' => 0,
                    'widthFactor' => 1.0,
                    'heightFactor' => 1.0,
                    'deliveryDays' => $days,
                    'width' => null,
                    'height' => null,
                    'optionSetId' => null,
                    'exclusions' => null,
                    'availability' => $availability,
                ];
            }
        }

        $sets = [];
        foreach (self::setDefinitions() as $name => [$label, $groupNames]) {
            $setId = self::id('set.' . $name);
            $sets[$setId] = [
                'id' => $setId,
                'technicalName' => $name,
                'name' => $label,
                'groups' => array_map(static fn (string $group, int $i) => [
                    'id' => self::id('set.' . $name . '.' . $group),
                    'optionGroupId' => self::id('group.' . $group),
                    'position' => $i + 1,
                    'required' => null,
                ], $groupNames, array_keys($groupNames)),
            ];
        }

        $calculations = [];
        foreach (self::calculationDefinitions() as $name => $calculation) {
            $optionValues = [];
            $position = 0;
            foreach (self::assignmentDefinitions()[$name] as $group => $assigned) {
                foreach ($assigned as $valueName => [$isDefault, $components]) {
                    $optionValues[] = [
                        'id' => self::id('assignment.' . $name . '.' . $group . '.' . $valueName),
                        'optionValueId' => self::id('value.' . $group . '.' . $valueName),
                        'materialId' => null,
                        'active' => true,
                        'isDefault' => $isDefault,
                        'position' => ++$position,
                        'priceComponents' => $components,
                        'tiers' => null,
                        'conditions' => null,
                    ];
                }
            }
            $calculation['optionValues'] = $optionValues;
            $calculation['priceTables'] = [];
            $calculations[$calculation['id']] = $calculation;
        }

        $materials = [];
        foreach (self::materialDefinitions() as $id => $material) {
            $translation = $material['translations'][$language] ?? $material['translations']['de'];
            unset($material['translations']);
            $materials[$id] = $material + $translation + ['mediaUrl' => null, 'supplierId' => null];
        }

        return [
            'product' => ['id' => $productId, 'name' => self::PRODUCTS[$productId][1] ?? ''],
            'profile' => self::profileDefinition($productId),
            'calculations' => $calculations,
            'materials' => $materials,
            'groups' => $groups,
            'values' => $values,
            'sets' => $sets,
            'customerGroupRules' => [],
            'logisticsRules' => self::logisticsRuleDefinitions(),
        ];
    }

    /**
     * Logistikanteil (KONZEPT 3d), Werte aus LOGISTIKPAUSCHALE 3.3 b): Acryl 1.200–2.449 mm Sperrgut 17,77 €/m²,
     * gleitend über die längste Seite 1.000 → 1.400 mm. Kleine Formate (alle bisherigen Beispielfälle) bleiben unverändert.
     *
     * @return list<array<string, mixed>>
     */
    public static function logisticsRuleDefinitions(): array
    {
        return [
            [
                'id' => self::id('logistics.acryl.bulky'),
                'calculationId' => null,
                'materialId' => self::MATERIAL_ACRYL,
                'optionValueId' => null,
                'costType' => 'bulky',
                'form' => 'per_sqm',
                'value' => 17.77,
                'rampFrom' => 1000,
                'rampTo' => 1400,
                'thresholdBasis' => null,
                'thresholdFrom' => null,
                'thresholdTo' => null,
                'minimumAmount' => null,
                'minimumScope' => null,
                'validFrom' => null,
                'active' => true,
                'note' => 'Demo: Comdatek Sperrgut Acryl ab 1.200 mm (Auswertung 03–10/2026)',
            ],
        ];
    }
}
