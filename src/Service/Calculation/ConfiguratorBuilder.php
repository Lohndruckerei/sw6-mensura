<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Service\Calculation;

/**
 * Baut die Antwort „Konfigurator-Daten eines Produkts“ (docs/contract/schema/configurator.schema.json)
 * aus dem Katalog – ohne Einkaufspreise. `priceHint` ist nur Anzeige; rechnen tut ausschließlich die Engine.
 */
final class ConfiguratorBuilder
{
    public function __construct(private readonly PriceEngine $engine = new PriceEngine())
    {
    }

    /** @return array<string, mixed> */
    public function build(Catalog $catalog, CalculationContext $context): array
    {
        $messages = new Messages($context->language);
        $profile = $catalog->profile();
        $entries = $catalog->profileMaterials();

        // Je Material: erreichbare Sets und Gruppen (Start-Set → Material-Set → Folge-Sets aller wählbaren Werte)
        $reachable = [];
        $allSets = [];
        foreach ($entries as $entry) {
            [$sets, $groups] = $this->reachable($catalog, $entry['calculationId'], $entry['materialId']);
            $reachable[$entry['materialId']] = $groups;
            foreach ($sets as $setId) {
                $allSets[$setId] = true;
            }
        }

        $materials = [];
        $startSet = null;
        foreach ($entries as $index => $entry) {
            $material = $catalog->material($entry['materialId']) ?? [];
            $calculation = $catalog->calculation($entry['calculationId']) ?? [];
            $limits = $this->engine->limits($catalog, $calculation, $material);
            $surcharge = 1 + $catalog->customerGroupRule($entry['calculationId'], $context->customerGroupId)['surchargePercent'] / 100;
            if ($index === 0 && isset($calculation['optionSetId'])) {
                $startSet = $catalog->optionSet($calculation['optionSetId'])['technicalName'] ?? null;
            }
            $setId = $material['optionSetId'] ?? $calculation['optionSetId'] ?? null;
            $materialDefaults = $this->cheapestDefaults($catalog, $entry, $reachable[$entry['materialId']] ?? []);
            $materials[] = [
                'id' => $entry['materialId'],
                'technicalName' => (string) $material['technicalName'],
                'name' => (string) ($material['name'] ?? $material['technicalName']),
                'description' => $material['description'] ?? null,
                'image' => $material['mediaUrl'] ?? null,
                'badges' => array_values($material['badges'] ?? []),
                'calculation' => [
                    'technicalName' => (string) $calculation['technicalName'],
                    'name' => (string) $calculation['name'],
                    'motifMode' => (string) ($calculation['motifMode'] ?? 'multiply'),
                ],
                'optionSet' => (string) ($catalog->optionSet($setId)['technicalName'] ?? ''),
                'fromPricePerSqm' => $this->fromPricePerSqm($catalog, $calculation, $material, $entry, $surcharge),
                'limits' => $limits,
            ];
            if ($materialDefaults !== []) {
                $materials[array_key_last($materials)]['defaults'] = $materialDefaults;
            }
        }

        $groups = [];
        foreach ($catalog->groups() as $groupId => $group) {
            $type = (string) ($group['type'] ?? 'standard');
            // Materialien, bei denen die Gruppe vorkommt
            $withGroup = [];
            foreach ($entries as $entry) {
                $rule = $catalog->profileRule($groupId, $entry['materialId']);
                $allowed = $this->engine->allowedValues($catalog, $entry['calculationId'], $entry['materialId'], $groupId, $rule);
                $present = $type === 'standard' ? isset($reachable[$entry['materialId']][$groupId]) : $allowed !== [];
                if ($present && $allowed !== []) {
                    $withGroup[$entry['materialId']] = ['entry' => $entry, 'allowed' => $allowed];
                }
            }
            if ($withGroup === []) {
                continue;
            }

            $values = [];
            foreach ($catalog->valueIdsOfGroup($groupId) as $valueId) {
                $availableFor = [];
                $hintSource = null;
                foreach ($withGroup as $materialId => $info) {
                    if (\in_array($valueId, $info['allowed'], true)) {
                        $availableFor[] = (string) ($catalog->material($materialId)['technicalName'] ?? $materialId);
                        $hintSource ??= $info['entry'];
                    }
                }
                if ($hintSource === null) {
                    continue;
                }
                $value = $catalog->value($valueId) ?? [];
                $assignment = $catalog->assignment($hintSource['calculationId'], $valueId, $hintSource['materialId']);
                $surcharge = 1 + $catalog->customerGroupRule($hintSource['calculationId'], $context->customerGroupId)['surchargePercent'] / 100;
                $out = [
                    'technicalName' => (string) $value['technicalName'],
                    'name' => (string) ($value['name'] ?? $value['technicalName']),
                ];
                if (!empty($value['description'])) {
                    $out['description'] = $value['description'];
                }
                if (!empty($value['mediaUrl'])) {
                    $out['image'] = $value['mediaUrl'];
                }
                if (!empty($value['badges'])) {
                    $out['badges'] = array_values($value['badges']);
                }
                $out['priceHint'] = $this->priceHint($assignment['priceComponents'] ?? [], $surcharge, $messages);
                if ($type === 'format') {
                    $out['width'] = isset($value['width']) ? (int) $value['width'] : null;
                    $out['height'] = isset($value['height']) ? (int) $value['height'] : null;
                }
                if ($type === 'delivery') {
                    $out['deliveryDays'] = isset($value['deliveryDays']) ? (int) $value['deliveryDays'] : null;
                }
                if (!empty($value['optionSetId'])) {
                    $out['optionSet'] = $catalog->optionSet($value['optionSetId'])['technicalName'] ?? null;
                }
                if (\count($availableFor) < \count($withGroup)) {
                    $out['availableForMaterials'] = $availableFor;
                }
                $values[] = $out;
            }

            $generalRule = $catalog->profileRule($groupId, null);
            $groupOut = [
                'technicalName' => (string) $group['technicalName'],
                'name' => (string) ($group['name'] ?? $group['technicalName']),
                'helpText' => $group['helpText'] ?? null,
                'type' => $type,
                'scope' => (string) ($group['scope'] ?? 'position'),
                'displayType' => (string) ($group['displayType'] ?? 'select'),
                'required' => (bool) ($group['required'] ?? true),
                'mode' => (string) ($generalRule['mode'] ?? 'inherit'),
                'values' => $values,
            ];
            // Aufpreise dieser Gruppe ausblenden (Fokus auf den Gesamtpreis); fehlt = anzeigen
            if (($group['showPriceHint'] ?? true) === false) {
                $groupOut['showPriceHint'] = false;
            }
            // keine Vorauswahl (für das erste Material, bei dem die Gruppe vorkommt); fehlt = vorauswählen
            $first = reset($withGroup)['entry'];
            if (!$this->engine->preselects($catalog, $first['calculationId'], $groupId, $first['materialId'])) {
                $groupOut['preselect'] = false;
            }
            $groups[] = $groupOut;
        }

        $sets = [];
        foreach (array_keys($allSets) as $setId) {
            $set = $catalog->optionSet($setId);
            if ($set === null) {
                continue;
            }
            $sets[$set['technicalName']] = array_values(array_filter(array_map(
                static fn (string $groupId) => $catalog->group($groupId)['technicalName'] ?? null,
                $catalog->setGroupIds($setId),
            )));
        }

        // Vorbelegung: Profil-Vorgaben, durch die Engine geprüft und vervollständigt
        $defaults = \is_array($profile['defaults'] ?? null) ? $profile['defaults'] : [];
        $defaults['productId'] = $catalog->productId();
        $normalized = $this->engine->normalize($defaults, $catalog, $messages, $context);

        $layout = \in_array($profile['layout'] ?? null, ['compact', 'guided'], true) ? $profile['layout'] : $context->storefrontLayout;
        $steps = \is_array($profile['steps'] ?? null) && $profile['steps'] !== [] ? array_values($profile['steps']) : [
            ['key' => 'material', 'label' => $messages->text('step.material')],
            ['key' => 'format', 'label' => $messages->text('step.format')],
            ['key' => 'options', 'label' => $messages->text('step.options')],
            ['key' => 'quantity', 'label' => $messages->text('step.quantity')],
        ];
        $quantitySteps = $profile['quantitySteps'] ?? null;
        if (!\is_array($quantitySteps) || $quantitySteps === []) {
            $firstCalculation = $entries !== [] ? $catalog->calculation($entries[0]['calculationId']) : null;
            $quantitySteps = $firstCalculation['quantitySteps'] ?? [1, 2, 5, 10, 25, 50, 100];
        }

        return [
            'apiVersion' => 1,
            'productId' => $catalog->productId(),
            'layout' => $layout,
            'allowLayoutSwitch' => $context->allowLayoutSwitch,
            'steps' => $steps,
            'materials' => $materials,
            'groups' => $groups,
            'sets' => (object) $sets,
            'startSet' => $startSet,
            'defaults' => $normalized->toArray($catalog),
            'quantitySteps' => array_values(array_map('intval', $quantitySteps)),
            'orderCutoff' => $context->orderCutoff,
            'display' => $this->display($profile, $context),
            // Testbetrieb der Darstellungen (Plugin-Einstellung layoutTestShare, 0 = kein Test)
            'layoutTest' => ['share' => max(0, min(100, $context->layoutTestShare))],
        ];
    }

    /**
     * Anzeige-Schalter: Plugin-Einstellung, je Schalter vom Produktprofil überschreibbar.
     *
     * @param array<string, mixed> $profile
     *
     * @return array<string, bool>
     */
    private function display(array $profile, CalculationContext $context): array
    {
        $display = [];
        $override = \is_array($profile['display'] ?? null) ? $profile['display'] : [];
        foreach (CalculationContext::DISPLAY_DEFAULTS as $key => $default) {
            $value = $override[$key] ?? $context->display[$key] ?? $default;
            $display[$key] = (bool) $value;
        }

        return $display;
    }

    /**
     * @return array{0: list<string>, 1: array<string, true>} besuchte Sets, erreichbare Standard-Gruppen
     */
    private function reachable(Catalog $catalog, string $calculationId, string $materialId): array
    {
        $calculation = $catalog->calculation($calculationId) ?? [];
        $material = $catalog->material($materialId) ?? [];
        $queue = array_values(array_filter([$calculation['optionSetId'] ?? null, $material['optionSetId'] ?? null]));
        $visited = [];
        $groups = [];
        for ($i = 0; $i < \count($queue) && $i < 50; ++$i) {
            $setId = $queue[$i];
            if (isset($visited[$setId])) {
                continue;
            }
            $visited[$setId] = true;
            foreach ($catalog->setGroupIds($setId) as $groupId) {
                $group = $catalog->group($groupId);
                if ($group === null || ($group['type'] ?? 'standard') !== 'standard') {
                    continue;
                }
                $groups[$groupId] = true;
                $rule = $catalog->profileRule($groupId, $materialId);
                foreach ($this->engine->allowedValues($catalog, $calculationId, $materialId, $groupId, $rule) as $valueId) {
                    $follow = $catalog->value($valueId)['optionSetId'] ?? null;
                    if ($follow !== null) {
                        $queue[] = $follow;
                    }
                }
            }
        }

        return [array_keys($visited), $groups];
    }

    /**
     * Vorbelegung je Material (Vertrag materials[].defaults, Regel des Benutzers 08.11.2026: „immer den günstigsten
     * Fall vorauswählen“): je erreichbarer Gruppe mit Vorauswahl der günstigste erlaubte Wert. Abschläge (z. B. 0/0
     * unbedruckt) werden nie vorausgewählt; bei gleichem Aufpreis gewinnt der Standardwert der Kalkulation, dann die
     * Reihenfolge. Feste/ausgeblendete Gruppen: ihr Wert. Gruppen ohne Vorauswahl fehlen.
     * Aufpreis zum Vergleich: VK-Preisanteile des Werts für ein Stück mit 1 m² (Umfang 4 m) bei diesem Material.
     *
     * @param array<string, true> $groupIds
     *
     * @return array<string, string> Gruppe (technischer Name) → Wert (technischer Name)
     */
    private function cheapestDefaults(Catalog $catalog, array $entry, array $groupIds): array
    {
        $calculationId = $entry['calculationId'];
        $materialId = $entry['materialId'];
        $materialPrice = (float) ($catalog->material($materialId)['priceSale'] ?? 0);
        $defaults = [];
        foreach (array_keys($groupIds) as $groupId) {
            $group = $catalog->group($groupId);
            if ($group === null || !$this->engine->preselects($catalog, $calculationId, $groupId, $materialId)) {
                continue;
            }
            $rule = $catalog->profileRule($groupId, $materialId);
            $allowed = $this->engine->allowedValues($catalog, $calculationId, $materialId, $groupId, $rule);
            if ($allowed === []) {
                continue;
            }
            $best = null;
            $bestCost = null;
            foreach ($allowed as $valueId) {
                $assignment = $catalog->assignment($calculationId, $valueId, $materialId) ?? [];
                $cost = $this->referenceCost($assignment['priceComponents'] ?? [], $materialPrice);
                if ($cost < -1e-9) {
                    continue;
                }
                $better = $bestCost === null || $cost < $bestCost - 1e-9
                    || (abs($cost - $bestCost) <= 1e-9 && ($assignment['isDefault'] ?? false) && !(($catalog->assignment($calculationId, $best, $materialId) ?? [])['isDefault'] ?? false));
                if ($better) {
                    $best = $valueId;
                    $bestCost = $cost;
                }
            }
            if (\in_array($rule['mode'] ?? 'inherit', ['fixed', 'hidden'], true)) {
                $best = $allowed[0];
            }
            if ($best !== null) {
                $defaults[(string) $group['technicalName']] = (string) ($catalog->value($best)['technicalName'] ?? $best);
            }
        }

        return $defaults;
    }

    /** Aufpreis der VK-Preisanteile für ein Stück mit 1 m² Fläche und 4 m Umfang. */
    private function referenceCost(mixed $components, float $materialPrice): float
    {
        $cost = 0.0;
        foreach (\is_array($components) ? $components : [] as $component) {
            $amount = \is_array($component) && is_numeric($component['sale'] ?? null) ? (float) $component['sale'] : null;
            if ($amount === null) {
                continue;
            }
            $cost += match ($component['type'] ?? '') {
                'per_area', 'per_piece', 'per_motif', 'per_order' => $amount,
                'per_perimeter' => 4 * $amount,
                'percent' => $materialPrice * $amount / 100,
                'factor' => $materialPrice * ($amount - 1),
                'base_area' => $amount - $materialPrice,
                default => 0.0,
            };
        }

        return $cost;
    }

    /**
     * „ab“-Preis je m² für Material-Kacheln – so, wie die Engine den Satz je m² rechnet, ohne gewählte Optionen:
     * (Materialpreis + Preisanteile je m² aus Kalkulation, Material und Produktprofil)
     * × (1 + Aufschlag Produktprofil % + Aufschlag Profil-Material %) × (1 + Kundengruppen-Aufschlag %).
     * Nur bei Mengeneinheit Fläche; Staffeln und Mindestpreis bleiben außen vor (es ist ein „ab“-Preis).
     *
     * @param array<string, mixed>                       $calculation
     * @param array<string, mixed>                       $material
     * @param array{surchargePercent: float, ...}        $entry
     */
    private function fromPricePerSqm(Catalog $catalog, array $calculation, array $material, array $entry, float $customerGroupFactor): ?float
    {
        if (($calculation['unit'] ?? 'area') !== 'area' || !isset($material['priceSale'])) {
            return null;
        }
        $profile = $catalog->profile();
        $perArea = 0.0;
        foreach ([$calculation['priceComponents'] ?? null, $material['priceComponents'] ?? null, $profile['priceComponents'] ?? null] as $components) {
            foreach (\is_array($components) ? $components : [] as $component) {
                if (\is_array($component) && ($component['type'] ?? null) === 'per_area' && is_numeric($component['sale'] ?? null)) {
                    $perArea += (float) $component['sale'];
                }
            }
        }
        $profileFactor = 1 + ((float) ($profile['surchargePercent'] ?? 0) + (float) ($entry['surchargePercent'] ?? 0)) / 100;

        return round(((float) $material['priceSale'] + $perArea) * $profileFactor * $customerGroupFactor, 2);
    }

    /**
     * Anzeige-Text aus den VK-Preisanteilen, z. B. „+ 9,00 €/m²“, „− 8,00 €/m²“, „+ 30 %“.
     *
     * @param mixed $components
     */
    public function priceHint(mixed $components, float $surcharge, Messages $messages): string
    {
        $parts = [];
        foreach (\is_array($components) ? $components : [] as $component) {
            if (!\is_array($component) || !is_numeric($component['sale'] ?? null)) {
                continue;
            }
            $amount = (float) $component['sale'];
            $type = (string) ($component['type'] ?? '');
            if (abs($amount) < 1e-9 && $type !== 'base_area') {
                continue;
            }
            $sign = $amount < 0 ? '− ' : '+ ';
            $money = $messages->money(abs($amount) * $surcharge);
            $parts[] = match ($type) {
                'per_area' => $sign . $money . '/m²',
                'per_piece' => $sign . $money . '/' . $messages->text('unit.piece'),
                'per_motif' => $sign . $money . '/' . $messages->text('unit.motif'),
                'per_perimeter' => $sign . $money . '/' . $messages->text('unit.perimeter'),
                'per_order' => $sign . $money,
                'percent' => $sign . $messages->plain(abs($amount)) . ' %',
                'factor' => '× ' . $messages->plain($amount),
                'base_area' => $messages->money($amount * $surcharge) . '/m²',
                default => null,
            };
        }
        $parts = array_values(array_filter($parts));

        return $parts === [] ? $messages->text('hint.none') : implode(' ', $parts);
    }
}
