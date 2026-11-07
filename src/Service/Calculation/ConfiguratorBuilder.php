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
                'fromPricePerSqm' => ($calculation['unit'] ?? 'area') === 'area' && isset($material['priceSale'])
                    ? round((float) $material['priceSale'] * $surcharge, 2) : null,
                'limits' => $limits,
            ];
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
            $groups[] = [
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
        ];
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
