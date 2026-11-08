<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Service\Calculation;

use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

/**
 * Lädt den Katalog eines Produkts aus der Datenbank (Sprache aus dem Context) in die Form, die die Engine
 * erwartet (siehe Catalog). Gleiches Format wie Demo\DemoCatalog::catalog() – so prüft der Vertragstest
 * dieselbe Engine, die im Shop rechnet.
 */
class CatalogLoader
{
    private const CALCULATION_FIELDS = [
        'id', 'technicalName', 'name', 'active', 'unit', 'formatMode', 'widthMin', 'widthMax', 'heightMin', 'heightMax',
        'allowRotation', 'minArea', 'areaRounding', 'areaScope', 'maxMotifs', 'maxQuantity', 'optionSetId', 'motifMode', 'tierScope',
        'bestPriceMode', 'priceComponents', 'minimumPricePurchase', 'minimumPriceSale', 'tierBasis', 'tiers',
        'priceRounding', 'setupPricePurchase', 'setupPriceSale', 'quantitySteps', 'supplierId',
    ];
    private const MATERIAL_FIELDS = [
        'id', 'technicalName', 'code', 'active', 'position', 'pricePurchase', 'priceSale', 'minimumPricePurchase',
        'minimumPriceSale', 'minArea', 'areaRounding', 'tierBasis', 'tiers', 'priceComponents', 'optionSetId', 'widthMax', 'heightMax',
        'name', 'description', 'badges', 'supplierId', 'weightPerSqm',
    ];
    private const GROUP_FIELDS = ['id', 'technicalName', 'name', 'helpText', 'type', 'scope', 'displayType', 'required', 'showPriceHint', 'position'];
    private const VALUE_FIELDS = [
        'id', 'optionGroupId', 'technicalName', 'name', 'description', 'badges', 'position', 'widthAdd', 'heightAdd',
        'widthFactor', 'heightFactor', 'deliveryDays', 'width', 'height', 'optionSetId', 'exclusions', 'availability',
    ];
    private const ASSIGNMENT_FIELDS = ['id', 'optionValueId', 'materialId', 'active', 'isDefault', 'position', 'priceComponents', 'tiers', 'conditions'];
    private const PROFILE_FIELDS = [
        'id', 'productId', 'type', 'active', 'layout', 'widthMin', 'widthMax', 'heightMin', 'heightMax', 'minQuantity',
        'maxQuantity', 'minimumPriceSale', 'surchargePercent', 'priceComponents', 'quantitySteps', 'steps', 'defaults', 'display',
    ];

    private const LOGISTICS_FIELDS = [
        'id', 'calculationId', 'materialId', 'optionValueId', 'costType', 'form', 'value', 'rampFrom', 'rampTo',
        'thresholdBasis', 'thresholdFrom', 'thresholdTo', 'minimumAmount', 'minimumScope', 'validFrom', 'active',
    ];

    public function __construct(private readonly DefinitionInstanceRegistry $registry)
    {
    }

    public function load(string $productId, Context $context): ?Catalog
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('productId', $productId))
            ->addFilter(new EqualsFilter('active', true))
            ->addAssociation('materials')
            ->addAssociation('options')
            ->setLimit(1);
        $profile = $this->registry->getRepository('ld_mensura_product_profile')->search($criteria, $context)->first();
        if (!$profile instanceof Entity) {
            return null;
        }

        $profileData = $this->fields($profile, self::PROFILE_FIELDS);
        $profileData['materials'] = [];
        foreach ($profile->get('materials') ?? [] as $row) {
            $profileData['materials'][] = $this->fields($row, ['id', 'calculationId', 'materialId', 'position', 'surchargePercent']);
        }
        $profileData['options'] = [];
        foreach ($profile->get('options') ?? [] as $row) {
            $profileData['options'][] = $this->fields($row, ['id', 'optionGroupId', 'materialId', 'mode', 'valueIds', 'defaultValueId', 'position']);
        }
        $calculationIds = array_values(array_unique(array_column($profileData['materials'], 'calculationId')));
        if ($calculationIds === []) {
            return null;
        }

        $criteria = (new Criteria($calculationIds))
            ->addAssociation('materials')
            ->addAssociation('optionValues')
            ->addAssociation('priceTables.rows');
        $calculations = [];
        $materialIds = array_filter(array_column($profileData['materials'], 'materialId'));
        foreach ($this->registry->getRepository('ld_mensura_calculation')->search($criteria, $context)->getEntities() as $calculation) {
            $data = $this->fields($calculation, self::CALCULATION_FIELDS);
            $data['materialIds'] = array_values($calculation->get('materials')?->getIds() ?? []);
            $materialIds = array_merge($materialIds, $data['materialIds']);
            $data['optionValues'] = [];
            foreach ($calculation->get('optionValues') ?? [] as $row) {
                $data['optionValues'][] = $this->fields($row, self::ASSIGNMENT_FIELDS);
            }
            $data['priceTables'] = [];
            foreach ($calculation->get('priceTables') ?? [] as $table) {
                $tableData = $this->fields($table, ['id', 'name', 'materialId', 'optionValueIds', 'priceType', 'active', 'position']);
                $tableData['optionValueIds'] ??= [];
                $tableData['rows'] = [];
                foreach ($table->get('rows') ?? [] as $row) {
                    $tableData['rows'][] = $this->fields($row, ['quantity', 'pricePurchase', 'priceSale']);
                }
                $data['priceTables'][] = $tableData;
            }
            $calculations[$data['id']] = $data;
        }

        $materials = [];
        if ($materialIds !== []) {
            $criteria = (new Criteria(array_values(array_unique($materialIds))))->addAssociation('media');
            foreach ($this->registry->getRepository('ld_mensura_material')->search($criteria, $context)->getEntities() as $material) {
                $data = $this->fields($material, self::MATERIAL_FIELDS);
                $data['mediaUrl'] = $this->mediaUrl($material->get('media'));
                $materials[$data['id']] = $data;
            }
        }

        $groups = [];
        foreach ($this->registry->getRepository('ld_mensura_option_group')->search(new Criteria(), $context)->getEntities() as $group) {
            $groups[$group->getUniqueIdentifier()] = $this->fields($group, self::GROUP_FIELDS);
        }
        $values = [];
        $criteria = (new Criteria())->addAssociation('media');
        foreach ($this->registry->getRepository('ld_mensura_option_value')->search($criteria, $context)->getEntities() as $value) {
            $data = $this->fields($value, self::VALUE_FIELDS);
            $data['mediaUrl'] = $this->mediaUrl($value->get('media'));
            $values[$data['id']] = $data;
        }
        $sets = [];
        $criteria = (new Criteria())->addAssociation('groups');
        foreach ($this->registry->getRepository('ld_mensura_option_set')->search($criteria, $context)->getEntities() as $set) {
            $data = $this->fields($set, ['id', 'technicalName', 'name']);
            $data['groups'] = [];
            foreach ($set->get('groups') ?? [] as $row) {
                $data['groups'][] = $this->fields($row, ['id', 'optionGroupId', 'position', 'required']);
            }
            $sets[$data['id']] = $data;
        }
        $rules = [];
        foreach ($this->registry->getRepository('ld_mensura_customer_group_rule')->search(new Criteria(), $context)->getEntities() as $rule) {
            $rules[] = $this->fields($rule, ['calculationId', 'customerGroupId', 'surchargePercent', 'priceRounding']);
        }

        // Logistikregeln (KONZEPT 3d) der beteiligten Kalkulationen, Materialien und aller Optionswerte; Auswahl
        // nach Gültigkeit und Vorrang trifft die Engine. Immer im Systembereich gelesen: Der Preis muss für jeden gleich
        // sein, auch wenn ein Admin-Benutzer ohne `ld_mensura.logistics` neu rechnet; nach außen geht nur der Gesamtpreis.
        $logisticsRules = [];
        $criteria = (new Criteria())->addFilter(new EqualsFilter('active', true));
        $found = $context->scope(Context::SYSTEM_SCOPE, fn (Context $system) => $this->registry->getRepository('ld_mensura_logistics_rule')->search($criteria, $system)->getEntities());
        foreach ($found as $rule) {
            $data = $this->fields($rule, self::LOGISTICS_FIELDS);
            $data['validFrom'] = $data['validFrom'] instanceof \DateTimeInterface ? $data['validFrom']->format('Y-m-d') : null;
            $relevant = ($data['optionValueId'] !== null && ($data['calculationId'] === null || isset($calculations[$data['calculationId']])))
                || ($data['materialId'] !== null && isset($materials[$data['materialId']]))
                || ($data['optionValueId'] === null && $data['materialId'] === null && isset($calculations[$data['calculationId']]));
            if ($relevant) {
                $logisticsRules[] = $data;
            }
        }

        // Steuer des Produkts (auch geerbt bei Varianten) für den Bruttopreis in der Store-API
        $product = $context->enableInheritance(fn (Context $inherited) => $this->registry->getRepository('product')->search(new Criteria([$productId]), $inherited)->first());
        $taxId = $product instanceof Entity && $product->has('taxId') ? $product->get('taxId') : null;

        return new Catalog([
            'product' => ['id' => $productId, 'taxId' => $taxId],
            'profile' => $profileData,
            'calculations' => $calculations,
            'materials' => $materials,
            'groups' => $groups,
            'values' => $values,
            'sets' => $sets,
            'customerGroupRules' => $rules,
            'logisticsRules' => $logisticsRules,
        ]);
    }

    /**
     * @param list<string> $fields
     *
     * @return array<string, mixed>
     */
    private function fields(Entity $entity, array $fields): array
    {
        $data = [];
        foreach ($fields as $field) {
            $data[$field] = $entity->has($field) ? $entity->get($field) : null;
        }
        if (\array_key_exists('name', $data) && $data['name'] === null && $entity->has('translated')) {
            $data['name'] = $entity->getTranslation('name');
        }

        return $data;
    }

    private function mediaUrl(mixed $media): ?string
    {
        return $media instanceof MediaEntity ? $media->getUrl() : null;
    }
}
