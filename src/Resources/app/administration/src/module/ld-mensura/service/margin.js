/**
 * Margenrechner (docs/KONZEPT.md 3.5): EK und VK sind getrennte Felder, der Rechner schlägt den VK nur vor.
 *
 * - Marge %    = (VK − EK) ÷ VK   (auf den Verkaufspreis)
 * - Aufschlag % = (VK − EK) ÷ EK  (auf den Einkaufspreis)
 * Beide werden immer angezeigt, weil sie verschiedene Zahlen sind (25 % Marge = 33,3 % Aufschlag).
 */

export const DEFAULT_RULE = { saleRule: 'margin', saleRuleValue: 25, marginMin: 20, marginMax: 30, priceRounding: null };

const num = (value) => (value === null || value === undefined || value === '' ? null : Number(value));

/**
 * @returns {{ margin: number, markup: number, state: 'ok'|'warn'|'err' } | null}
 *          state: ok = Marge im/über dem Zielkorridor, warn = darunter, err = VK ≤ EK
 */
export function marginInfo(purchase, sale, rule = DEFAULT_RULE) {
    const ek = num(purchase);
    const vk = num(sale);
    if (ek === null || vk === null || ek <= 0 || vk <= 0) {
        return null;
    }
    const margin = ((vk - ek) / vk) * 100;
    const markup = ((vk - ek) / ek) * 100;
    let state = 'ok';
    if (vk <= ek) {
        state = 'err';
    } else if (num(rule?.marginMin) !== null && margin < num(rule.marginMin)) {
        state = 'warn';
    }

    return { margin, markup, state };
}

/**
 * VK-Vorschlag nach VK-Regel der Kalkulation; null bei Regel „frei“ oder ohne EK.
 * Beispiel EK 26,00 €: Marge 25 % → 34,67 €, Aufschlag 25 % → 32,50 €, Faktor 1,3 → 33,80 €
 */
export function suggestSale(purchase, rule = DEFAULT_RULE) {
    const ek = num(purchase);
    const value = num(rule?.saleRuleValue);
    if (ek === null || ek <= 0 || value === null) {
        return null;
    }
    let vk;
    switch (rule.saleRule) {
        case 'margin':
            if (value >= 100) {
                return null;
            }
            vk = ek / (1 - value / 100);
            break;
        case 'markup':
            vk = ek * (1 + value / 100);
            break;
        case 'factor':
            vk = ek * value;
            break;
        default:
            return null;
    }
    const step = num(rule.priceRounding);
    if (step && step > 0) {
        vk = Math.ceil(vk / step - 1e-9) * step;
    }

    return Math.round(vk * 100) / 100;
}

/** VK-Regel aus einer Kalkulation (Entity oder Objekt) lesen, sonst Vorgabe. */
export function ruleOf(calculation) {
    if (!calculation) {
        return DEFAULT_RULE;
    }

    return {
        saleRule: calculation.saleRule ?? DEFAULT_RULE.saleRule,
        saleRuleValue: calculation.saleRuleValue ?? DEFAULT_RULE.saleRuleValue,
        marginMin: calculation.marginMin ?? DEFAULT_RULE.marginMin,
        marginMax: calculation.marginMax ?? DEFAULT_RULE.marginMax,
        priceRounding: calculation.priceRounding ?? null,
    };
}

export function formatNumber(value, digits = 2) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) {
        return '–';
    }

    return Number(value).toLocaleString(Shopware.Store.get('session').currentLocale ?? 'de-DE', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    });
}

/** Betrag mit zwei Nachkommastellen und „€“, ohne Wert „–“. */
export function formatMoney(value) {
    return value === null || value === undefined ? '–' : `${formatNumber(value, 2)} €`;
}
