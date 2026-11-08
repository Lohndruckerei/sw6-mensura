<?php
/*
 * Logistikanteil (KONZEPT 3d) im laufenden Shop prüfen – nicht Teil von check-all:
 *   bin/console mensura:demo:seed && docker exec -i shopware php < tests/Backend/Shop/logistics.php
 * Legt drei Test-Integrationen an (ohne Recht / ld_mensura.logistics / ld_mensura.logistics_edit), eine Express-Regel
 * (4,18 €/m², mind. 25 € je Bestellung), einen Gast-Kunden und eine Testbestellung mit zwei Express-Positionen.
 * Integrationen und Express-Regel werden danach entfernt; Gast und Bestellung bleiben (Testdaten im Entwicklungsshop).
 */
$pdo = new PDO('mysql:host=127.0.0.1;dbname=shopware', 'root', 'root', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$api = 'http://localhost/api';
$fail = 0;
function check(string $label, bool $ok, string $detail = ''): void { global $fail; echo ($ok ? 'OK   ' : 'FEHL ') . $label . ($detail !== '' ? "  ($detail)" : '') . "\n"; if (!$ok) { $fail++; } }
function req(string $method, string $url, ?array $body, array $headers = []): array {
    $ch = curl_init($url);
    $headers = array_merge(['Accept: application/json', 'Content-Type: application/json'], $headers);
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_HEADER => true]);
    if ($body !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body)); }
    $raw = (string) curl_exec($ch);
    $size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    preg_match('/sw-context-token: (\S+)/i', substr($raw, 0, $size), $m);
    return [$status, json_decode(substr($raw, $size), true), substr($raw, $size), $m[1] ?? null];
}
function admin(string $method, string $path, ?array $body, string $token): array {
    global $api;
    return req($method, $api . $path, $body, ['Authorization: Bearer ' . $token]);
}
function makeIntegration(PDO $pdo, string $label, array $privileges): array {
    $roleId = random_bytes(16); $integrationId = random_bytes(16);
    $key = 'SWIA' . strtoupper(substr(bin2hex(random_bytes(16)), 0, 22));
    $secret = bin2hex(random_bytes(24));
    $now = (new DateTime())->format('Y-m-d H:i:s.v');
    $pdo->prepare('INSERT INTO acl_role (id, name, privileges, created_at) VALUES (?, ?, ?, ?)')->execute([$roleId, $label, json_encode($privileges), $now]);
    $pdo->prepare('INSERT INTO integration (id, access_key, secret_access_key, label, admin, created_at) VALUES (?, ?, ?, ?, 0, ?)')
        ->execute([$integrationId, $key, password_hash($secret, PASSWORD_BCRYPT), $label, $now]);
    $pdo->prepare('INSERT INTO integration_role (integration_id, acl_role_id) VALUES (?, ?)')->execute([$integrationId, $roleId]);
    [, $d] = req('POST', 'http://localhost/api/oauth/token', ['grant_type' => 'client_credentials', 'client_id' => $key, 'client_secret' => $secret]);
    return [$roleId, $integrationId, $d['access_token'] ?? null];
}

$entities = ['ld_mensura_calculation', 'ld_mensura_calculation_material', 'ld_mensura_calculation_option_value', 'ld_mensura_material',
    'ld_mensura_material_translation', 'ld_mensura_option_group', 'ld_mensura_option_value', 'ld_mensura_option_set', 'ld_mensura_option_set_group',
    'ld_mensura_price_table', 'ld_mensura_price_table_row', 'ld_mensura_product_profile', 'ld_mensura_product_profile_material',
    'ld_mensura_product_profile_option', 'ld_mensura_customer_group_rule', 'ld_mensura_order_line_item', 'customer_group', 'media',
    'product', 'order', 'order_line_item', 'system_config', 'tax', 'currency', 'language'];
$base = ['ld_mensura.viewer', 'ld_mensura.orders'];
foreach ($entities as $e) { $base[] = "$e:read"; }
$read = ['ld_mensura.logistics', 'ld_mensura_logistics_rule:read', 'ld_mensura_order_line_item_logistics:read'];
$edit = array_merge($read, ['ld_mensura.logistics_edit', 'ld_mensura_logistics_rule:create', 'ld_mensura_logistics_rule:update', 'ld_mensura_logistics_rule:delete']);

$acryl = '0194e1a0000070008000000000000b02';
$alu = '0194e1a0000070008000000000000b01';
$product = '0194e1a0000070008000000000000a01';
$expressId = $pdo->query("SELECT LOWER(HEX(v.id)) FROM ld_mensura_option_value v JOIN ld_mensura_option_group g ON g.id = v.option_group_id WHERE g.technical_name = 'lieferzeit' AND v.technical_name = 'express'")->fetchColumn();
$acrylCalc = $pdo->query("SELECT LOWER(HEX(calculation_id)) FROM ld_mensura_calculation_material WHERE LOWER(HEX(material_id))='$acryl' LIMIT 1")->fetchColumn();
$aluCalc = $pdo->query("SELECT LOWER(HEX(calculation_id)) FROM ld_mensura_calculation_material WHERE LOWER(HEX(material_id))='$alu' LIMIT 1")->fetchColumn();
$created = [];
$expressRule = bin2hex(random_bytes(16));

try {
    $none = makeIntegration($pdo, 'mensura-logistik-test-ohne', $base);
    $reader = makeIntegration($pdo, 'mensura-logistik-test-lesen', array_merge($base, $read));
    $editor = makeIntegration($pdo, 'mensura-logistik-test-pflegen', array_merge($base, $edit));
    $created = [$none, $reader, $editor];
    check('Tokens ohne/lesen/pflegen', $none[2] && $reader[2] && $editor[2]);

    // Regeln lesen
    [$s] = admin('POST', '/search/ld-mensura-logistics-rule', [], $none[2]);
    check('Regeln lesen ohne Recht → 403', $s === 403, "status $s");
    [$s, $d] = admin('POST', '/search/ld-mensura-logistics-rule', [], $reader[2]);
    check('Regeln lesen mit ld_mensura.logistics → Demo-Regel', $s === 200 && count($d['data'] ?? []) >= 1, "status $s");

    // Schreiben + Validator
    $rule = ['id' => $expressRule, 'optionValueId' => $expressId, 'costType' => 'express', 'form' => 'per_sqm', 'value' => 4.18, 'minimumAmount' => 25.0, 'minimumScope' => 'order'];
    [$s] = admin('POST', '/ld-mensura-logistics-rule', $rule, $reader[2]);
    check('Regel anlegen nur mit Leserecht → 403', $s === 403, "status $s");
    [$s, $d] = admin('POST', '/ld-mensura-logistics-rule', ['optionValueId' => $expressId, 'materialId' => $alu, 'costType' => 'express', 'form' => 'per_sqm', 'value' => 1], $editor[2]);
    check('Validator: zwei Ziele → 400', $s === 400, "status $s " . ($d['errors'][0]['detail'] ?? ''));
    [$s, $d] = admin('POST', '/ld-mensura-logistics-rule', ['materialId' => $alu, 'costType' => 'teuer', 'form' => 'per_sqm', 'value' => 1], $editor[2]);
    check('Validator: unbekannte Kostenart → 400', $s === 400, "status $s");
    [$s] = admin('POST', '/ld-mensura-logistics-rule', $rule, $editor[2]);
    check('Express-Regel anlegen mit Pflegerecht → 204', $s === 204, "status $s");

    // Vorschau-Route
    $preview = ['calculationId' => $acrylCalc, 'samples' => [['materialId' => $acryl, 'format' => ['width' => 1500, 'height' => 1000]]],
        'rules' => [['id' => 'draft', 'materialId' => $acryl, 'costType' => 'bulky', 'form' => 'per_sqm', 'value' => 20, 'rampFrom' => 1000, 'rampTo' => 1400]]];
    [$s] = admin('POST', '/_action/ld-mensura/logistics/preview', $preview, $none[2]);
    check('Vorschau ohne Recht → 403', $s === 403, "status $s");
    [$s, $d] = admin('POST', '/_action/ld-mensura/logistics/preview', $preview, $reader[2]);
    $row = $d['samples'][0] ?? [];
    check('Vorschau mit Recht: gespeichert 120,16 €, Entwurf ergänzt', $s === 200 && ($row['totalSaved'] ?? null) == 120.16 && ($row['total'] ?? 0) > 120.16, "status $s " . json_encode(array_intersect_key($row, array_flip(['total', 'totalSaved', 'logistics', 'sharePercent', 'warn']))));

    // Testrechner
    $cfg = ['calculationId' => $acrylCalc, 'configuration' => ['materialId' => $acryl, 'format' => ['width' => 1500, 'height' => 1000], 'perMotif' => 1]];
    [$s, $d] = admin('POST', '/_action/ld-mensura/test-calculate', $cfg, $none[2]);
    check('Testrechner ohne Recht: kein Logistikanteil, Rechenweg ohne Logistik', $s === 200 && $d['logistics'] === null && !preg_match('/logisti/i', json_encode($d)), "status $s total " . json_encode($d['result']['price']['total'] ?? null));
    [$s, $d] = admin('POST', '/_action/ld-mensura/test-calculate', $cfg, $reader[2]);
    check('Testrechner mit Recht: „davon Logistik“ 26,66 €', $s === 200 && abs(($d['logistics']['sale'] ?? 0) - 26.66) < 0.011, json_encode($d['logistics'] ?? null));

    // Warenkorb und Bestellung (Gast), zwei Express-Positionen Alu 1 m² und 2 m²
    $key = $pdo->query("SELECT access_key FROM sales_channel WHERE type_id = UNHEX('8A243080F92E4C719546314B577CF82B') LIMIT 1")->fetchColumn();
    $country = $pdo->query("SELECT LOWER(HEX(id)) FROM country WHERE iso = 'DE'")->fetchColumn();
    $salutation = $pdo->query("SELECT LOWER(HEX(id)) FROM salutation LIMIT 1")->fetchColumn();
    $store = fn (string $m, string $p, ?array $b, ?string $t) => req($m, 'http://localhost/store-api' . $p, $b, array_filter(['sw-access-key: ' . $key, $t ? 'sw-context-token: ' . $t : null]));
    [$s, , , $token] = $store('POST', '/account/register', [
        // Name/E-Mail ohne „Logistik“, sonst schlägt die Suche nach dem Wort in der Warenkorb-Antwort an (Lieferadresse)
        'guest' => true, 'salutationId' => $salutation, 'firstName' => 'Test', 'lastName' => 'Kunde',
        'email' => 'mensura-test-' . bin2hex(random_bytes(3)) . '@example.test', 'storefrontUrl' => 'http://localhost', 'acceptedDataProtection' => true,
        'billingAddress' => ['street' => 'Teststraße 1', 'zipcode' => '12345', 'city' => 'Teststadt', 'countryId' => $country],
    ], null);
    check('Gast angelegt', $s === 200 && $token !== null, "status $s");
    $position = fn (int $w, int $h) => ['configuration' => ['schema' => 1, 'productId' => $product, 'materialId' => $alu, 'format' => ['width' => $w, 'height' => $h],
        'motifs' => 1, 'perMotif' => 1, 'options' => ['farbigkeit' => '4-0', 'veredelung' => 'keine', 'bohrung' => 'nein', 'schnitt' => 'recht'], 'deliveryValue' => 'express']];
    // Einzelpreise wie auf der Produktseite
    [, $one] = $store('POST', '/ld-mensura/calculate', $position(1000, 1000), $token);
    [, $two] = $store('POST', '/ld-mensura/calculate', $position(2000, 1000), $token);
    $alone = ($one['price']['total'] ?? 0) + ($two['price']['total'] ?? 0);
    [$s] = $store('POST', '/ld-mensura/cart/add', $position(1000, 1000), $token);
    [$s2, $cart] = $store('POST', '/ld-mensura/cart/add', $position(2000, 1000), $token);
    $items = array_values(array_filter($cart['lineItems'] ?? [], fn ($i) => $i['referencedId'] === $product));
    $cartSum = array_sum(array_map(fn ($i) => $i['price']['totalPrice'], $items));
    $gross = ($cart['price']['taxStatus'] ?? 'gross') === 'gross';
    check('Warenkorb: zwei Express-Positionen', $s === 200 && $s2 === 200 && count($items) === 2, "status $s/$s2, " . count($items));
    // Produktseite: je 25 € Mindestbetrag; Warenkorb: einmal 25 € verteilt → netto 25 € günstiger (brutto × Steuer)
    check('Warenkorb günstiger als Produktseite (Mindestbetrag einmal)', $cartSum < ($gross ? $alone * 1.19 : $alone) - 1, sprintf('Produktseite netto %.2f, Warenkorb %.2f (%s)', $alone, $cartSum, $gross ? 'brutto' : 'netto'));
    check('Warenkorb-Antwort ohne Logistikanteil', !preg_match('/logisti/i', json_encode($cart)));

    [$s, $order] = $store('POST', '/checkout/order', [], $token);
    check('Bestellung angelegt', $s === 200 && isset($order['id']), "status $s " . ($order['errors'][0]['detail'] ?? ''));
    $orderId = $order['id'] ?? '';
    $rows = $pdo->query("SELECT l.logistics_sale, l.production_time_sale, l.share_percent, l.goods_contribution_margin, l.lines, s.sale_total
        FROM ld_mensura_order_line_item_logistics l
        JOIN order_line_item oli ON oli.id = l.order_line_item_id AND oli.version_id = l.order_line_item_version_id
        JOIN ld_mensura_order_line_item s ON s.order_line_item_id = oli.id AND s.order_line_item_version_id = oli.version_id
        WHERE LOWER(HEX(oli.order_id)) = '$orderId' AND oli.version_id = UNHEX('0FA91CE3E96A4BC2BE4BD9CE752C3425')")->fetchAll(PDO::FETCH_ASSOC);
    $sumLogistics = array_sum(array_column($rows, 'logistics_sale'));
    check('Logistik-Schnappschuss je Position', count($rows) === 2, (string) count($rows));
    check('Mindestbetrag einmal: Σ Logistik = 25 €', abs($sumLogistics - 25.0) < 0.02, sprintf('%.2f', $sumLogistics));
    check('Produktionszeit-Aufschlag getrennt (> 0)', count($rows) === 2 && min(array_map('floatval', array_column($rows, 'production_time_sale'))) > 0, json_encode(array_column($rows, 'production_time_sale')));
    $lines = json_decode($rows[0]['lines'] ?? '[]', true);
    check('Zeilen mit deliveryKey statt IDs', ($lines[0]['deliveryKey'] ?? null) === 'express' && !isset($lines[0]['optionValueId']), json_encode($lines[0] ?? null));

    // Schnappschuss über die Admin-API
    [$s, $d] = admin('POST', '/search/ld-mensura-order-line-item-logistics', ['filter' => [['type' => 'equals', 'field' => 'orderLineItem.orderId', 'value' => $orderId]]], $none[2]);
    check('Logistik-Schnappschuss ohne Recht → 403', $s === 403, "status $s");
    [$s, $d] = admin('POST', '/search/ld-mensura-order-line-item-logistics', ['limit' => 5], $reader[2]);
    $snap = $d['data'][0]['attributes'] ?? $d['data'][0] ?? [];
    check('Logistik-Schnappschuss mit Recht lesbar, Waren-DB ohne EK-Recht leer', $s === 200 && isset($snap['logisticsSale']) && $snap['goodsContributionMargin'] === null, "status $s " . json_encode(['ls' => $snap['logisticsSale'] ?? null, 'gdb' => $snap['goodsContributionMargin'] ?? 'fehlt']));
    [$s, $d] = admin('POST', '/search/ld-mensura-order-line-item', ['limit' => 5], $none[2]);
    check('Allgemeiner Schnappschuss ohne Logistik', $s === 200 && !preg_match('/logisti|productionTime/i', json_encode($d)), "status $s");
} finally {
    $pdo->prepare('DELETE FROM ld_mensura_logistics_rule WHERE id = UNHEX(?)')->execute([$expressRule]);
    foreach ($created as [$roleId, $integrationId]) {
        $pdo->prepare('DELETE FROM integration_role WHERE integration_id = ?')->execute([$integrationId]);
        $pdo->prepare('DELETE FROM integration WHERE id = ?')->execute([$integrationId]);
        $pdo->prepare('DELETE FROM acl_role WHERE id = ?')->execute([$roleId]);
    }
    echo 'Aufgeräumt: Integrationen ' . $pdo->query("SELECT COUNT(*) FROM integration WHERE label LIKE 'mensura-logistik-test%'")->fetchColumn()
        . ', Express-Regel ' . $pdo->query("SELECT COUNT(*) FROM ld_mensura_logistics_rule WHERE cost_type = 'express'")->fetchColumn() . " übrig\n";
}
echo $fail === 0 ? "✓ Logistikanteil im Shop bestanden\n" : "✗ $fail Fehler\n";
exit($fail === 0 ? 0 : 1);
