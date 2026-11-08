<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Service\Demo;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexingMessage;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Demo-Sortiment aus der Comdatek-Einkaufspreisliste 2026 (ComdatekCatalog): je Excel-Gruppe eine Kalkulation mit
 * Materialien und Optionen (EK = Excel, VK = 25 % Marge, Logistikpauschale), je Gruppe ein Shopware-Produkt mit
 * Profil plus „Plattendirektdruck“ über alle Plattenkalkulationen, Kategorie „Mensura-Demo“ und – wenn der
 * LieferLotse aktiv ist – der Lieferant Comdatek mit Produktzuordnungen (Bestellweg manuell im Portal).
 *
 * Wiederholbar: feste IDs, setzt Optionssets, Optionszuordnungen der Kalkulationen sowie Materialien und
 * Optionsregeln der Profile seiner Produkte auf den Stand im Code zurück. Basis-Demo (`mensura:demo:seed`) und
 * Vertrags-Fixtures bleiben unberührt; gemeinsame Optionsgruppen werden nur ergänzt, nie geändert.
 *
 *   bin/console mensura:demo:comdatek
 */
#[AsCommand(name: 'mensura:demo:comdatek', description: 'Mensura: Demo-Produkte aus der Comdatek-Einkaufspreisliste 2026 anlegen (wiederholbar)')]
class ComdatekDemoCommand extends Command
{
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly Connection $connection,
        private readonly ProductIndexer $productIndexer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createCLIContext();
        $hasSupplier = $this->registry->has('ld_supplier');

        $this->seedOptionLibrary($context);
        $this->seedOptionSets($context);
        $materialCount = $this->seedMaterials($context);
        $this->seedCalculations($context);
        $this->seedCategory($context);
        $rows = $this->seedProducts($context);
        $this->seedProfiles($context);
        // sofort indizieren (Listing-Preise, Kategorien, SEO-URLs) statt auf die Nachrichten-Warteschlange zu warten
        $productIds = array_map(static fn (string $key) => ComdatekCatalog::productId($key), array_keys(ComdatekCatalog::products()));
        $this->productIndexer->handle(new ProductIndexingMessage($productIds, null, $context));
        foreach ($productIds as $index => $productId) {
            $url = $this->connection->fetchOne(
                'SELECT seo_path_info FROM seo_url WHERE foreign_key = UNHEX(?) AND route_name = ? AND is_canonical = 1 AND is_deleted = 0 ORDER BY created_at LIMIT 1',
                [$productId, 'frontend.detail.page'],
            );
            $rows[$index][] = '/' . (\is_string($url) ? $url : 'detail/' . $productId);
        }

        if ($hasSupplier) {
            $this->seedSupplier($context);
            $io->writeln('LieferLotse: Lieferant „Comdatek GmbH“ (' . ComdatekCatalog::SUPPLIER_NUMBER . ') mit Produktzuordnungen');
        } else {
            $io->writeln('LieferLotse nicht aktiv – Lieferant übersprungen');
        }

        $io->table(['Produkt', 'Nummer', 'Materialien', 'URL'], $rows);
        $io->success(\count(ComdatekCatalog::calculations()) . ' Kalkulationen, ' . $materialCount . ' Materialien, ' . \count($rows) . ' Produkte (Kategorie „Mensura-Demo“).');

        return self::SUCCESS;
    }

    /** Gemeinsame Gruppen/Werte nur anlegen, wenn sie fehlen; eigene Werte (Comdatek) immer aktualisieren. */
    private function seedOptionLibrary(Context $context): void
    {
        $groups = ComdatekCatalog::groups();
        $existingGroups = $this->existingIds('ld_mensura_option_group', array_map(static fn (string $g) => ComdatekCatalog::groupId($g), array_keys($groups)), $context);

        $demoValues = DemoCatalog::valueDefinitions();
        $newValues = ComdatekCatalog::newValues();
        $neededDemoValues = [];
        foreach ($demoValues as $group => $values) {
            foreach (array_keys($values) as $value) {
                $neededDemoValues[ComdatekCatalog::valueId($group, $value)] = [$group, $value];
            }
        }
        $existingValues = $this->existingIds('ld_mensura_option_value', array_keys($neededDemoValues), $context);

        $payload = [];
        foreach ($groups as $name => [$position, $type, $displayType, $de, $en, $helpDe, $helpEn]) {
            $groupId = ComdatekCatalog::groupId($name);
            $values = [];
            foreach ($demoValues[$name] ?? [] as $value => [$valuePosition, $valueDe, $valueEn, $days, $availability]) {
                if (!\in_array(ComdatekCatalog::valueId($name, $value), $existingValues, true)) {
                    $values[] = [
                        'id' => ComdatekCatalog::valueId($name, $value), 'technicalName' => $value, 'position' => $valuePosition,
                        'deliveryDays' => $days, 'availability' => $availability,
                        'translations' => ['de-DE' => ['name' => $valueDe], 'en-GB' => ['name' => $valueEn]],
                    ];
                }
            }
            foreach ($newValues[$name] ?? [] as $value => [$valuePosition, $valueDe, $valueEn]) {
                $values[] = [
                    'id' => ComdatekCatalog::valueId($name, $value), 'technicalName' => $value, 'position' => $valuePosition,
                    'translations' => ['de-DE' => ['name' => $valueDe], 'en-GB' => ['name' => $valueEn]],
                ];
            }
            $group = ['id' => $groupId, 'values' => $values];
            if (!\in_array($groupId, $existingGroups, true)) {
                $group += [
                    'technicalName' => $name, 'type' => $type, 'scope' => 'position', 'displayType' => $displayType,
                    'required' => true, 'position' => $position,
                    'translations' => ['de-DE' => ['name' => $de, 'helpText' => $helpDe], 'en-GB' => ['name' => $en, 'helpText' => $helpEn]],
                ];
            }
            if (\count($group) > 2 || $values !== []) {
                $payload[] = $group;
            }
        }
        $this->upsert('ld_mensura_option_group', $payload, $context);
    }

    private function seedOptionSets(Context $context): void
    {
        $sets = [];
        $rowIds = [];
        foreach (ComdatekCatalog::calculations() as $key => $calculation) {
            $groups = [];
            foreach ($calculation['sets'] as $index => $group) {
                $rowId = ComdatekCatalog::id('set.' . $key . '.' . $group);
                $rowIds[] = $rowId;
                $groups[] = ['id' => $rowId, 'optionGroupId' => ComdatekCatalog::groupId($group), 'position' => $index + 1];
            }
            $sets[] = ['id' => ComdatekCatalog::setId($key), 'technicalName' => 'cd-' . $key, 'name' => $calculation['name'], 'groups' => $groups];
        }
        $this->deleteOthers('ld_mensura_option_set_group', 'optionSetId', array_column($sets, 'id'), $rowIds, $context);
        $this->upsert('ld_mensura_option_set', $sets, $context);
    }

    private function seedMaterials(Context $context): int
    {
        $materials = [];
        foreach (ComdatekCatalog::calculations() as $calculation) {
            $position = 0;
            foreach ($calculation['materials'] as $key => [$de, $en, $ek]) {
                $extra = $calculation['materials'][$key][3] ?? [];
                $minimum = $extra['minimum'] ?? null;
                $materials[] = [
                    'id' => ComdatekCatalog::materialId($key),
                    'technicalName' => 'cd-' . $key,
                    'code' => strtoupper($key),
                    'active' => true,
                    'position' => ++$position,
                    'pricePurchase' => $ek,
                    'priceSale' => ComdatekCatalog::vk($ek),
                    'minimumPricePurchase' => $minimum,
                    'minimumPriceSale' => $minimum !== null ? ComdatekCatalog::vk($minimum) : null,
                    'minArea' => $extra['minArea'] ?? null,
                    'tierBasis' => null,
                    'tiers' => isset($extra['tiers']) ? ComdatekCatalog::tiers($extra['tiers']) : null,
                    'sheetWidth' => $calculation['size'][0] <= 3000 ? $calculation['size'][0] : null,
                    'sheetHeight' => $calculation['size'][1] <= 3000 ? $calculation['size'][1] : null,
                    'supplierId' => ComdatekCatalog::SUPPLIER_ID,
                    'supplierCodes' => isset($extra['supplierCode']) ? [['supplierId' => ComdatekCatalog::SUPPLIER_ID, 'code' => $extra['supplierCode']]] : null,
                    'translations' => [
                        'de-DE' => ['name' => $de, 'description' => 'Comdatek-Einkaufspreisliste 2026 (Demo).'],
                        'en-GB' => ['name' => $en, 'description' => 'Comdatek purchase price list 2026 (demo).'],
                    ],
                ];
            }
        }
        $this->upsert('ld_mensura_material', $materials, $context);

        return \count($materials);
    }

    private function seedCalculations(Context $context): void
    {
        $calculations = [];
        $assignmentIds = [];
        foreach (ComdatekCatalog::calculations() as $key => $calculation) {
            $assignments = ComdatekCatalog::assignments($key);
            $assignmentIds = array_merge($assignmentIds, array_column($assignments, 'id'));
            [$width, $height] = $calculation['size'];
            $calculations[] = [
                'id' => ComdatekCatalog::calculationId($key),
                'technicalName' => 'cd-' . $key,
                'name' => $calculation['name'],
                'description' => 'Comdatek-Einkaufspreisliste 2026, VK = EK mit 25 % Marge, inkl. Logistikpauschale (Vectura-Konzept, Modell D).',
                'active' => true,
                'unit' => 'area',
                'formatMode' => 'free',
                'widthMin' => 50,
                'widthMax' => $width,
                'heightMin' => 50,
                'heightMax' => $height,
                'allowRotation' => true,
                'areaScope' => 'total',
                'optionSetId' => ComdatekCatalog::setId($key),
                'motifMode' => 'multiply',
                'tierScope' => 'total',
                'bestPriceMode' => 'hint',
                'tierBasis' => 'none',
                'tiers' => null,
                // Logistikpauschale: Wert kommt später aus Vectura (dort noch kein Code), bis dahin fest je Materialgruppe
                'priceComponents' => array_merge($calculation['components'] ?? [], [ComdatekCatalog::logistics($calculation['logistics'])]),
                'minimumPricePurchase' => $calculation['minimum'],
                'minimumPriceSale' => ComdatekCatalog::vk($calculation['minimum']),
                'quantitySteps' => $calculation['quantitySteps'] ?? [1, 2, 5, 10, 25, 50, 100],
                'saleRule' => 'margin',
                'saleRuleValue' => 25.0,
                'marginMin' => 20.0,
                'marginMax' => 30.0,
                'supplierId' => ComdatekCatalog::SUPPLIER_ID,
                'supplierCodes' => [['supplierId' => ComdatekCatalog::SUPPLIER_ID, 'code' => $calculation['supplierCode']]],
                'materials' => array_map(static fn (string $material) => ['id' => ComdatekCatalog::materialId($material)], array_keys($calculation['materials'])),
                'optionValues' => $assignments,
            ];
        }
        $this->deleteOthers('ld_mensura_calculation_option_value', 'calculationId', array_column($calculations, 'id'), $assignmentIds, $context);
        $this->upsert('ld_mensura_calculation', $calculations, $context);
    }

    private function seedCategory(Context $context): void
    {
        $root = $this->connection->fetchOne(
            'SELECT LOWER(HEX(navigation_category_id)) FROM sales_channel WHERE type_id = UNHEX(?) ORDER BY created_at LIMIT 1',
            [Defaults::SALES_CHANNEL_TYPE_STOREFRONT],
        );
        if (!\is_string($root)) {
            return;
        }
        $this->upsert('category', [[
            'id' => ComdatekCatalog::CATEGORY_ID,
            'parentId' => $root,
            'active' => true,
            'visible' => true,
            'type' => 'page',
            'productAssignmentType' => 'product',
            'displayNestedProducts' => true,
            'translations' => [
                'de-DE' => ['name' => 'Mensura-Demo', 'description' => 'Demo-Produkte aus der Comdatek-Einkaufspreisliste 2026, konfigurierbar mit Mensura.'],
                'en-GB' => ['name' => 'Mensura demo', 'description' => 'Demo products from the Comdatek price list 2026, configurable with Mensura.'],
            ],
        ]], $context);
    }

    /** @return list<array{0: string, 1: string, 2: string}> */
    private function seedProducts(Context $context): array
    {
        $taxId = $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM tax ORDER BY tax_rate = 19 DESC, position LIMIT 1');
        $salesChannels = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(id)) FROM sales_channel WHERE type_id = UNHEX(?)',
            [Defaults::SALES_CHANNEL_TYPE_STOREFRONT],
        );
        $calculations = ComdatekCatalog::calculations();
        $products = [];
        $rows = [];
        foreach (ComdatekCatalog::products() as $key => [$number, $nameDe, $nameEn, , $entries]) {
            $productId = ComdatekCatalog::productId($key);
            // Anzeigepreis: günstigster VK je m² der Materialien (der Preis entsteht im Konfigurator)
            $prices = [];
            $names = [];
            foreach ($entries as [$calculation, $material]) {
                foreach ($calculations[$calculation]['materials'] as $materialKey => [$de, , $ek]) {
                    if ($material === null || $material === $materialKey) {
                        $prices[] = ComdatekCatalog::vk($ek);
                        $names[] = $de;
                    }
                }
            }
            $net = min($prices);
            $products[] = [
                'id' => $productId,
                'productNumber' => $number,
                'active' => true,
                'stock' => 999999,
                'maxPurchase' => 999999,
                'taxId' => $taxId,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => round($net * 1.19, 2), 'net' => $net, 'linked' => true]],
                'translations' => [
                    'de-DE' => ['name' => $nameDe, 'description' => 'Demo-Produkt aus der Comdatek-Einkaufspreisliste 2026 – Preis je Konfiguration (Material, Format, Menge, Optionen).'],
                    'en-GB' => ['name' => $nameEn, 'description' => 'Demo product from the Comdatek price list 2026 – price per configuration.'],
                ],
                'categories' => [['id' => ComdatekCatalog::CATEGORY_ID]],
                'visibilities' => array_map(static fn (string $id) => [
                    'id' => ComdatekCatalog::id('visibility.' . $key . '.' . $id),
                    'salesChannelId' => $id,
                    'visibility' => 30,
                ], $salesChannels),
            ];
            $rows[] = [$nameDe, $number, (string) \count($names)];
        }
        $this->upsert('product', $products, $context);

        return $rows;
    }

    /** Profile: Materialien und Optionsregeln werden auf den Stand im Code zurückgesetzt. */
    private function seedProfiles(Context $context): void
    {
        $profiles = [];
        $profileIds = [];
        foreach (ComdatekCatalog::products() as $key => [, , , $layout, $entries, , [$width, $height]]) {
            $profileId = ComdatekCatalog::id('profile.' . $key);
            $profileIds[] = $profileId;
            $materials = [];
            foreach ($entries as $index => [$calculation, $material]) {
                $materials[] = [
                    'id' => ComdatekCatalog::id('profile.' . $key . '.' . $calculation . '.' . ($material ?? 'alle')),
                    'calculationId' => ComdatekCatalog::calculationId($calculation),
                    'materialId' => $material !== null ? ComdatekCatalog::materialId($material) : null,
                    'position' => $index + 1,
                ];
            }
            $profiles[] = [
                'id' => $profileId,
                'productId' => ComdatekCatalog::productId($key),
                'productVersionId' => Defaults::LIVE_VERSION,
                // nur Auswahl aus Kalkulationen, keine Sondervorgaben → allgemein (speziell wäre z. B. Duschrückwand)
                'type' => 'general',
                'active' => true,
                'layout' => $layout,
                'defaults' => ['format' => ['width' => $width, 'height' => $height], 'motifs' => 1, 'perMotif' => 1],
                'materials' => $materials,
            ];
        }
        foreach (['ld_mensura_product_profile_option', 'ld_mensura_product_profile_material'] as $entity) {
            $this->deleteOthers($entity, 'productProfileId', $profileIds, [], $context);
        }
        $this->upsert('ld_mensura_product_profile', $profiles, $context);
    }

    /** LieferLotse: Lieferant Comdatek, Bestellweg manuell im Portal (nichts wird gesendet), Produktzuordnungen. */
    private function seedSupplier(Context $context): void
    {
        $assignments = [];
        foreach (ComdatekCatalog::products() as $key => [, , , , , $supplierProduct]) {
            $assignments[] = [
                'id' => ComdatekCatalog::id('supplier-product.' . $key),
                'productId' => ComdatekCatalog::productId($key),
                'productVersionId' => Defaults::LIVE_VERSION,
                'supplierProductNumber' => $supplierProduct,
                'priority' => 1,
                'active' => true,
                'notes' => $supplierProduct === null ? 'Mehrere Comdatek-Produkte – Artikelnummer je Material (Mensura-Lieferantencodes).' : null,
            ];
        }
        $this->upsert('ld_supplier', [[
            'id' => ComdatekCatalog::SUPPLIER_ID,
            'supplierNumber' => ComdatekCatalog::SUPPLIER_NUMBER,
            'name' => 'Comdatek GmbH',
            'active' => true,
            'notes' => "Sitz: Hückelhoven. Druckerei-Lieferant für Platten, Banner und Textil (Mensura-Demo aus der Einkaufspreisliste 2026).\nBestellung manuell im Portal – keine automatische Übertragung.",
            'websites' => [
                ['id' => ComdatekCatalog::id('supplier.website'), 'type' => 'website', 'label' => 'Website', 'url' => 'https://www.comdatek.de'],
                ['id' => ComdatekCatalog::id('supplier.portal'), 'type' => 'b2b_portal', 'label' => 'Comdatek Connect', 'url' => 'https://connect.comdatek.de'],
            ],
            'interfaces' => [[
                'id' => ComdatekCatalog::id('supplier.interface.order'),
                'name' => 'Bestellung im Portal (manuell)',
                'purpose' => 'order_out',
                'transport' => 'portal_manual',
                'format' => 'json',
                'active' => true,
                'config' => ['url' => 'https://connect.comdatek.de'],
            ]],
            'productAssignments' => $assignments,
        ]], $context);
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function existingIds(string $entity, array $ids, Context $context): array
    {
        return array_values($this->registry->getRepository($entity)->searchIds(new Criteria($ids), $context)->getIds());
    }

    /**
     * Löscht Datensätze, die zu den Eltern gehören, aber nicht (mehr) im Code stehen.
     *
     * @param list<string> $parentIds
     * @param list<string> $keepIds
     */
    private function deleteOthers(string $entity, string $parentField, array $parentIds, array $keepIds, Context $context): void
    {
        $criteria = (new Criteria())->addFilter(new EqualsAnyFilter($parentField, $parentIds));
        if ($keepIds !== []) {
            $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsAnyFilter('id', $keepIds)]));
        }
        $repository = $this->registry->getRepository($entity);
        $ids = $repository->searchIds($criteria, $context)->getIds();
        if ($ids !== []) {
            $repository->delete(array_map(static fn ($id) => ['id' => $id], array_values($ids)), $context);
        }
    }

    /** @param list<array<string, mixed>> $payload */
    private function upsert(string $entity, array $payload, Context $context): void
    {
        if ($payload !== []) {
            $this->registry->getRepository($entity)->upsert($payload, $context);
        }
    }
}
