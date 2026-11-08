<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Service\Cart;

use Lohndruckerei\Mensura\Service\Calculation\Catalog;
use Lohndruckerei\Mensura\Service\Calculation\CatalogLoader;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Lohndruckerei\Mensura\Service\Calculation\ContextFactory;
use Lohndruckerei\Mensura\Service\Calculation\Messages;
use Lohndruckerei\Mensura\Service\Calculation\PriceEngine;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Preis konfigurierter Positionen (INTEGRATION.md Abschnitt 1): normale Produktposition mit Konfiguration im Payload
 * `ldMensura.configuration`. Nach dem ProductCartProcessor (Priorität 5000) rechnet die Engine bei jeder
 * Warenkorbberechnung neu – Preise des Frontends werden nie übernommen. Ungültige Positionen werden entfernt.
 *
 * Der Gesamtpreis der Position ist exakt der Engine-Preis (netto; bei Brutto-Anzeige mit Steuer aufgeschlagen);
 * der Stückpreis ist nur abgeleitet (Gesamt ÷ Menge, gerundet).
 */
class MensuraCartProcessor implements CartProcessorInterface
{
    public function __construct(
        private readonly CatalogLoader $loader,
        private readonly PriceEngine $engine,
        private readonly ContextFactory $contextFactory,
        private readonly QuantityPriceCalculator $calculator,
        private readonly Connection $connection,
    ) {
    }

    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $catalogs = [];
        $calculationContext = null;
        $productLineItems = $toCalculate->getLineItems()->filterFlatByType(LineItem::PRODUCT_LINE_ITEM_TYPE);

        // Produkte mit aktivem Mensura-Profil sind nur konfiguriert käuflich – Positionen ohne Konfiguration
        // (Kauf-Button im Listing, /checkout/line-item/add, Store-API) würden sonst zum Stammpreis verkauft.
        $unconfigured = array_filter($productLineItems, static fn (LineItem $item) => !\is_array($item->getPayloadValue(LineItemFactory::PAYLOAD_KEY)['configuration'] ?? null));
        $configurable = $this->configurableProductIds(array_map(static fn (LineItem $item) => (string) $item->getReferencedId(), $unconfigured));
        foreach ($unconfigured as $lineItem) {
            if (!isset($configurable[strtolower((string) $lineItem->getReferencedId())])) {
                continue;
            }
            $calculationContext ??= $this->contextFactory->fromSalesChannelContext($context);
            $toCalculate->getLineItems()->removeElement($lineItem);
            $original->getLineItems()->remove($lineItem->getId());
            $toCalculate->addErrors(new MensuraCartError($lineItem->getId(), (string) $lineItem->getLabel(), (new Messages($calculationContext->language))->text('cart.configuration_required')));
        }

        foreach ($productLineItems as $lineItem) {
            $payload = $lineItem->getPayloadValue(LineItemFactory::PAYLOAD_KEY);
            if (!\is_array($payload) || !\is_array($payload['configuration'] ?? null)) {
                continue;
            }
            $productId = (string) $lineItem->getReferencedId();
            $catalogs[$productId] ??= $this->loader->load($productId, $context->getContext());
            $catalog = $catalogs[$productId];
            $calculationContext ??= $this->contextFactory->fromSalesChannelContext($context);

            $configuration = $payload['configuration'];
            $configuration['productId'] = $productId;
            $result = $catalog instanceof Catalog ? $this->engine->calculate($configuration, $catalog, $calculationContext) : null;
            if ($result === null || !$result->isValid()) {
                $reason = $result !== null ? implode(' · ', array_column($result->toStoreApi()['errors'], 'message')) : 'product.not_configurable';
                $toCalculate->getLineItems()->removeElement($lineItem);
                $original->getLineItems()->remove($lineItem->getId());
                $toCalculate->addErrors(new MensuraCartError($lineItem->getId(), (string) $lineItem->getLabel(), $reason));
                continue;
            }

            LineItemFactory::applyResult($lineItem, $result, $catalog);
            // Lieferinformation je Stück: Gewicht (kg) und Format (mm) der Konfiguration statt der Produkt-Stammdaten,
            // damit Versandregeln (Warenkorbgewicht) und Versand-Plugins mit den echten Werten rechnen
            $delivery = $lineItem->getDeliveryInformation();
            if ($delivery !== null) {
                $weight = $result->getWeight();
                if ($weight !== null) {
                    $delivery->setWeight($weight['piece']);
                }
                $areas = $result->getAreas();
                if ($areas !== null) {
                    $delivery->setWidth($areas['width']);
                    $delivery->setHeight($areas['height']);
                }
            }
            $quantity = $lineItem->getQuantity();
            $taxRules = $lineItem->getPrice()?->getTaxRules() ?? new TaxRuleCollection();
            $total = (float) $result->getSaleTotal();
            if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
                $total = round($total * (1 + array_sum($taxRules->map(static fn ($rule) => $rule->getTaxRate())) / 100), 2);
            }
            $position = $this->calculator->calculate(new QuantityPriceDefinition($total, $taxRules, 1), $context);
            $lineItem->setPriceDefinition(new QuantityPriceDefinition(round($total / max(1, $quantity), 2), $taxRules, $quantity));
            $lineItem->setPrice(new CalculatedPrice(
                round($position->getTotalPrice() / max(1, $quantity), 2),
                $position->getTotalPrice(),
                $position->getCalculatedTaxes(),
                $taxRules,
                $quantity,
            ));
        }
    }

    /**
     * Produkt-IDs (klein, hex) mit aktivem Mensura-Profil – eine Abfrage für alle unkonfigurierten Positionen.
     *
     * @param list<string> $productIds
     *
     * @return array<string, true>
     */
    private function configurableProductIds(array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter($productIds, static fn (string $id) => Uuid::isValid($id))));
        if ($productIds === []) {
            return [];
        }
        $rows = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(product_id)) FROM ld_mensura_product_profile WHERE active = 1 AND product_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($productIds)],
            ['ids' => ArrayParameterType::BINARY],
        );

        return array_fill_keys(array_map('strval', $rows), true);
    }
}
