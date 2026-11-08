<?php
/*
 * EK-Schutz (KONZEPT 3b) über die echte Admin-API prüfen – im laufenden Shop, nicht Teil von check-all:
 *   docker exec -i shopware php < tests/Backend/Shop/purchase-protection.php
 * Legt zwei Test-Integrationen an (Rolle ohne/mit ld_mensura.purchase) und entfernt sie danach wieder.
 * Ändert kurz einen Preisanteil und stellt ihn wieder her; danach zur Sicherheit bin/console mensura:demo:seed.
 */
$pdo = new PDO('mysql:host=127.0.0.1;dbname=shopware', 'root', 'root', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$base = 'http://localhost/api';
$fail = 0;
function check(string $label, bool $ok, string $detail = ''): void { global $fail; echo ($ok ? 'OK   ' : 'FEHL ') . $label . ($detail !== '' ? "  ($detail)" : '') . "\n"; if (!$ok) { $fail++; } }
function req(string $method, string $url, ?array $body, ?string $token): array {
    $ch = curl_init($url);
    $headers = ['Accept: application/json', 'Content-Type: application/json'];
    if ($token) { $headers[] = 'Authorization: Bearer ' . $token; }
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers]);
    if ($body !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body)); }
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return [$status, json_decode((string) $raw, true)];
}

$entities = ['ld_mensura_calculation', 'ld_mensura_calculation_material', 'ld_mensura_calculation_option_value', 'ld_mensura_material',
    'ld_mensura_material_translation', 'ld_mensura_option_group', 'ld_mensura_option_value', 'ld_mensura_option_set', 'ld_mensura_option_set_group',
    'ld_mensura_price_table', 'ld_mensura_price_table_row', 'ld_mensura_product_profile', 'ld_mensura_product_profile_material',
    'ld_mensura_product_profile_option', 'ld_mensura_customer_group_rule', 'ld_mensura_order_line_item', 'ld_mensura_feature_value',
    'ld_mensura_use_case_assignment', 'customer_group', 'media', 'product', 'order', 'order_line_item', 'system_config', 'tax', 'currency', 'language'];
$basePrivileges = ['ld_mensura.viewer', 'ld_mensura.editor', 'ld_mensura.orders'];
foreach ($entities as $e) { foreach (['read', 'update', 'create'] as $a) { $basePrivileges[] = "$e:$a"; } }

$created = [];
function makeIntegration(PDO $pdo, string $label, array $privileges): array {
    $roleId = random_bytes(16); $integrationId = random_bytes(16);
    $key = 'SWIA' . strtoupper(substr(bin2hex(random_bytes(16)), 0, 22));
    $secret = bin2hex(random_bytes(24));
    $now = (new DateTime())->format('Y-m-d H:i:s.v');
    $pdo->prepare('INSERT INTO acl_role (id, name, privileges, created_at) VALUES (?, ?, ?, ?)')->execute([$roleId, $label, json_encode($privileges), $now]);
    $pdo->prepare('INSERT INTO integration (id, access_key, secret_access_key, label, admin, created_at) VALUES (?, ?, ?, ?, 0, ?)')
        ->execute([$integrationId, $key, password_hash($secret, PASSWORD_BCRYPT), $label, $now]);
    $pdo->prepare('INSERT INTO integration_role (integration_id, acl_role_id) VALUES (?, ?)')->execute([$integrationId, $roleId]);
    return [$roleId, $integrationId, $key, $secret];
}
function token(string $key, string $secret): ?string {
    [$s, $d] = req('POST', 'http://localhost/api/oauth/token', ['grant_type' => 'client_credentials', 'client_id' => $key, 'client_secret' => $secret], null);
    return $d['access_token'] ?? null;
}

try {
    $restricted = makeIntegration($pdo, 'mensura-ek-test-ohne', $basePrivileges);
    $allowed = makeIntegration($pdo, 'mensura-ek-test-mit', array_merge($basePrivileges, ['ld_mensura.purchase']));
    $created = [$restricted, $allowed];
    $tR = token($restricted[2], $restricted[3]);
    $tA = token($allowed[2], $allowed[3]);
    check('Token ohne/mit EK-Recht', $tR !== null && $tA !== null);

    $alu = '0194e1a0000070008000000000000b01';
    [$s, $d] = req('GET', "$base/ld-mensura-material/$alu", null, $tR);
    $attrs = $d['data']['attributes'] ?? $d['data'] ?? [];
    check('Lesen ohne Recht: pricePurchase null, VK da', $s === 200 && $attrs['pricePurchase'] === null && $attrs['priceSale'] == 29, "status $s, EK " . json_encode($attrs['pricePurchase'] ?? 'fehlt') . ', VK ' . json_encode($attrs['priceSale'] ?? null));
    [$s, $d] = req('GET', "$base/ld-mensura-material/$alu", null, $tA);
    $attrs = $d['data']['attributes'] ?? $d['data'] ?? [];
    check('Lesen mit Recht: pricePurchase 26', $s === 200 && $attrs['pricePurchase'] == 26, json_encode($attrs['pricePurchase'] ?? null));

    [$s] = req('POST', "$base/search/ld-mensura-material", ['filter' => [['type' => 'range', 'field' => 'pricePurchase', 'parameters' => ['gte' => 20]]]], $tR);
    check('Suche nach EK ohne Recht → 403', $s === 403, "status $s");
    [$s] = req('POST', "$base/search/ld-mensura-material", ['aggregations' => [['name' => 'ek', 'type' => 'sum', 'field' => 'pricePurchase']]], $tR);
    check('Aggregation über EK ohne Recht → 403', $s === 403, "status $s");
    [$s] = req('POST', "$base/search/ld-mensura-material", ['filter' => [['type' => 'range', 'field' => 'pricePurchase', 'parameters' => ['gte' => 20]]]], $tA);
    check('Suche nach EK mit Recht → 200', $s === 200, "status $s");

    [$s, $d] = req('PATCH', "$base/ld-mensura-material/$alu", ['pricePurchase' => 1.0], $tR);
    check('EK ändern ohne Recht → 403', $s === 403, "status $s " . ($d['errors'][0]['code'] ?? ''));
    $ek = $pdo->query("SELECT price_purchase FROM ld_mensura_material WHERE LOWER(HEX(id))='$alu'")->fetchColumn();
    check('EK in der DB unverändert (26)', (float) $ek === 26.0, (string) $ek);

    // Preisanteile: VK ändern ohne Recht, EK muss erhalten bleiben
    $row = $pdo->query("SELECT LOWER(HEX(id)) id, price_components FROM ld_mensura_calculation_option_value WHERE price_components IS NOT NULL AND JSON_LENGTH(price_components) > 0 AND JSON_EXTRACT(price_components, '$[0].purchase') IS NOT NULL LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $before = json_decode($row['price_components'], true);
    [$s, $d] = req('GET', "$base/ld-mensura-calculation-option-value/{$row['id']}", null, $tR);
    $seen = ($d['data']['attributes'] ?? $d['data'])['priceComponents'];
    check('Preisanteile ohne Recht: purchase null', $seen[0]['purchase'] === null, json_encode($seen[0]));
    $seen[0]['sale'] = round(($before[0]['sale'] ?? 0) + 1.11, 2);
    [$s] = req('PATCH', "$base/ld-mensura-calculation-option-value/{$row['id']}", ['priceComponents' => $seen], $tR);
    $after = json_decode($pdo->query("SELECT price_components FROM ld_mensura_calculation_option_value WHERE LOWER(HEX(id))='{$row['id']}'")->fetchColumn(), true);
    check('VK-Änderung ohne Recht gespeichert, EK erhalten', $s === 204 && $after[0]['sale'] == $seen[0]['sale'] && $after[0]['purchase'] == $before[0]['purchase'],
        "status $s, vorher " . json_encode($before[0]) . ' nachher ' . json_encode($after[0]));
    $pdo->prepare("UPDATE ld_mensura_calculation_option_value SET price_components = ? WHERE LOWER(HEX(id)) = ?")->execute([$row['price_components'], $row['id']]);

    // Testrechner
    $calc = $pdo->query("SELECT LOWER(HEX(calculation_id)) FROM ld_mensura_calculation_material WHERE LOWER(HEX(material_id))='$alu' LIMIT 1")->fetchColumn();
    $cfg = ['calculationId' => $calc, 'configuration' => ['materialId' => $alu, 'format' => ['width' => 1000, 'height' => 500], 'perMotif' => 10]];
    [$s, $d] = req('POST', "$base/_action/ld-mensura/test-calculate", $cfg, $tR);
    check('Testrechner ohne Recht: VK, aber kein EK', $s === 200 && $d['result']['price']['total'] > 0 && $d['purchase'] === null && $d['contributionMargin'] === null, "status $s total " . json_encode($d['result']['price']['total'] ?? null));
    [$s, $d] = req('POST', "$base/_action/ld-mensura/test-calculate", $cfg, $tA);
    check('Testrechner mit Recht: EK und DB', $s === 200 && ($d['purchase']['total'] ?? 0) > 0 && $d['contributionMargin'] !== null, json_encode($d['contributionMargin'] ?? null));

    // Bestell-Schnappschuss
    [$s, $d] = req('POST', "$base/search/ld-mensura-order-line-item", ['limit' => 1], $tR);
    $snap = $d['data'][0]['attributes'] ?? $d['data'][0] ?? null;
    check('Schnappschuss ohne Recht: kein EK', $s === 200 && $snap !== null && $snap['purchaseTotal'] === null && !isset($snap['breakdown']['purchase']) && $snap['saleTotal'] > 0, "status $s");
    [$s, $d] = req('POST', "$base/search/ld-mensura-order-line-item", ['limit' => 1], $tA);
    $snap = $d['data'][0]['attributes'] ?? $d['data'][0] ?? null;
    check('Schnappschuss mit Recht: EK da', $s === 200 && $snap['purchaseTotal'] > 0 && isset($snap['breakdown']['purchase']), "status $s");
} finally {
    foreach ($created as [$roleId, $integrationId]) {
        $pdo->prepare('DELETE FROM integration_role WHERE integration_id = ?')->execute([$integrationId]);
        $pdo->prepare('DELETE FROM integration WHERE id = ?')->execute([$integrationId]);
        $pdo->prepare('DELETE FROM acl_role WHERE id = ?')->execute([$roleId]);
    }
    echo 'Test-Integrationen entfernt: ' . $pdo->query("SELECT COUNT(*) FROM integration WHERE label LIKE 'mensura-ek-test%'")->fetchColumn() . " übrig\n";
}
echo $fail === 0 ? "✓ EK-Schutz bestanden\n" : "✗ $fail Fehler\n";
exit($fail === 0 ? 0 : 1);
