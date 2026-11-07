<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Service\Calculation;

/**
 * Geprüfte Konfiguration einer Position (Ergebnis von PriceEngine::normalize), intern mit IDs.
 * `toArray()` liefert die Vertragsform (docs/contract/schema/configuration.schema.json).
 */
final class NormalizedConfiguration
{
    public string $productId = '';
    public ?string $materialId = null;
    public ?string $calculationId = null;
    public float $profileMaterialSurcharge = 0.0;

    public int $width = 0;
    public int $height = 0;
    public ?string $formatValueId = null;
    public bool $rotated = false;

    public int $motifs = 1;
    public int $perMotif = 1;

    /** @var array<string, string> groupId → valueId (sichtbare Gruppen in Reihenfolge, danach ausgeblendete) */
    public array $options = [];
    /** @var list<string> sichtbare Gruppen-IDs in Reihenfolge */
    public array $activeGroupIds = [];
    /** @var list<string> ausgeblendete Gruppen (Profil `hidden`): Wert wird berechnet, aber nicht angezeigt */
    public array $hiddenGroupIds = [];

    public ?string $deliveryGroupId = null;
    public ?string $deliveryValueId = null;
    /** @var list<string> erlaubte Lieferzeit-Werte in Reihenfolge */
    public array $deliveryValueIds = [];

    public ?string $reference = null;
    /** @var list<array<string, mixed>>|null */
    public ?array $artwork = null;

    /** @var list<array<string, mixed>> */
    public array $errors = [];
    /** @var list<array<string, mixed>> */
    public array $hints = [];

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /** Gesamtmenge: Motive × Stück je Motiv (multiply) bzw. Gesamtmenge (split). */
    public function quantity(string $motifMode, ?int $perMotif = null): int
    {
        $perMotif ??= $this->perMotif;

        return $motifMode === 'split' ? $perMotif : $this->motifs * $perMotif;
    }

    /** @return array<string, mixed> */
    public function toArray(Catalog $catalog): array
    {
        $options = [];
        foreach ($this->options as $groupId => $valueId) {
            $group = $catalog->group($groupId);
            $value = $catalog->value($valueId);
            if ($group !== null && $value !== null) {
                $options[$group['technicalName']] = $value['technicalName'];
            }
        }

        $format = ['width' => $this->width, 'height' => $this->height];
        if ($this->formatValueId !== null) {
            $format['formatValueId'] = $this->formatValueId;
        }

        $result = [
            'schema' => 1,
            'productId' => $this->productId,
            'materialId' => $this->materialId,
            'format' => $format,
            'motifs' => $this->motifs,
            'perMotif' => $this->perMotif,
            'options' => (object) $options,
        ];
        if ($this->deliveryValueId !== null) {
            $result['deliveryValue'] = $catalog->value($this->deliveryValueId)['technicalName'] ?? null;
        }
        if ($this->reference !== null) {
            $result['reference'] = $this->reference;
        }
        if ($this->artwork !== null) {
            $result['artwork'] = $this->artwork;
        }

        return $result;
    }
}
