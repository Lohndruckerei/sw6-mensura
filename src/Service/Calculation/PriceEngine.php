<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Service\Calculation;

/**
 * Preis-Engine (docs/KONZEPT.md Abschnitt 4), reines PHP ohne DAL.
 *
 * Ablauf: normalize() prüft die Konfiguration gegen Produktprofil, Kalkulation, Optionssets und Bedingungen,
 * ersetzt ungültige Werte (Hinweis) und meldet Formatfehler (Fehler mit `fix`). price() rechnet die Standardformel
 * für eine Seite (`sale` = VK, `purchase` = EK). calculate() verbindet beides zur Store-API-Antwort und zum
 * Rechenweg für den Bestell-Schnappschuss (mit EK, nie in die Store-API).
 *
 *   B, H          = Endformat + Formatzugaben der Optionen (mm)
 *   Fläche        = abgerechnete Fläche je Stück: max(Mindestfläche, aufrunden(B × H / 1e6 × Menge, Flächenrundung)) / Menge
 *                   (areaScope `total`, Standard; bei `piece` Mindestfläche/Rundung je Stück, siehe billedArea())
 *   Umfang        = 2 × (B + H) / 1000
 *   Satz/m²       = (Materialpreis[Staffel] bzw. base_area + Σ per_area) × Π factor
 *   Stückpreis    = Satz/m² × Fläche (bzw. Preistabelle) + Σ per_piece + Umfang × Σ per_perimeter
 *   Zwischensumme = Stückpreis × Menge + Motive × Σ per_motif + Einrichtepauschale + Σ per_order
 *                   × (1 − Staffelrabatt) × (1 + Σ percent) × (1 + Aufschlag Produktprofil)    [Aufschlag nur VK]
 *   Gesamt        = max(Mindestpreis, Zwischensumme) × (1 + Kundengruppen-Aufschlag) → gerundet  [Kundengruppe nur VK]
 */
final class PriceEngine
{
    private const MAX_SET_DEPTH = 20;

    /** Feste Obergrenzen, auch wenn Kalkulation und Profil keine setzen (Schutz vor Unsinns-/Überlaufeingaben). */
    public const HARD_MAX_MOTIFS = 1000;
    public const HARD_MAX_QUANTITY = 1000000;
    private const HARD_MAX_SIDE = 1000000;

    public function calculate(array $input, Catalog $catalog, CalculationContext $context, bool $matrix = false): CalculationResult
    {
        $messages = new Messages($context->language);
        $config = $this->normalize($input, $catalog, $messages, $context);

        $result = new CalculationResult($config, $catalog, $context);
        if ($config->isValid()) {
            $sale = $this->price($config, $catalog, $context, 'sale');
            $purchase = $this->price($config, $catalog, $context, 'purchase');
            if ($sale === null) {
                $config->errors[] = $messages->message('price.not_available');
            } else {
                $calculation = $catalog->calculation($config->calculationId);
                if ($sale['minimumApplied']) {
                    $config->hints[] = $messages->message('price.minimum_applied', ['price' => $messages->money($sale['minimumDisplayed'])]);
                }
                $best = $this->bestPrice($config, $catalog, $context, $sale);
                if ($best !== null) {
                    if (($calculation['bestPriceMode'] ?? 'hint') === 'apply') {
                        $sale['total'] = $best['total'];
                        $sale['minimumApplied'] = false;
                        $sale['bestPriceQuantity'] = $best['quantity'];
                        $config->hints[] = $messages->message('price.best_price_applied', ['quantity' => $messages->number($best['quantity']), 'price' => $messages->money($best['total'])]);
                    } else {
                        $config->hints[] = $messages->message('price.best_price', ['quantity' => $messages->number($best['quantity']), 'price' => $messages->money($best['total'])], 'perMotif', ['perMotif' => $best['perMotif']]);
                    }
                }
                $result->setPrices($sale, $purchase);
            }
        }
        $result->setDelivery($this->delivery($config, $catalog, $context, $messages));
        if ($matrix && $config->isValid()) {
            $result->setMatrix($this->matrix($config, $catalog, $context, $messages));
        }

        return $result;
    }

    // ------------------------------------------------------------------------------------------------
    // Normalisierung
    // ------------------------------------------------------------------------------------------------

    public function normalize(array $input, Catalog $catalog, Messages $messages, CalculationContext $context): NormalizedConfiguration
    {
        $config = new NormalizedConfiguration();
        $config->productId = $catalog->productId();
        $defaults = $catalog->profile()['defaults'] ?? [];
        $defaults = \is_array($defaults) ? $defaults : [];

        $removedHints = [];
        $optionHints = [];
        $otherHints = [];

        // Material: muss im Profil erlaubt sein
        $profileMaterials = $catalog->profileMaterials();
        if ($profileMaterials === []) {
            $config->errors[] = $messages->message('material.unavailable', [], 'materialId');

            return $config;
        }
        $entry = $this->findMaterial($profileMaterials, $input['materialId'] ?? null)
            ?? $this->findMaterial($profileMaterials, $defaults['materialId'] ?? null)
            ?? $profileMaterials[0];
        if (!empty($input['materialId']) && $input['materialId'] !== $entry['materialId']) {
            $otherHints[] = $messages->message('material.reset', ['new' => $catalog->material($entry['materialId'])['name'] ?? ''], 'materialId');
        }
        $config->materialId = $entry['materialId'];
        $config->calculationId = $entry['calculationId'];
        $config->profileMaterialSurcharge = $entry['surchargePercent'];
        $calculation = $catalog->calculation($config->calculationId);
        $material = $catalog->material($config->materialId);
        $calculationName = (string) ($calculation['name'] ?? '');
        $limits = $this->limits($catalog, $calculation, $material);

        // Format
        $format = \is_array($input['format'] ?? null) ? $input['format'] : (\is_array($defaults['format'] ?? null) ? $defaults['format'] : []);
        $config->width = self::count($format['width'] ?? $limits['widthMin'] ?? 100, self::HARD_MAX_SIDE);
        $config->height = self::count($format['height'] ?? $limits['heightMin'] ?? 100, self::HARD_MAX_SIDE);
        $formatValueId = $format['formatValueId'] ?? null;
        if (\is_string($formatValueId) && $formatValueId !== '') {
            $formatValue = $catalog->value($formatValueId);
            $formatGroup = $catalog->group($formatValue['optionGroupId'] ?? null);
            if ($formatValue !== null && ($formatGroup['type'] ?? null) === 'format'
                && $catalog->assignment($config->calculationId, $formatValueId, $config->materialId) !== null
                && $this->allowedByProfile($catalog, $formatGroup['id'], $config->materialId, $formatValueId)) {
                $config->formatValueId = $formatValueId;
                $config->width = (int) ($formatValue['width'] ?? $config->width);
                $config->height = (int) ($formatValue['height'] ?? $config->height);
            } else {
                $otherHints[] = $messages->message('format.value_reset', [], 'format.formatValueId');
            }
        }
        if (($calculation['formatMode'] ?? 'free') === 'fixed' && $config->formatValueId === null) {
            $config->errors[] = $messages->message('format.fixed_required', [], 'format.formatValueId');
        }

        // Menge
        // eine über der festen Grenze, damit die Prüfung unten greift (kein Überlauf bei Riesenzahlen)
        $config->motifs = self::count($input['motifs'] ?? $defaults['motifs'] ?? 1, self::HARD_MAX_MOTIFS + 1);
        $config->perMotif = self::count($input['perMotif'] ?? $defaults['perMotif'] ?? 1, self::HARD_MAX_QUANTITY + 1);
        // keine stille Korrektur: ungültige Mengen (0, negativ, Text, Nachkommastellen) als Hinweis melden
        foreach (['motifs', 'perMotif'] as $field) {
            if (!\array_key_exists($field, $input)) {
                continue;
            }
            $raw = $input[$field];
            $wholeNumber = (\is_int($raw) || \is_float($raw) || (\is_string($raw) && is_numeric($raw)))
                && (float) $raw >= 1 && floor((float) $raw) === (float) $raw;
            if (!$wholeNumber) {
                $otherHints[] = $messages->message('quantity.reset', ['old' => \is_scalar($raw) ? (string) $raw : '?', 'new' => $messages->number($config->{$field})], $field);
            }
        }
        if (isset($input['reference']) && \is_string($input['reference'])) {
            $config->reference = mb_substr($input['reference'], 0, 255);
        }
        if (isset($input['artwork']) && \is_array($input['artwork'])) {
            // bis zum Druckdaten-Plugin nur eine kleine Liste einfacher Einträge übernehmen (kein unbegrenzter Payload)
            $config->artwork = self::limitArtwork($input['artwork']);
        }

        // Optionen: Start-Set → Set des Materials → Sets der gewählten Werte (rekursiv)
        $requested = \is_array($input['options'] ?? null) ? $input['options'] : (\is_array($defaults['options'] ?? null) ? $defaults['options'] : []);
        $consumed = [];
        $queue = array_values(array_filter([$calculation['optionSetId'] ?? null, $material['optionSetId'] ?? null]));
        $visitedSets = [];
        $chosenValues = [];
        for ($i = 0; $i < \count($queue) && $i < self::MAX_SET_DEPTH; ++$i) {
            $setId = $queue[$i];
            if (isset($visitedSets[$setId])) {
                continue;
            }
            $visitedSets[$setId] = true;
            foreach ($catalog->setGroupIds($setId) as $groupId) {
                $group = $catalog->group($groupId);
                if ($group === null || ($group['type'] ?? 'standard') !== 'standard' || isset($config->options[$groupId]) || \in_array($groupId, $config->activeGroupIds, true)) {
                    continue;
                }
                $required = $this->setGroupRequired($catalog, $setId, $groupId) ?? (bool) ($group['required'] ?? true);
                $rule = $catalog->profileRule($groupId, $config->materialId);
                $mode = $rule['mode'] ?? 'inherit';
                $base = $this->allowedValues($catalog, $config->calculationId, $config->materialId, $groupId, $rule);
                $compatible = array_values(array_filter($base, fn (string $v) => $this->conflict($catalog, $config, $v, $chosenValues) === null));

                $technicalName = $group['technicalName'];
                $requestedName = isset($requested[$technicalName]) && \is_string($requested[$technicalName]) ? $requested[$technicalName] : null;
                $consumed[$technicalName] = true;
                $requestedId = $requestedName !== null ? $catalog->valueIdByName($groupId, $requestedName) : null;

                if ($compatible === []) {
                    if ($required) {
                        $config->errors[] = $messages->message('option.unavailable', ['group' => $group['name']], 'options.' . $technicalName);
                    }
                    continue;
                }

                // Keine Vorauswahl (Kalkulation bzw. Profil): ohne gültige Wahl des Kunden bleibt die Gruppe offen;
                // Pflichtgruppe → Fehler option.choose, kein Preis (docs/contract/README.md „Vorauswahl“)
                $requestedValid = $requestedId !== null && \in_array($requestedId, $compatible, true);
                if (!$requestedValid && $mode !== 'fixed' && $mode !== 'hidden' && !$this->preselects($catalog, $config->calculationId, $groupId, $config->materialId)) {
                    $config->pendingGroupIds[] = $groupId;
                    // sichtbar und in Reihenfolge (activeGroups), nur noch ohne Wert
                    $config->activeGroupIds[] = $groupId;
                    if ($required) {
                        $config->errors[] = $messages->message('option.choose', ['group' => $group['name']], 'options.' . $technicalName);
                    }
                    continue;
                }

                $default = $this->defaultValue($catalog, $config->calculationId, $config->materialId, $compatible, $rule);
                if ($requestedId !== null && \in_array($requestedId, $compatible, true)) {
                    $chosen = $requestedId;
                } else {
                    $chosen = $default;
                    if ($requestedName !== null && $mode !== 'hidden') {
                        $oldName = $requestedId !== null ? ($catalog->value($requestedId)['name'] ?? $requestedName) : $requestedName;
                        $newName = $catalog->value($chosen)['name'] ?? '';
                        if ($mode === 'fixed') {
                            $optionHints[] = $messages->message('option.fixed', ['group' => $group['name'], 'new' => $newName], 'options.' . $technicalName);
                        } elseif ($requestedId !== null && \in_array($requestedId, $base, true)) {
                            $other = $this->conflict($catalog, $config, $requestedId, $chosenValues);
                            $optionHints[] = $messages->message('option.excluded', ['group' => $group['name'], 'old' => $oldName, 'other' => $catalog->value($other)['name'] ?? '', 'new' => $newName], 'options.' . $technicalName);
                        } else {
                            $optionHints[] = $messages->message('option.reset', ['group' => $group['name'], 'old' => $oldName, 'calculation' => $calculationName, 'new' => $newName], 'options.' . $technicalName);
                        }
                    }
                }

                $config->options[$groupId] = $chosen;
                $chosenValues[$groupId] = $chosen;
                if ($mode === 'hidden') {
                    $config->hiddenGroupIds[] = $groupId;
                } else {
                    $config->activeGroupIds[] = $groupId;
                }
                $followSet = $catalog->value($chosen)['optionSetId'] ?? null;
                if ($followSet !== null) {
                    $queue[] = $followSet;
                }
            }
        }
        // sichtbare Gruppen zuerst, ausgeblendete danach
        $ordered = [];
        foreach (array_merge($config->activeGroupIds, $config->hiddenGroupIds) as $groupId) {
            if (isset($config->options[$groupId])) {
                $ordered[$groupId] = $config->options[$groupId];
            }
        }
        $config->options = $ordered;

        // Gewünschte Optionen, die es hier nicht (mehr) gibt
        foreach ($requested as $technicalName => $valueName) {
            if (isset($consumed[$technicalName])) {
                continue;
            }
            $group = $catalog->groupByName((string) $technicalName);
            if ($group === null) {
                $removedHints[] = $messages->message('option.unknown', ['group' => (string) $technicalName], 'options.' . $technicalName);
            } elseif (($group['type'] ?? 'standard') === 'standard') {
                $removedHints[] = $messages->message('option.removed', ['group' => $group['name'], 'calculation' => $calculationName], 'options.' . $technicalName);
            }
        }

        // Format prüfen (Grenzen aus Kalkulation, Material, Profil; Drehen erlaubt?)
        $this->checkFormat($config, $calculation, $material, $limits, $messages);

        // Menge prüfen
        $profile = $catalog->profile();
        $motifMode = (string) ($calculation['motifMode'] ?? 'multiply');
        $quantity = $config->quantity($motifMode);
        $maxMotifs = min($limits['maxMotifs'] ?? self::HARD_MAX_MOTIFS, self::HARD_MAX_MOTIFS);
        if ($config->motifs > $maxMotifs) {
            $config->errors[] = $messages->message('quantity.motifs_max', ['limit' => $messages->number($maxMotifs)], 'motifs', ['motifs' => $maxMotifs]);
        }
        $minQuantity = isset($profile['minQuantity']) ? (int) $profile['minQuantity'] : null;
        if ($minQuantity !== null && $quantity < $minQuantity) {
            $config->errors[] = $messages->message('quantity.min', ['limit' => $messages->number($minQuantity)], 'perMotif', ['perMotif' => $this->perMotifFor($config, $motifMode, $minQuantity, true)]);
        }
        $maxQuantity = min($limits['maxQuantity'] ?? self::HARD_MAX_QUANTITY, self::HARD_MAX_QUANTITY);
        if ($quantity > $maxQuantity) {
            $config->errors[] = $messages->message('quantity.max', ['limit' => $messages->number($maxQuantity)], 'perMotif', ['perMotif' => $this->perMotifFor($config, $motifMode, $maxQuantity, false)]);
        }

        // Lieferzeit
        $deliveryHint = $this->normalizeDelivery($config, $catalog, $input['deliveryValue'] ?? ($defaults['deliveryValue'] ?? null), isset($input['deliveryValue']), $messages);
        if ($deliveryHint !== null) {
            $otherHints[] = $deliveryHint;
        }

        $config->hints = array_merge($otherHints, $removedHints, $optionHints);

        return $config;
    }

    /**
     * Druckdaten-Verweise aus dem Client: höchstens 20 Einträge, je Eintrag nur Skalare mit kurzen Schlüsseln/Werten.
     *
     * @param array<mixed> $artwork
     *
     * @return list<array<string, scalar|null>>
     */
    private static function limitArtwork(array $artwork): array
    {
        $result = [];
        foreach (\array_slice(array_values($artwork), 0, 20) as $entry) {
            if (!\is_array($entry)) {
                continue;
            }
            $clean = [];
            foreach (\array_slice($entry, 0, 10, true) as $key => $value) {
                if (\is_string($key) && \strlen($key) <= 64 && (\is_scalar($value) || $value === null)) {
                    $clean[$key] = \is_string($value) ? mb_substr($value, 0, 500) : $value;
                }
            }
            if ($clean !== []) {
                $result[] = $clean;
            }
        }

        return $result;
    }

    /**
     * Ganzzahl ≥ 1 aus einer Eingabe (Zahl oder numerischer Text, Nachkommastellen abgeschnitten), höchstens `$cap` –
     * ohne Überlauf bei sehr großen Werten (z. B. 1e20 oder PHP_INT_MAX); Nicht-Zahlen ergeben 1.
     */
    private static function count(mixed $value, int $cap): int
    {
        if (!is_numeric($value)) {
            return 1;
        }

        return (int) min(max((float) $value, 1.0), (float) $cap);
    }

    /**
     * Wirksame Grenzen: Kalkulation, enger durch Material (Max.-Format) und Produktprofil.
     *
     * @param array<string, mixed>|null $calculation
     * @param array<string, mixed>|null $material
     *
     * @return array{widthMin: int|null, widthMax: int|null, heightMin: int|null, heightMax: int|null, allowRotation: bool, maxMotifs: int|null, maxQuantity: int|null}
     */
    public function limits(Catalog $catalog, ?array $calculation, ?array $material): array
    {
        $profile = $catalog->profile();
        $max = static fn (?int ...$v) => ($f = array_filter($v, static fn ($x) => $x !== null)) === [] ? null : max($f);
        $min = static fn (?int ...$v) => ($f = array_filter($v, static fn ($x) => $x !== null)) === [] ? null : min($f);
        $int = static fn (mixed $v) => $v === null ? null : (int) $v;

        return [
            'widthMin' => $max($int($calculation['widthMin'] ?? null), $int($profile['widthMin'] ?? null)),
            'widthMax' => $min($int($calculation['widthMax'] ?? null), $int($material['widthMax'] ?? null), $int($profile['widthMax'] ?? null)),
            'heightMin' => $max($int($calculation['heightMin'] ?? null), $int($profile['heightMin'] ?? null)),
            'heightMax' => $min($int($calculation['heightMax'] ?? null), $int($material['heightMax'] ?? null), $int($profile['heightMax'] ?? null)),
            'allowRotation' => (bool) ($calculation['allowRotation'] ?? true),
            'maxMotifs' => $int($calculation['maxMotifs'] ?? null),
            'maxQuantity' => $min($int($calculation['maxQuantity'] ?? null), $int($profile['maxQuantity'] ?? null)),
        ];
    }

    /**
     * Werte einer Gruppe, die Kalkulation (für dieses Material) und Produktprofil erlauben, in Reihenfolge.
     *
     * @param array<string, mixed>|null $rule
     *
     * @return list<string>
     */
    public function allowedValues(Catalog $catalog, string $calculationId, string $materialId, string $groupId, ?array $rule): array
    {
        $values = [];
        foreach ($catalog->valueIdsOfGroup($groupId) as $valueId) {
            if ($catalog->assignment($calculationId, $valueId, $materialId) === null) {
                continue;
            }
            if (!$this->ruleAllows($rule, $valueId)) {
                continue;
            }
            $values[] = $valueId;
        }
        // Reihenfolge der Kalkulation (position der Zuordnung), sonst die des Werts
        usort($values, static function (string $a, string $b) use ($catalog, $calculationId, $materialId) {
            return ($catalog->assignment($calculationId, $a, $materialId)['position'] ?? 0) <=> ($catalog->assignment($calculationId, $b, $materialId)['position'] ?? 0);
        });

        return $values;
    }

    /**
     * Wird in dieser Gruppe ein Wert vorausgewählt? Profilregel (Material vor allgemein) vor Kalkulation; Standard ja.
     */
    public function preselects(Catalog $catalog, string $calculationId, string $groupId, ?string $materialId): bool
    {
        foreach ([$materialId !== null ? $catalog->profileRule($groupId, $materialId) : null, $catalog->profileRule($groupId, null)] as $rule) {
            if (\is_array($rule) && isset($rule['preselect']) && $rule['preselect'] !== null) {
                return (bool) $rule['preselect'];
            }
        }
        $setting = $catalog->calculation($calculationId)['preselect'] ?? null;

        return !\is_array($setting) || ($setting[$groupId] ?? true) !== false;
    }

    /** @param array<string, mixed>|null $rule */
    private function ruleAllows(?array $rule, string $valueId): bool
    {
        $mode = $rule['mode'] ?? 'inherit';

        return match ($mode) {
            'restrict' => \in_array($valueId, $rule['valueIds'] ?? [], true),
            'fixed', 'hidden' => ($rule['defaultValueId'] ?? null) === null || $rule['defaultValueId'] === $valueId,
            default => true,
        };
    }

    private function allowedByProfile(Catalog $catalog, string $groupId, string $materialId, string $valueId): bool
    {
        return $this->ruleAllows($catalog->profileRule($groupId, $materialId), $valueId);
    }

    /**
     * @param list<string>              $allowed
     * @param array<string, mixed>|null $rule
     */
    private function defaultValue(Catalog $catalog, string $calculationId, string $materialId, array $allowed, ?array $rule): string
    {
        $ruleDefault = $rule['defaultValueId'] ?? null;
        if ($ruleDefault !== null && \in_array($ruleDefault, $allowed, true)) {
            return $ruleDefault;
        }
        foreach ($allowed as $valueId) {
            if (($catalog->assignment($calculationId, $valueId, $materialId)['isDefault'] ?? false) === true) {
                return $valueId;
            }
        }

        return $allowed[0];
    }

    /**
     * Bedingungen: Ausschlüsse des Werts (Bibliothek + Kalkulation) und Voraussetzungen.
     * Liefert den Wert, mit dem `valueId` kollidiert (oder der fehlt), sonst null.
     *
     * @param array<string, string> $chosen groupId → valueId der bisher gewählten Werte
     */
    private function conflict(Catalog $catalog, NormalizedConfiguration $config, string $valueId, array $chosen): ?string
    {
        $assignment = $catalog->assignment((string) $config->calculationId, $valueId, (string) $config->materialId);
        $conditions = \is_array($assignment['conditions'] ?? null) ? $assignment['conditions'] : [];
        $excludes = array_merge($conditions['excludes'] ?? [], $catalog->value($valueId)['exclusions'] ?? []);
        foreach ($chosen as $other) {
            if (\in_array($other, $excludes, true)) {
                return $other;
            }
            $otherAssignment = $catalog->assignment((string) $config->calculationId, $other, (string) $config->materialId);
            $otherExcludes = array_merge($otherAssignment['conditions']['excludes'] ?? [], $catalog->value($other)['exclusions'] ?? []);
            if (\in_array($valueId, $otherExcludes, true)) {
                return $other;
            }
        }
        foreach ($conditions['requires'] ?? [] as $required) {
            $requiredGroup = $catalog->value($required)['optionGroupId'] ?? null;
            if ($requiredGroup === null || ($chosen[$requiredGroup] ?? null) !== $required) {
                return $required;
            }
        }

        return null;
    }

    private function setGroupRequired(Catalog $catalog, string $setId, string $groupId): ?bool
    {
        foreach ($catalog->optionSet($setId)['groups'] ?? [] as $row) {
            if ($row['optionGroupId'] === $groupId && isset($row['required'])) {
                return (bool) $row['required'];
            }
        }

        return null;
    }

    /**
     * @param list<array{calculationId: string, materialId: string, surchargePercent: float}> $profileMaterials
     *
     * @return array{calculationId: string, materialId: string, surchargePercent: float}|null
     */
    private function findMaterial(array $profileMaterials, mixed $materialId): ?array
    {
        foreach ($profileMaterials as $entry) {
            if ($entry['materialId'] === $materialId) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $calculation
     * @param array<string, mixed>|null $material
     * @param array<string, mixed>      $limits
     */
    private function checkFormat(NormalizedConfiguration $config, ?array $calculation, ?array $material, array $limits, Messages $messages): void
    {
        $fits = static fn (int $w, int $h) => ($limits['widthMin'] === null || $w >= $limits['widthMin'])
            && ($limits['widthMax'] === null || $w <= $limits['widthMax'])
            && ($limits['heightMin'] === null || $h >= $limits['heightMin'])
            && ($limits['heightMax'] === null || $h <= $limits['heightMax']);

        if ($fits($config->width, $config->height)) {
            return;
        }
        if ($limits['allowRotation'] && $fits($config->height, $config->width)) {
            $config->rotated = true;

            return;
        }

        $name = (string) ($material['name'] ?? $calculation['name'] ?? '');
        $checks = [
            ['width', 'Min', $config->width, $limits['widthMin'], static fn ($v, $l) => $v < $l],
            ['width', 'Max', $config->width, $limits['widthMax'], static fn ($v, $l) => $v > $l],
            ['height', 'Min', $config->height, $limits['heightMin'], static fn ($v, $l) => $v < $l],
            ['height', 'Max', $config->height, $limits['heightMax'], static fn ($v, $l) => $v > $l],
        ];
        foreach ($checks as [$dimension, $kind, $value, $limit, $violates]) {
            if ($limit !== null && $violates($value, $limit)) {
                $config->errors[] = $messages->message(
                    'format.' . $dimension . '_' . strtolower($kind),
                    ['material' => $name, 'limit' => $messages->number($limit)],
                    'format.' . $dimension,
                    ['format.' . $dimension => $limit],
                );
            }
        }
    }

    private function perMotifFor(NormalizedConfiguration $config, string $motifMode, int $quantity, bool $up): int
    {
        if ($motifMode === 'split') {
            return $quantity;
        }

        return max(1, $up ? (int) ceil($quantity / $config->motifs) : intdiv($quantity, $config->motifs));
    }

    // ------------------------------------------------------------------------------------------------
    // Lieferzeit
    // ------------------------------------------------------------------------------------------------

    private function normalizeDelivery(NormalizedConfiguration $config, Catalog $catalog, mixed $requested, bool $explicit, Messages $messages): ?array
    {
        $deliveryGroup = null;
        foreach ($catalog->groups() as $groupId => $group) {
            if (($group['type'] ?? '') !== 'delivery') {
                continue;
            }
            $allowed = $this->allowedValues($catalog, (string) $config->calculationId, (string) $config->materialId, $groupId, $catalog->profileRule($groupId, $config->materialId));
            if ($allowed !== []) {
                $deliveryGroup = $groupId;
                $config->deliveryValueIds = $allowed;
                break;
            }
        }
        if ($deliveryGroup === null) {
            return null;
        }

        $available = array_values(array_filter($config->deliveryValueIds, fn (string $v) => $this->deliveryReason($config, $catalog, $v, null, $messages) === null));
        $requestedId = \is_string($requested) ? $catalog->valueIdByName($deliveryGroup, $requested) : null;
        if ($requestedId !== null && \in_array($requestedId, $available, true)) {
            $config->deliveryValueId = $requestedId;

            return null;
        }
        $pool = $available !== [] ? $available : $config->deliveryValueIds;
        $config->deliveryValueId = $this->defaultValue($catalog, (string) $config->calculationId, (string) $config->materialId, $pool, $catalog->profileRule($deliveryGroup, $config->materialId));
        if ($explicit && \is_string($requested)) {
            $old = $requestedId !== null ? ($catalog->value($requestedId)['name'] ?? $requested) : $requested;

            return $messages->message('delivery.reset', ['old' => $old, 'new' => $catalog->value($config->deliveryValueId)['name'] ?? ''], 'deliveryValue');
        }

        return null;
    }

    /** Grund, warum eine Lieferzeit nicht möglich ist (Verfügbarkeitsregeln am Wert), sonst null. */
    private function deliveryReason(NormalizedConfiguration $config, Catalog $catalog, string $valueId, ?int $perMotif, Messages $messages): ?string
    {
        $availability = $catalog->value($valueId)['availability'] ?? null;
        if (!\is_array($availability) || $availability === []) {
            return null;
        }
        $calculation = $catalog->calculation($config->calculationId);
        $quantity = $config->quantity((string) ($calculation['motifMode'] ?? 'multiply'), $perMotif);
        if (!empty($availability['materialIds']) && !\in_array($config->materialId, $availability['materialIds'], true)) {
            return $messages->text('delivery.material', ['material' => $catalog->material($config->materialId)['name'] ?? '']);
        }
        if (isset($availability['maxQuantity']) && $quantity > (int) $availability['maxQuantity']) {
            return $messages->text('delivery.max_quantity', ['limit' => $messages->number((int) $availability['maxQuantity'])]);
        }
        if (isset($availability['maxArea'])) {
            [$width, $height] = $this->dimensions($config, $catalog);
            $area = $width * $height / 1_000_000 * $quantity;
            if ($area > (float) $availability['maxArea'] + 1e-9) {
                return $messages->text('delivery.max_area', ['limit' => $messages->plain((float) $availability['maxArea'])]);
            }
        }

        return null;
    }

    /** @return list<array{value: string, available: bool, date: string|null, reason: string|null}> */
    private function delivery(NormalizedConfiguration $config, Catalog $catalog, CalculationContext $context, Messages $messages): array
    {
        $calendar = new DeliveryCalendar($context);
        $rows = [];
        foreach ($config->deliveryValueIds as $valueId) {
            $value = $catalog->value($valueId);
            $reason = $this->deliveryReason($config, $catalog, $valueId, null, $messages);
            $rows[] = [
                'value' => (string) $value['technicalName'],
                'available' => $reason === null,
                'date' => $reason === null && isset($value['deliveryDays']) ? $calendar->dateFor((int) $value['deliveryDays'])->format('Y-m-d') : null,
                'reason' => $reason,
            ];
        }

        return $rows;
    }

    // ------------------------------------------------------------------------------------------------
    // Preis
    // ------------------------------------------------------------------------------------------------

    /**
     * Rechnet eine Seite. `perMotif`/`deliveryValueId` überschreiben die Konfiguration (Matrix, Bestpreis).
     *
     * @return array<string, mixed>|null null = keine passende Preistabelle
     */
    public function price(NormalizedConfiguration $config, Catalog $catalog, CalculationContext $context, string $side, ?int $perMotif = null, ?string $deliveryValueId = null): ?array
    {
        $calculation = $catalog->calculation($config->calculationId) ?? [];
        $material = $catalog->material($config->materialId) ?? [];
        $profile = $catalog->profile();
        $perMotif ??= $config->perMotif;
        $motifMode = (string) ($calculation['motifMode'] ?? 'multiply');
        $quantity = $config->quantity($motifMode, $perMotif);
        $tierQuantity = ($calculation['tierScope'] ?? 'total') === 'per_motif' ? $quantity / max(1, $config->motifs) : (float) $quantity;

        [$width, $height] = $this->dimensions($config, $catalog);
        // Fläche der Position = Länge × Breite × Stück; Mindestfläche/Rundung je Position (areaScope total) oder je Stück
        $rawPieceArea = $width * $height / 1_000_000;
        $areaRules = $this->areaRules($calculation, $material);
        $billedArea = $this->billedArea($areaRules, $width, $height, $quantity);
        $pieceArea = $billedArea / max(1, $quantity); // abgerechnete Fläche je Stück (für Preise je m²)
        $perimeter = 2 * ($width + $height) / 1000;
        $unitAmount = match ($calculation['unit'] ?? 'area') {
            'area' => $pieceArea,
            'length' => max($width, $height) / 1000,
            default => 1.0,
        };
        $measure = static fn (string $basis) => match ($basis) {
            'quantity' => $tierQuantity,
            'total_area' => $pieceArea * $tierQuantity,
            'piece_area' => ($calculation['areaScope'] ?? 'total') === 'piece' ? $pieceArea : $rawPieceArea,
            default => 0.0,
        };
        $lines = [];

        $valueIds = $this->selectedValueIds($config, $deliveryValueId);

        // Preisanteile sammeln
        $components = [];
        $add = static function (array $list, string $source) use (&$components, $side): void {
            foreach ($list as $component) {
                if (!\is_array($component) || !isset($component['type'])) {
                    continue;
                }
                $amount = $component[$side] ?? null;
                if (!is_numeric($amount)) {
                    continue;
                }
                $components[] = ['type' => (string) $component['type'], 'amount' => (float) $amount, 'source' => $source];
            }
        };
        $add(\is_array($calculation['priceComponents'] ?? null) ? $calculation['priceComponents'] : [], 'calculation');
        $add(\is_array($material['priceComponents'] ?? null) ? $material['priceComponents'] : [], 'material');
        if ($side === 'sale') {
            $add(\is_array($profile['priceComponents'] ?? null) ? $profile['priceComponents'] : [], 'profile');
        }
        $baseArea = null;
        foreach ($valueIds as $valueId) {
            $assignment = $catalog->assignment((string) $config->calculationId, $valueId, (string) $config->materialId);
            if ($assignment === null) {
                continue;
            }
            $list = \is_array($assignment['priceComponents'] ?? null) ? $assignment['priceComponents'] : [];
            $tier = Tiers::forSide($assignment['tiers'] ?? null, $calculation['tierBasis'] ?? null, $side);
            $step = $tier !== null ? Tiers::pick($tier, $measure($tier['basis'])) : null;
            $source = 'option:' . ($catalog->value($valueId)['technicalName'] ?? $valueId);
            $primary = null;
            foreach ($list as $index => $component) {
                if (($component['type'] ?? null) === 'base_area') {
                    $primary = $index;
                }
            }
            $primary ??= array_key_first($list);
            foreach ($list as $index => $component) {
                if (!\is_array($component) || !is_numeric($component[$side] ?? null)) {
                    continue;
                }
                $amount = (float) $component[$side];
                if ($step !== null && isset($step['price']) && $index === $primary) {
                    $amount = $step['price'];
                } elseif ($step !== null && isset($step['discount'])) {
                    $amount *= 1 - $step['discount'] / 100;
                }
                if ($component['type'] === 'base_area') {
                    $baseArea = ['amount' => $amount, 'source' => $source];
                    continue;
                }
                $components[] = ['type' => (string) $component['type'], 'amount' => $amount, 'source' => $source];
            }
        }
        $sum = static fn (string $type) => array_sum(array_map(static fn (array $c) => $c['amount'], array_filter($components, static fn (array $c) => $c['type'] === $type)));
        // leere Liste → 1; ein gepflegter Faktor 0 bleibt 0 (nicht still zu 1)
        $factors = array_map(static fn (array $c) => (float) $c['amount'], array_values(array_filter($components, static fn (array $c) => $c['type'] === 'factor')));
        $factor = $factors === [] ? 1.0 : array_product($factors);

        // Staffel der Kalkulation (Preis ersetzt Materialpreis, Rabatt auf Zwischensumme)
        $calculationTier = Tiers::forSide($calculation['tiers'] ?? null, $calculation['tierBasis'] ?? null, $side);

        // Preistabelle (ersetzt Satz × Fläche)
        $tablePiece = null;
        $tables = self::activePriceTables($calculation);
        if ($tables !== []) {
            $table = $this->matchPriceTable($tables, (string) $config->materialId, $valueIds);
            if ($table === null) {
                return null;
            }
            $row = $this->priceTableRow($table, $tierQuantity, $side);
            if ($row === null) {
                return null;
            }
            $tablePiece = ($table['priceType'] ?? 'piece') === 'total' ? $row['price'] / max(1, $row['quantity']) : $row['price'];
            $lines[] = ['key' => 'priceTable', 'table' => $table['name'] ?? ($table['id'] ?? null), 'rowQuantity' => $row['quantity'], 'piece' => $tablePiece];
        }

        // Materialpreis bzw. base_area
        if ($baseArea !== null) {
            $rate = $baseArea['amount'];
            $lines[] = ['key' => 'baseArea', 'source' => $baseArea['source'], 'amount' => $rate];
        } else {
            $rate = (float) ($side === 'sale' ? ($material['priceSale'] ?? 0) : ($material['pricePurchase'] ?? 0));
            $materialTier = Tiers::forSide($material['tiers'] ?? null, $material['tierBasis'] ?? $calculation['tierBasis'] ?? null, $side);
            $tier = $materialTier ?? ($calculationTier !== null && isset($calculationTier['steps'][0]['price']) ? $calculationTier : null);
            if ($tier !== null) {
                $step = Tiers::pick($tier, $measure($tier['basis']));
                if ($step !== null && isset($step['price'])) {
                    $rate = $step['price'];
                } elseif ($step !== null && isset($step['discount'])) {
                    $rate *= 1 - $step['discount'] / 100;
                }
            }
            $lines[] = ['key' => 'material', 'amount' => $rate];
        }

        $perArea = $sum('per_area');
        if ($tablePiece !== null) {
            $piece = $tablePiece + $perArea * $factor * $pieceArea;
        } else {
            $piece = ($rate + $perArea) * $factor * $unitAmount;
        }
        $piece += $sum('per_piece') + $perimeter * $sum('per_perimeter');
        $setup = (float) ($side === 'sale' ? ($calculation['setupPriceSale'] ?? 0) : ($calculation['setupPricePurchase'] ?? 0));
        $subtotal = $piece * $quantity + $config->motifs * $sum('per_motif') + $setup + $sum('per_order');

        $discount = 0.0;
        if ($calculationTier !== null && !isset($calculationTier['steps'][0]['price'])) {
            $value = $calculationTier['basis'] === 'total_price' ? $subtotal : $measure($calculationTier['basis']);
            $step = Tiers::pick($calculationTier, $value);
            $discount = (float) ($step['discount'] ?? 0);
        }
        $subtotal *= 1 - $discount / 100;
        $percent = $sum('percent');
        $subtotal *= 1 + $percent / 100;
        $surcharge = 0.0;
        if ($side === 'sale') {
            $surcharge = (float) ($profile['surchargePercent'] ?? 0) + $config->profileMaterialSurcharge;
            $subtotal *= 1 + $surcharge / 100;
        }

        if ($side === 'sale') {
            $minimum = max((float) ($material['minimumPriceSale'] ?? $calculation['minimumPriceSale'] ?? 0), (float) ($profile['minimumPriceSale'] ?? 0));
        } else {
            $minimum = (float) ($material['minimumPricePurchase'] ?? $calculation['minimumPricePurchase'] ?? 0);
        }
        $minimumApplied = $minimum > $subtotal + 1e-9;
        $total = max($minimum, $subtotal);

        $customerGroupPercent = 0.0;
        $rounding = null;
        if ($side === 'sale') {
            $rule = $catalog->customerGroupRule((string) $config->calculationId, $context->customerGroupId);
            $customerGroupPercent = $rule['surchargePercent'];
            $rounding = $rule['priceRounding'] ?? (isset($calculation['priceRounding']) ? (float) $calculation['priceRounding'] : null);
            $total *= 1 + $customerGroupPercent / 100;
            $minimum *= 1 + $customerGroupPercent / 100;
        }
        // Basiswährung → Währung des Verkaufskanals (beide Seiten, damit Deckungsbeitrag in einer Währung bleibt)
        $total *= $context->currencyFactor;
        $minimum *= $context->currencyFactor;
        $total = self::roundPrice($total, $rounding);
        if ($minimumApplied && $rounding !== null && $rounding > 0 && $total + 0.005 < round($minimum, 2)) {
            // Endpreis-Rundung (z. B. auf ganze Euro) darf nicht unter den Mindestpreis führen: auf den nächsten
            // Rundungsschritt aufrunden (die normale Cent-Rundung des Mindestpreises selbst bleibt)
            $total = round(ceil(round($minimum, 2) / $rounding - 1e-9) * $rounding, 2);
        }
        // Gesamtpreis bleibt exakt (Entscheidung 09.11.2026): kein „Stückpreis auf Cent × Menge“, weil das große Auflagen
        // mit kleinen Stückpreisen spürbar verteuert (300 Flyer 35,00 € → 36,00 €); der Stückpreis ist nur Anzeige

        return [
            'side' => $side,
            'total' => $total,
            'quantity' => $quantity,
            'perMotif' => $perMotif,
            // Flächenwerte (auch für eigene Formeln und spätere Plugins, z. B. Versand nach Format)
            'pieceArea' => round($pieceArea, 6),
            'rawPieceArea' => round($rawPieceArea, 6),
            'area' => round($rawPieceArea * $quantity, 6),
            'billedArea' => round($billedArea, 6),
            'minArea' => isset($areaRules['minArea']) ? (float) $areaRules['minArea'] : null,
            'areaRounding' => isset($areaRules['areaRounding']) ? (float) $areaRules['areaRounding'] : null,
            'areaScope' => (string) ($calculation['areaScope'] ?? 'total'),
            // Gewicht (kg) aus Gewicht je m² des Materials × tatsächliche Fläche – für Versand-/Verpackungskosten
            'weight' => isset($material['weightPerSqm']) ? [
                'perSqm' => (float) $material['weightPerSqm'],
                'piece' => round($rawPieceArea * (float) $material['weightPerSqm'], 4),
                'total' => round($rawPieceArea * (float) $material['weightPerSqm'] * $quantity, 4),
            ] : null,
            'perimeter' => round($perimeter, 6),
            'width' => $width,
            'height' => $height,
            'minimumApplied' => $minimumApplied,
            'minimumDisplayed' => round($minimum, 2),
            'lines' => array_merge($lines, [
                ['key' => 'components', 'items' => $components],
                ['key' => 'piece', 'amount' => round($piece, 6)],
                ['key' => 'setup', 'amount' => $setup],
                ['key' => 'tierDiscountPercent', 'amount' => $discount],
                ['key' => 'percent', 'amount' => $percent],
                ['key' => 'profileSurchargePercent', 'amount' => $surcharge],
                ['key' => 'subtotal', 'amount' => round($subtotal, 6)],
                ['key' => 'minimum', 'amount' => $minimum],
                ['key' => 'customerGroupPercent', 'amount' => $customerGroupPercent],
            ]),
        ];
    }

    public static function roundPrice(float $amount, ?float $step): float
    {
        if ($step !== null && $step > 0) {
            return round(round($amount / $step) * $step, 2);
        }

        return round($amount + 1e-9, 2);
    }

    /** @return array{0: float, 1: float} Breite/Höhe in mm inkl. Formatzugaben der gewählten Werte */
    public function dimensions(NormalizedConfiguration $config, Catalog $catalog): array
    {
        $width = (float) $config->width;
        $height = (float) $config->height;
        $values = array_values($config->options);
        if ($config->deliveryValueId !== null) {
            $values[] = $config->deliveryValueId;
        }
        $widthAdd = 0.0;
        $heightAdd = 0.0;
        foreach ($values as $valueId) {
            $value = $catalog->value($valueId) ?? [];
            $width *= (float) ($value['widthFactor'] ?? 1) ?: 1.0;
            $height *= (float) ($value['heightFactor'] ?? 1) ?: 1.0;
            $widthAdd += (float) ($value['widthAdd'] ?? 0);
            $heightAdd += (float) ($value['heightAdd'] ?? 0);
        }

        return [$width + $widthAdd, $height + $heightAdd];
    }

    /**
     * Flächenregeln: Mindestfläche und Flächenrundung des Materials überschreiben die der Kalkulation
     * (Folie A ab 1 m², Folie B ab 0,5 m²); Bezug (areaScope) kommt aus der Kalkulation.
     *
     * @param array<string, mixed>|null $calculation
     * @param array<string, mixed>|null $material
     *
     * @return array{minArea: mixed, areaRounding: mixed, areaScope: string}
     */
    public function areaRules(?array $calculation, ?array $material): array
    {
        return [
            'minArea' => $material['minArea'] ?? $calculation['minArea'] ?? null,
            'areaRounding' => $material['areaRounding'] ?? $calculation['areaRounding'] ?? null,
            'areaScope' => (string) ($calculation['areaScope'] ?? 'total'),
        ];
    }

    /**
     * Abgerechnete Fläche der Position in m².
     * areaScope `total` (Standard): Gesamtfläche L × B × Stück, aufgerundet auf `areaRounding`, mindestens `minArea`
     * (Folie: bis 1 m² gleicher Preis, darüber in Schritten). areaScope `piece`: dasselbe je Stück, dann × Stück.
     *
     * @param array<string, mixed>|null $rules Flächenregeln aus areaRules()
     */
    public function billedArea(?array $rules, float $width, float $height, int $quantity): float
    {
        if (($rules['areaScope'] ?? 'total') === 'piece') {
            return $this->pieceArea($rules, $width, $height) * $quantity;
        }

        return self::roundArea($width * $height / 1_000_000 * $quantity, $rules);
    }

    /**
     * Gemeinsame Flächenrundung für beide Bezüge (Position und Stück): auf `areaRounding` aufrunden, mindestens `minArea`.
     * Vor dem Aufrunden wird auf 9 Stellen gerundet, damit Fließkomma-Reste an der Schwelle (1,3 / 0,1 = 12,999…)
     * nicht einen Schritt zu viel ergeben – eine Fläche genau auf der Schwelle bleibt auf der Schwelle.
     *
     * @param array<string, mixed>|null $rules Flächenregeln aus areaRules()
     */
    public static function roundArea(float $area, ?array $rules): float
    {
        $rounding = (float) ($rules['areaRounding'] ?? 0);
        if ($rounding > 0) {
            $area = round(ceil(round($area / $rounding, 9)) * $rounding, 9);
        }

        return max((float) ($rules['minArea'] ?? 0), $area);
    }

    /**
     * Abgerechnete Fläche je Stück bei areaScope `piece`.
     *
     * @param array<string, mixed>|null $rules Flächenregeln aus areaRules()
     */
    public function pieceArea(?array $rules, float $width, float $height): float
    {
        return self::roundArea($width * $height / 1_000_000, $rules);
    }

    /**
     * Gewählte Werte, die Preise tragen: Optionen + Lieferzeit (ggf. überschrieben, Matrix) + Festformat.
     * Dieselbe Quelle für price() und bestPrice().
     *
     * @return list<string>
     */
    private function selectedValueIds(NormalizedConfiguration $config, ?string $deliveryValueId = null): array
    {
        $valueIds = array_values($config->options);
        $deliveryValueId ??= $config->deliveryValueId;
        if ($deliveryValueId !== null) {
            $valueIds[] = $deliveryValueId;
        }
        if ($config->formatValueId !== null) {
            $valueIds[] = $config->formatValueId;
        }

        return $valueIds;
    }

    /**
     * Aktive Preistabellen der Kalkulation (inaktive zählen weder für den Preis noch für den Bestpreis).
     *
     * @param array<string, mixed> $calculation
     *
     * @return list<array<string, mixed>>
     */
    private static function activePriceTables(array $calculation): array
    {
        return array_values(array_filter($calculation['priceTables'] ?? [], static fn (array $t) => ($t['active'] ?? true) !== false));
    }

    /**
     * Spezifischste passende Preistabelle: alle Schlüsselwerte gewählt; mehr Schlüssel und Material vor ohne.
     *
     * @param list<array<string, mixed>> $tables
     * @param list<string>               $valueIds
     *
     * @return array<string, mixed>|null
     */
    private function matchPriceTable(array $tables, string $materialId, array $valueIds): ?array
    {
        $best = null;
        $bestScore = -1;
        foreach ($tables as $table) {
            if (($table['materialId'] ?? null) !== null && $table['materialId'] !== $materialId) {
                continue;
            }
            $keys = $table['optionValueIds'] ?? [];
            if (array_diff($keys, $valueIds) !== []) {
                continue;
            }
            $score = \count($keys) * 2 + (($table['materialId'] ?? null) !== null ? 1 : 0);
            if ($score > $bestScore || ($score === $bestScore && ($table['position'] ?? 0) < ($best['position'] ?? 0))) {
                $best = $table;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * Zeile für die Menge: letzte Zeile mit quantity ≤ Menge, unter der ersten Zeile die erste.
     *
     * @param array<string, mixed> $table
     *
     * @return array{quantity: int, price: float}|null
     */
    private function priceTableRow(array $table, float $quantity, string $side): ?array
    {
        $rows = array_values(array_filter($table['rows'] ?? [], static fn (array $r) => is_numeric($r[$side === 'sale' ? 'priceSale' : 'pricePurchase'] ?? null)));
        if ($rows === []) {
            return null;
        }
        usort($rows, static fn (array $a, array $b) => $a['quantity'] <=> $b['quantity']);
        $found = $rows[0];
        foreach ($rows as $row) {
            if ($row['quantity'] <= $quantity + 1e-9) {
                $found = $row;
            }
        }

        return ['quantity' => (int) $found['quantity'], 'price' => (float) $found[$side === 'sale' ? 'priceSale' : 'pricePurchase']];
    }

    // ------------------------------------------------------------------------------------------------
    // Bestpreis und Matrix
    // ------------------------------------------------------------------------------------------------

    /**
     * Bestpreisregel (PRODUKTBEISPIELE Lücke 6): kostet eine größere Menge an einer Staffelschwelle weniger?
     *
     * @param array<string, mixed> $sale
     *
     * @return array{perMotif: int, quantity: int, total: float}|null
     */
    private function bestPrice(NormalizedConfiguration $config, Catalog $catalog, CalculationContext $context, array $sale): ?array
    {
        $calculation = $catalog->calculation($config->calculationId) ?? [];
        if (($calculation['bestPriceMode'] ?? 'hint') === 'none') {
            return null;
        }
        $motifMode = (string) ($calculation['motifMode'] ?? 'multiply');
        $perMotifScope = ($calculation['tierScope'] ?? 'total') === 'per_motif';
        $pieceArea = (float) $sale['pieceArea'];
        $material = $catalog->material($config->materialId) ?? [];

        $thresholds = $this->bestPriceThresholds($config, $catalog);
        if ($thresholds === []) {
            return null;
        }

        $current = (float) $sale['total'];
        $limits = $this->limits($catalog, $calculation, $material);
        $best = null;
        $candidates = [];
        foreach ($thresholds as [$basis, $from]) {
            $measureQuantity = $basis === 'total_area' ? (int) ceil($from / max($pieceArea, 1e-9) - 1e-9) : (int) ceil($from - 1e-9);
            // Messmenge → Stück je Motiv
            if ($motifMode === 'split') {
                $perMotif = $perMotifScope ? $measureQuantity * $config->motifs : $measureQuantity;
            } else {
                $perMotif = $perMotifScope ? $measureQuantity : (int) ceil($measureQuantity / $config->motifs);
            }
            if ($perMotif <= $config->perMotif) {
                continue;
            }
            $candidates[$perMotif] = true;
        }
        ksort($candidates);
        $messages = new Messages($context->language);
        foreach (array_keys($candidates) as $perMotif) {
            $quantity = $config->quantity($motifMode, $perMotif);
            if ($quantity > min($limits['maxQuantity'] ?? self::HARD_MAX_QUANTITY, self::HARD_MAX_QUANTITY)) {
                continue;
            }
            // die größere Menge muss mit der gewählten Lieferzeit lieferbar sein (Verfügbarkeit nach Menge/Fläche)
            if ($config->deliveryValueId !== null && $this->deliveryReason($config, $catalog, $config->deliveryValueId, $perMotif, $messages) !== null) {
                continue;
            }
            $price = $this->price($config, $catalog, $context, 'sale', $perMotif);
            if ($price === null) {
                continue;
            }
            if ($price['total'] < $current - 0.004 && ($best === null || $price['total'] < $best['total'])) {
                $best = ['perMotif' => $perMotif, 'quantity' => $quantity, 'total' => $price['total']];
            }
        }

        return $best;
    }

    /**
     * Staffelschwellen für die Bestpreisregel – aus denselben Quellen wie price(): Material, Kalkulation, alle gewählten
     * Werte (Optionen, Lieferzeit, Festformat) und nur aktive Preistabellen. Nur Basis Menge oder Gesamtfläche.
     *
     * @return list<array{0: string, 1: float}> [basis, from]
     */
    public function bestPriceThresholds(NormalizedConfiguration $config, Catalog $catalog): array
    {
        $calculation = $catalog->calculation($config->calculationId) ?? [];
        $material = $catalog->material($config->materialId) ?? [];
        $thresholds = [];
        $collect = static function (?array $tier) use (&$thresholds): void {
            if ($tier === null || !\in_array($tier['basis'], ['quantity', 'total_area'], true)) {
                return;
            }
            foreach ($tier['steps'] as $step) {
                $thresholds[] = [$tier['basis'], $step['from']];
            }
        };
        $collect(Tiers::forSide($material['tiers'] ?? null, $material['tierBasis'] ?? $calculation['tierBasis'] ?? null, 'sale'));
        $collect(Tiers::forSide($calculation['tiers'] ?? null, $calculation['tierBasis'] ?? null, 'sale'));
        foreach ($this->selectedValueIds($config) as $valueId) {
            $assignment = $catalog->assignment((string) $config->calculationId, $valueId, (string) $config->materialId);
            $collect(Tiers::forSide($assignment['tiers'] ?? null, $calculation['tierBasis'] ?? null, 'sale'));
        }
        foreach (self::activePriceTables($calculation) as $table) {
            foreach ($table['rows'] ?? [] as $row) {
                $thresholds[] = ['quantity', (float) $row['quantity']];
            }
        }

        return $thresholds;
    }

    /**
     * Preismatrix Menge × Lieferzeit (geführte Darstellung). `quantity` = Stück je Motiv (bei `split` Gesamtmenge).
     *
     * @return list<array{quantity: int, delivery: string, total: float|null}>
     */
    private function matrix(NormalizedConfiguration $config, Catalog $catalog, CalculationContext $context, Messages $messages): array
    {
        $profile = $catalog->profile();
        $calculation = $catalog->calculation($config->calculationId) ?? [];
        $steps = $profile['quantitySteps'] ?? null;
        if (!\is_array($steps) || $steps === []) {
            $steps = $calculation['quantitySteps'] ?? [1, 2, 5, 10, 25, 50, 100];
        }
        $rows = [];
        foreach ($steps as $step) {
            $perMotif = max(1, (int) $step);
            foreach ($config->deliveryValueIds !== [] ? $config->deliveryValueIds : [null] as $valueId) {
                $total = null;
                if ($valueId === null || $this->deliveryReason($config, $catalog, $valueId, $perMotif, $messages) === null) {
                    $price = $this->price($config, $catalog, $context, 'sale', $perMotif, $valueId);
                    $total = $price['total'] ?? null;
                }
                $rows[] = [
                    'quantity' => $perMotif,
                    'delivery' => $valueId !== null ? (string) ($catalog->value($valueId)['technicalName'] ?? '') : '',
                    'total' => $total,
                ];
            }
        }

        return $rows;
    }
}
