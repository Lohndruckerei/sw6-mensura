<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Service\Demo;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Legt das Demo-Produkt „Aluverbund (Mensura-Demo)“ samt Kalkulationen, Materialien, Optionen, Sets und Profil an
 * (Daten aus DemoCatalog, passend zu docs/contract/fixtures). Mehrfach ausführbar (upsert mit festen IDs).
 *
 *   bin/console mensura:demo:seed
 */
#[AsCommand(name: 'mensura:demo:seed', description: 'Mensura: Demo-Produkt und Stammdaten passend zu den Vertrags-Beispielfällen anlegen')]
class DemoSeedCommand extends Command
{
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createCLIContext();

        foreach (DemoCatalog::PRODUCTS as $productId => [$number, $nameDe, $nameEn, , $netPrice]) {
            $this->seedProduct($context, $productId, $number, $nameDe, $nameEn, $netPrice);
            $io->writeln('Produkt ' . $number . ' (' . $productId . ')');
        }

        // Optionssets zuerst ohne Gruppen (Materialien und Werte verweisen darauf)
        $sets = [];
        foreach (DemoCatalog::setDefinitions() as $name => [$label]) {
            $sets[] = ['id' => DemoCatalog::id('set.' . $name), 'technicalName' => $name, 'name' => $label];
        }
        $this->upsert('ld_mensura_option_set', $sets, $context);

        $german = DemoCatalog::catalog('de');
        $english = DemoCatalog::catalog('en');

        $groups = [];
        foreach ($german['groups'] as $id => $group) {
            $groupValues = [];
            foreach ($german['values'] as $valueId => $value) {
                if ($value['optionGroupId'] !== $id) {
                    continue;
                }
                $groupValues[] = [
                    'id' => $valueId,
                    'technicalName' => $value['technicalName'],
                    'position' => $value['position'],
                    'deliveryDays' => $value['deliveryDays'],
                    'availability' => $value['availability'],
                    'translations' => [
                        'de-DE' => ['name' => $value['name']],
                        'en-GB' => ['name' => $english['values'][$valueId]['name']],
                    ],
                ];
            }
            $groups[] = [
                'id' => $id,
                'technicalName' => $group['technicalName'],
                'type' => $group['type'],
                'scope' => $group['scope'],
                'displayType' => $group['displayType'],
                'required' => $group['required'],
                'position' => $group['position'],
                'translations' => [
                    'de-DE' => ['name' => $group['name'], 'helpText' => $group['helpText']],
                    'en-GB' => ['name' => $english['groups'][$id]['name'], 'helpText' => $english['groups'][$id]['helpText']],
                ],
                'values' => $groupValues,
            ];
        }
        $this->upsert('ld_mensura_option_group', $groups, $context);

        $setGroups = [];
        foreach ($german['sets'] as $set) {
            foreach ($set['groups'] as $row) {
                $setGroups[] = ['id' => $row['id'], 'optionSetId' => $set['id'], 'optionGroupId' => $row['optionGroupId'], 'position' => $row['position']];
            }
        }
        $this->upsert('ld_mensura_option_set_group', $setGroups, $context);

        $materials = [];
        foreach (DemoCatalog::materialDefinitions() as $material) {
            $translations = [
                'de-DE' => $material['translations']['de'],
                'en-GB' => $material['translations']['en'],
            ];
            unset($material['translations']);
            $materials[] = $material + ['translations' => $translations];
        }
        $this->upsert('ld_mensura_material', $materials, $context);

        $calculations = [];
        foreach ($german['calculations'] as $calculation) {
            $calculation['materials'] = array_map(static fn (string $id) => ['id' => $id], $calculation['materialIds']);
            $calculation['optionValues'] = array_map(static fn (array $row) => array_filter($row, static fn ($v) => $v !== null), $calculation['optionValues']);
            unset($calculation['materialIds'], $calculation['priceTables']);
            $calculations[] = array_filter($calculation, static fn ($v) => $v !== null);
        }
        $this->upsert('ld_mensura_calculation', $calculations, $context);
        $io->writeln(\count($calculations) . ' Kalkulationen, ' . \count($materials) . ' Materialien, ' . \count($groups) . ' Optionsgruppen');

        foreach (array_keys(DemoCatalog::PRODUCTS) as $productId) {
            $profile = DemoCatalog::profileDefinition($productId);
            $profile['productVersionId'] = Defaults::LIVE_VERSION;
            $profile = array_filter($profile, static fn ($v) => $v !== null);
            if (($profile['options'] ?? []) === []) {
                unset($profile['options']);
            }
            $this->upsert('ld_mensura_product_profile', [$profile], $context);
        }

        $this->seedAdvisor($context);

        $io->success('Mensura-Demo angelegt (Aluverbund ' . DemoCatalog::PRODUCT_ID . ', Folie ' . DemoCatalog::PRODUCT_FOLIE . '). Store-API: GET /store-api/ld-mensura/product/{id}/configurator');

        return self::SUCCESS;
    }

    private function seedProduct(Context $context, string $productId, string $number, string $nameDe, string $nameEn, float $netPrice): void
    {
        $taxId = $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM tax ORDER BY tax_rate = 19 DESC, position LIMIT 1');
        $salesChannels = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(id)) FROM sales_channel WHERE type_id = UNHEX(?)',
            [Defaults::SALES_CHANNEL_TYPE_STOREFRONT],
        );
        // in die Hauptkategorie jeder Storefront, damit das Produkt in der Navigation erscheint
        $categories = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(navigation_category_id)) FROM sales_channel WHERE type_id = UNHEX(?)',
            [Defaults::SALES_CHANNEL_TYPE_STOREFRONT],
        );
        $product = [
            'id' => $productId,
            'productNumber' => $number,
            'active' => true,
            'stock' => 999999,
            // Menge kommt aus der Konfiguration (Motive × Stück); Shopware-Standard (100) würde kürzen
            'maxPurchase' => 999999,
            'taxId' => $taxId,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => round($netPrice * 1.19, 2), 'net' => $netPrice, 'linked' => true]],
            'translations' => [
                'de-DE' => ['name' => $nameDe, 'description' => 'Demo-Produkt für den Mensura-Konfigurator (Preise je m², berechnet im Konfigurator).'],
                'en-GB' => ['name' => $nameEn, 'description' => 'Demo product for the Mensura configurator.'],
            ],
            'categories' => array_map(static fn (string $id) => ['id' => $id], $categories),
            'visibilities' => array_map(static fn (string $id) => [
                // Aluverbund behält die ID aus früheren Seeds (keine Doppelungen)
                'id' => $productId === DemoCatalog::PRODUCT_ID ? md5('ld-mensura-demo:visibility:' . $id) : md5('ld-mensura-demo:visibility:' . $productId . ':' . $id),
                'salesChannelId' => $id,
                'visibility' => 30,
            ], $salesChannels),
        ];
        $this->upsert('product', [$product], $context);
    }

    private function seedAdvisor(Context $context): void
    {
        $einsatz = DemoCatalog::id('feature.einsatz');
        $wasserfest = DemoCatalog::id('feature.wasserfest');
        $this->upsert('ld_mensura_feature', [
            [
                'id' => $einsatz, 'technicalName' => 'einsatz', 'type' => 'multi', 'featureGroup' => 'umgebung', 'choices' => ['innen', 'aussen'], 'position' => 1,
                'translations' => ['de-DE' => ['name' => 'Einsatz', 'helpText' => 'Wo das Material dauerhaft hält, z. B. außen = wetterfest.'], 'en-GB' => ['name' => 'Use', 'helpText' => 'Where the material lasts, e.g. outdoor = weatherproof.']],
                'values' => [
                    ['id' => DemoCatalog::id('feature.einsatz.alu'), 'materialId' => DemoCatalog::MATERIAL_ALU, 'value' => ['value' => ['innen', 'aussen']]],
                    ['id' => DemoCatalog::id('feature.einsatz.acryl'), 'materialId' => DemoCatalog::MATERIAL_ACRYL, 'value' => ['value' => ['innen']]],
                ],
            ],
            [
                'id' => $wasserfest, 'technicalName' => 'wasserfest', 'type' => 'bool', 'featureGroup' => 'umgebung', 'position' => 2,
                'translations' => ['de-DE' => ['name' => 'Wasserfest', 'helpText' => 'Verträgt Regen und Spritzwasser, z. B. Schild am Eingang.'], 'en-GB' => ['name' => 'Waterproof', 'helpText' => 'Withstands rain and splash water.']],
                'values' => [
                    ['id' => DemoCatalog::id('feature.wasserfest.alu'), 'materialId' => DemoCatalog::MATERIAL_ALU, 'value' => ['value' => true]],
                ],
            ],
        ], $context);
        $this->upsert('ld_mensura_use_case', [[
            'id' => DemoCatalog::id('use-case.schild-aussen'),
            'technicalName' => 'schild-aussen',
            'position' => 1,
            'translations' => ['de-DE' => ['name' => 'Schild außen', 'description' => 'Firmenschild, Hinweisschild, Bauschild'], 'en-GB' => ['name' => 'Outdoor sign', 'description' => 'Company sign, notice board']],
            'assignments' => [
                ['id' => DemoCatalog::id('use-case.schild-aussen.alu'), 'materialId' => DemoCatalog::MATERIAL_ALU, 'suitability' => 5, 'preset' => ['options' => ['veredelung' => 'ag-2']]],
                ['id' => DemoCatalog::id('use-case.schild-aussen.acryl'), 'materialId' => DemoCatalog::MATERIAL_ACRYL, 'suitability' => 1],
            ],
        ]], $context);
    }

    /** @param list<array<string, mixed>> $payload */
    private function upsert(string $entity, array $payload, Context $context): void
    {
        $this->registry->getRepository($entity)->upsert($payload, $context);
    }
}
