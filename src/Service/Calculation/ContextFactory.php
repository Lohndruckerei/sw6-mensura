<?php

declare(strict_types=1);

namespace Lohndruckerei\Mensura\Service\Calculation;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Übersetzt den Shopware-Kontext (Kundengruppe, Sprache, Währung, Verkaufskanal) und die Plugin-Einstellungen
 * in den Shopware-freien CalculationContext der Engine.
 */
class ContextFactory
{
    private const CONFIG = 'LdMensura.config.';

    public function __construct(private readonly SystemConfigService $config)
    {
    }

    /**
     * Kontext für den Testrechner der Administration: Plugin-Einstellungen ohne Verkaufskanal, wählbare Kundengruppe.
     */
    public function forAdministration(?string $customerGroupId, string $language = 'de'): CalculationContext
    {
        $get = fn (string $key) => $this->config->get(self::CONFIG . $key);
        $workingDays = $get('workingDays');
        $cutoff = $get('orderCutoff');

        return new CalculationContext(
            customerGroupId: $customerGroupId,
            language: CalculationContext::languageFromLocale($language),
            now: new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin')),
            orderCutoff: \is_string($cutoff) && preg_match('/^[0-2]\d:[0-5]\d$/', $cutoff) === 1 ? $cutoff : null,
            workingDays: \is_array($workingDays) && $workingDays !== [] ? array_values(array_map('intval', $workingDays)) : [1, 2, 3, 4, 5],
            holidays: array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/', (string) ($get('holidays') ?? '')) ?: []), static fn (string $d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1)),
        );
    }

    /**
     * @param string|null $taxId Steuer des Produkts; damit wird der Steuersatz für `price.totalGross` ermittelt
     *                           (Steuerregeln des Kunden/Lands wie im Warenkorb, z. B. 0 % bei steuerfreier Lieferung)
     */
    public function fromSalesChannelContext(SalesChannelContext $context, ?string $taxId = null): CalculationContext
    {
        $salesChannelId = $context->getSalesChannelId();
        $get = fn (string $key) => $this->config->get(self::CONFIG . $key, $salesChannelId);

        $workingDays = $get('workingDays');
        $workingDays = \is_array($workingDays) && $workingDays !== [] ? array_values(array_map('intval', $workingDays)) : [1, 2, 3, 4, 5];
        $holidays = array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/', (string) ($get('holidays') ?? '')) ?: []), static fn (string $d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1));
        $cutoff = $get('orderCutoff');
        $layout = $get('storefrontLayout');
        $display = [];
        foreach (CalculationContext::DISPLAY_DEFAULTS as $key => $default) {
            $value = $get($key);
            $display[$key] = $value === null ? $default : (bool) $value;
        }

        return new CalculationContext(
            customerGroupId: $context->getCurrentCustomerGroup()->getId(),
            language: CalculationContext::languageFromLocale($context->getLanguageInfo()->localeCode),
            now: new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin')),
            currency: $context->getCurrency()->getIsoCode(),
            orderCutoff: \is_string($cutoff) && preg_match('/^[0-2]\d:[0-5]\d$/', $cutoff) === 1 ? $cutoff : null,
            workingDays: $workingDays,
            holidays: $holidays,
            storefrontLayout: \in_array($layout, ['compact', 'guided'], true) ? $layout : 'compact',
            allowLayoutSwitch: (bool) ($get('allowLayoutSwitch') ?? true),
            layoutTestShare: (int) ($get('layoutTestShare') ?? 0),
            display: $display,
            taxRate: $taxId !== null ? (float) array_sum($context->buildTaxRules($taxId)->map(static fn ($rule) => $rule->getTaxRate())) : null,
            // Preise sind in der Basiswährung gepflegt; Endbeträge werden mit dem Faktor des Kanals umgerechnet
            currencyFactor: $context->getCurrency()->getFactor() > 0 ? $context->getCurrency()->getFactor() : 1.0,
        );
    }
}
