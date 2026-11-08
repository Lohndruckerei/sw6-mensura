<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Service\Calculation;

/**
 * Alles, was die Preis-Engine für EIN Produkt braucht, als einfache Arrays (ohne DAL).
 *
 * Gefüllt wird der Katalog entweder aus der Datenbank (`CatalogLoader`) oder direkt aus PHP-Arrays
 * (`Demo\DemoCatalog` für Tests und `mensura:demo:seed`). Schlüssel heißen wie die Entity-Felder (camelCase),
 * IDs sind 32-stellige Hex-Strings.
 *
 * Aufbau:
 *  product            {id, name}
 *  profile            Produktprofil inkl. materials[] {calculationId, materialId|null, position, surchargePercent}
 *                     und options[] {optionGroupId, materialId|null, mode, valueIds[], defaultValueId, position}
 *  calculations[id]   Kalkulation inkl. materialIds[], optionValues[] (Zuordnung Kalkulation × Wert, optional je Material)
 *                     und priceTables[] {materialId|null, optionValueIds[], priceType, active, position, rows[] {quantity, pricePurchase, priceSale}}
 *  materials[id]      Material (name, description, badges, mediaUrl … Preise EK/VK, Staffeln, Preisanteile, optionSetId)
 *  groups[id]         Optionsgruppe (technicalName, name, helpText, type, scope, displayType, required, position)
 *  values[id]         Optionswert (optionGroupId, technicalName, name, … deliveryDays, width/height, optionSetId,
 *                     exclusions[], availability{})
 *  sets[id]           Optionsset {technicalName, name, groups[] {optionGroupId, position, required}}
 *  customerGroupRules[] {calculationId|null, customerGroupId, surchargePercent, priceRounding}
 *  logisticsRules[]   Logistikanteil (KONZEPT 3d) {id, calculationId|null, materialId|null, optionValueId|null, costType,
 *                     form, value, rampFrom, rampTo, thresholdBasis, thresholdFrom, thresholdTo, minimumAmount,
 *                     minimumScope, validFrom (YYYY-MM-DD|null), active}
 */
final class Catalog
{
    /** @var array<string, array<string, mixed>> */
    private array $groupsByName = [];
    /** @var array<string, array<string, string>> groupId → technicalName → valueId */
    private array $valueIndex = [];
    /** @var array<string, array<string, list<array<string, mixed>>>> calcId → valueId → Zuordnungen */
    private array $assignmentIndex = [];

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(private readonly array $data)
    {
        foreach ($this->data['groups'] ?? [] as $group) {
            $this->groupsByName[$group['technicalName']] = $group;
        }
        foreach ($this->data['values'] ?? [] as $id => $value) {
            $this->valueIndex[$value['optionGroupId']][$value['technicalName']] = $id;
        }
        foreach ($this->data['calculations'] ?? [] as $calcId => $calculation) {
            foreach ($calculation['optionValues'] ?? [] as $row) {
                if (($row['active'] ?? true) === false) {
                    continue;
                }
                $this->assignmentIndex[$calcId][$row['optionValueId']][] = $row;
            }
        }
    }

    public function productId(): string
    {
        return (string) ($this->data['product']['id'] ?? $this->data['profile']['productId']);
    }

    /** Steuer des Shopware-Produkts (für den Bruttopreis), null wenn unbekannt. */
    public function productTaxId(): ?string
    {
        return $this->data['product']['taxId'] ?? null;
    }

    /** @return array<string, mixed> */
    public function profile(): array
    {
        return $this->data['profile'];
    }

    /** @return array<string, mixed>|null */
    public function calculation(?string $id): ?array
    {
        return $id === null ? null : ($this->data['calculations'][$id] ?? null);
    }

    /** @return array<string, mixed>|null */
    public function material(?string $id): ?array
    {
        return $id === null ? null : ($this->data['materials'][$id] ?? null);
    }

    /** @return array<string, mixed>|null */
    public function group(?string $id): ?array
    {
        return $id === null ? null : ($this->data['groups'][$id] ?? null);
    }

    /** @return array<string, mixed>|null */
    public function groupByName(string $technicalName): ?array
    {
        return $this->groupsByName[$technicalName] ?? null;
    }

    /** @return array<string, array<string, mixed>> */
    public function groups(): array
    {
        $groups = $this->data['groups'] ?? [];
        uasort($groups, static fn (array $a, array $b) => [$a['position'] ?? 0, $a['technicalName']] <=> [$b['position'] ?? 0, $b['technicalName']]);

        return $groups;
    }

    /** @return array<string, mixed>|null */
    public function value(?string $id): ?array
    {
        return $id === null ? null : ($this->data['values'][$id] ?? null);
    }

    public function valueIdByName(string $groupId, string $technicalName): ?string
    {
        return $this->valueIndex[$groupId][$technicalName] ?? null;
    }

    /**
     * Werte einer Gruppe in Reihenfolge.
     *
     * @return list<string>
     */
    public function valueIdsOfGroup(string $groupId): array
    {
        $ids = array_values($this->valueIndex[$groupId] ?? []);
        usort($ids, fn (string $a, string $b) => [$this->data['values'][$a]['position'] ?? 0, $this->data['values'][$a]['technicalName']]
            <=> [$this->data['values'][$b]['position'] ?? 0, $this->data['values'][$b]['technicalName']]);

        return $ids;
    }

    /** @return array<string, mixed>|null */
    public function optionSet(?string $id): ?array
    {
        return $id === null ? null : ($this->data['sets'][$id] ?? null);
    }

    /**
     * Gruppen-IDs eines Sets in Reihenfolge.
     *
     * @return list<string>
     */
    public function setGroupIds(?string $setId): array
    {
        $set = $this->optionSet($setId);
        if ($set === null) {
            return [];
        }
        $rows = $set['groups'] ?? [];
        usort($rows, static fn (array $a, array $b) => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));

        return array_values(array_map(static fn (array $r) => $r['optionGroupId'], $rows));
    }

    /**
     * Erlaubte Materialien des Produktprofils in Reihenfolge, „alle Materialien der Kalkulation“ aufgelöst.
     *
     * @return list<array{calculationId: string, materialId: string, surchargePercent: float}>
     */
    public function profileMaterials(): array
    {
        $rows = $this->data['profile']['materials'] ?? [];
        usort($rows, static fn (array $a, array $b) => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));
        $result = [];
        $seen = [];
        foreach ($rows as $row) {
            $calculation = $this->calculation($row['calculationId']);
            if ($calculation === null || ($calculation['active'] ?? true) === false) {
                continue;
            }
            $materialIds = $row['materialId'] !== null ? [$row['materialId']] : $this->sortedMaterialIds($calculation['materialIds'] ?? []);
            foreach ($materialIds as $materialId) {
                $material = $this->material($materialId);
                if ($material === null || ($material['active'] ?? true) === false || isset($seen[$materialId])) {
                    continue;
                }
                $seen[$materialId] = true;
                $result[] = [
                    'calculationId' => $row['calculationId'],
                    'materialId' => $materialId,
                    'surchargePercent' => (float) ($row['surchargePercent'] ?? 0),
                ];
            }
        }

        return $result;
    }

    /**
     * Zuordnung Kalkulation × Optionswert für ein Material. Zeile mit Material gewinnt vor der allgemeinen.
     * Gibt es für den Wert nur Zeilen mit Material, ist er nur bei diesen Materialien wählbar.
     *
     * @return array<string, mixed>|null
     */
    public function assignment(string $calculationId, string $valueId, string $materialId): ?array
    {
        $rows = $this->assignmentIndex[$calculationId][$valueId] ?? [];
        $general = null;
        foreach ($rows as $row) {
            if (($row['materialId'] ?? null) === $materialId) {
                return $row;
            }
            if (($row['materialId'] ?? null) === null) {
                $general = $row;
            }
        }

        return $general;
    }

    /**
     * Regel des Produktprofils für eine Gruppe. Zeile mit Material gewinnt vor der allgemeinen.
     *
     * @return array<string, mixed>|null
     */
    public function profileRule(string $groupId, ?string $materialId): ?array
    {
        $general = null;
        foreach ($this->data['profile']['options'] ?? [] as $row) {
            if ($row['optionGroupId'] !== $groupId) {
                continue;
            }
            if ($materialId !== null && ($row['materialId'] ?? null) === $materialId) {
                return $row;
            }
            if (($row['materialId'] ?? null) === null) {
                $general = $row;
            }
        }

        return $general;
    }

    /**
     * Logistikregeln (KONZEPT 3d), alle Ziele und Gültigkeiten; die Auswahl trifft `Logistics::effectiveRules()`.
     * Nur intern: nie in Konfigurator-Daten oder Store-API.
     *
     * @return list<array<string, mixed>>
     */
    public function logisticsRules(): array
    {
        return $this->data['logisticsRules'] ?? [];
    }

    /**
     * Kundengruppen-Aufschlag: Regel der Kalkulation vor globaler Regel. Ohne Regel 0 % (Basis-VK = Wiederverkäufer).
     *
     * @return array{surchargePercent: float, priceRounding: float|null}
     */
    public function customerGroupRule(string $calculationId, ?string $customerGroupId): array
    {
        $global = null;
        foreach ($this->data['customerGroupRules'] ?? [] as $rule) {
            if ($customerGroupId === null || $rule['customerGroupId'] !== $customerGroupId) {
                continue;
            }
            if (($rule['calculationId'] ?? null) === $calculationId) {
                return ['surchargePercent' => (float) $rule['surchargePercent'], 'priceRounding' => isset($rule['priceRounding']) ? (float) $rule['priceRounding'] : null];
            }
            if (($rule['calculationId'] ?? null) === null) {
                $global = $rule;
            }
        }

        return [
            'surchargePercent' => (float) ($global['surchargePercent'] ?? 0),
            'priceRounding' => isset($global['priceRounding']) ? (float) $global['priceRounding'] : null,
        ];
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function sortedMaterialIds(array $ids): array
    {
        usort($ids, fn (string $a, string $b) => [$this->data['materials'][$a]['position'] ?? 0, $this->data['materials'][$a]['technicalName'] ?? $a]
            <=> [$this->data['materials'][$b]['position'] ?? 0, $this->data['materials'][$b]['technicalName'] ?? $b]);

        return $ids;
    }
}
