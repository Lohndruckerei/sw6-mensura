<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Service\Cart;

use Lohndruckerei\Mensura\Service\Calculation\CatalogLoader;
use Lohndruckerei\Mensura\Service\Calculation\ContextFactory;
use Lohndruckerei\Mensura\Service\Calculation\PriceEngine;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\CartConvertedEvent;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Rechenweg-Schnappschuss bei der Bestellung (INTEGRATION.md Abschnitt 2).
 *
 * Wenn der Warenkorb in eine Bestellung umgewandelt wird, hängt Mensura an jede konfigurierte Position einen
 * Datensatz `ld_mensura_order_line_item` (`orderLineItem.extensions.ldMensura`) – er wird zusammen mit der Bestellung
 * gespeichert: Konfiguration, kompletter Rechenweg EK und VK, EK gesamt, VK gesamt, Kundengruppe, Lieferantenvorschlag.
 * Shopware selbst friert Preis, Menge, Bezeichnung und Payload der Bestellposition ein; spätere Änderungen an
 * Kalkulationen, Materialien oder Optionen ändern bestehende Bestellungen also nicht.
 *
 * Nur Admin-API (nicht ApiAware), nie im Kundenkonto.
 */
class OrderSnapshotSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CatalogLoader $loader,
        private readonly PriceEngine $engine,
        private readonly ContextFactory $contextFactory,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [CartConvertedEvent::class => 'onCartConverted'];
    }

    public function onCartConverted(CartConvertedEvent $event): void
    {
        $converted = $event->getConvertedCart();
        $cart = $event->getCart();
        $context = $event->getSalesChannelContext();
        $calculationContext = null;
        $catalogs = [];
        $changed = false;

        foreach ($converted['lineItems'] ?? [] as $index => $orderLineItem) {
            $payload = $orderLineItem['payload'][LineItemFactory::PAYLOAD_KEY] ?? null;
            if (($orderLineItem['type'] ?? null) !== LineItem::PRODUCT_LINE_ITEM_TYPE || !\is_array($payload['configuration'] ?? null)) {
                continue;
            }
            $productId = (string) ($orderLineItem['referencedId'] ?? $orderLineItem['productId'] ?? '');
            $catalogs[$productId] ??= $this->loader->load($productId, $context->getContext());
            $catalog = $catalogs[$productId];
            if ($catalog === null) {
                continue;
            }
            $calculationContext ??= $this->contextFactory->fromSalesChannelContext($context);
            $configuration = $payload['configuration'];
            $configuration['productId'] = $productId;
            $result = $this->engine->calculate($configuration, $catalog, $calculationContext);
            if (!$result->isValid()) {
                continue;
            }
            $snapshot = $result->toSnapshot();

            // Abgleich mit dem Preis, den der Kunde bestätigt hat (Bestellposition), netto
            $cartLineItem = $cart->getLineItems()->get((string) ($orderLineItem['identifier'] ?? ''));
            $orderedTotal = $cartLineItem?->getPrice()?->getTotalPrice();
            if ($orderedTotal !== null && $context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
                $orderedTotal -= $cartLineItem->getPrice()->getCalculatedTaxes()->getAmount();
            }

            $converted['lineItems'][$index]['ldMensura'] = [
                'id' => Uuid::randomHex(),
                'schemaVersion' => 1,
                'calculationId' => $snapshot['calculationId'],
                'calculationTechnicalName' => $snapshot['calculationTechnicalName'],
                'materialId' => $snapshot['materialId'],
                'supplierId' => $snapshot['supplierId'],
                'supplierProductNumber' => null,
                'customerGroupId' => $snapshot['customerGroupId'],
                'configuration' => $snapshot['configuration'],
                'breakdown' => $snapshot['breakdown'] + [
                    'contributionMargin' => $snapshot['contributionMargin'],
                    'orderedNetTotal' => $orderedTotal !== null ? round($orderedTotal, 2) : null,
                    'calculatedAt' => $calculationContext->now->format(\DATE_ATOM),
                ],
                'purchaseTotal' => $snapshot['purchaseTotal'],
                'saleTotal' => $snapshot['saleTotal'],
                'artwork' => $configuration['artwork'] ?? null,
            ];
            // Logistik-Schnappschuss (KONZEPT 3d.5): eigene Tabelle mit eigenem Recht, für Vectura
            $logistics = $result->toLogisticsSnapshot();
            if ($logistics !== null) {
                $converted['lineItems'][$index]['ldMensuraLogistics'] = ['id' => Uuid::randomHex()] + $logistics;
            }
            $changed = true;
        }

        if ($changed) {
            $event->setConvertedCart($converted);
        }
    }
}
