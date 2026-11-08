<?php
/*
 * Comdatek-Demo (mensura:demo:comdatek) über die Store-API prüfen – im laufenden Shop, nicht Teil von check-all:
 *   docker exec -i -e KEY=<sw-access-key Storefront> -e LANG_DE=<Sprach-ID de-DE> shopware php < tests/Backend/Shop/comdatek-demo.php
 * Jedes Produkt: Konfigurator + Berechnung mit Vorbelegung; dazu Rechenbeispiele mit Sollwerten (VK = EK ÷ 0,75 + Logistikpauschale).
 */
$key = getenv('KEY');
$lang = getenv('LANG_DE');
$fail = 0;
function call(string $method, string $path, ?array $body = null): array
{
    global $key, $lang;
    $ctx = stream_context_create(['http' => ['method' => $method, 'ignore_errors' => true,
        'header' => "sw-access-key: $key\r\nsw-language-id: $lang\r\nContent-Type: application/json\r\nAccept: application/json\r\n",
        'content' => $body === null ? '' : json_encode($body)]]);
    $raw = file_get_contents('http://localhost/store-api' . $path, false, $ctx);
    preg_match('/\d{3}/', $http_response_header[0], $m);
    return [(int) $m[0], json_decode($raw, true)];
}
$id = fn (string $k) => md5('ld-mensura-comdatek:' . $k);
$products = ['aluverbund', 'kaschiert', 'acryl', 'hartschaum', 'pvc-ps', 'hohlkammer', 'displaykarton', 'reboard', 'stadur', 'plattendirektdruck', 'pvc-banner', 'textil-greyback', 'pvc-linoleum', 'textilbanner'];
foreach ($products as $p) {
    [$c, $cfg] = call('GET', '/ld-mensura/product/' . $id("product.$p") . '/configurator');
    if ($c !== 200) { echo "FEHL $p configurator $c\n"; $fail++; continue; }
    $groups = implode(',', array_map(fn ($g) => $g['technicalName'] . '(' . count($g['values']) . ')', $cfg['groups']));
    [$c2, $res] = call('POST', '/ld-mensura/calculate', ['configuration' => $cfg['defaults']]);
    $ok = $c2 === 200 && ($res['valid'] ?? false) && ($res['price']['total'] ?? 0) > 0;
    if (!$ok) { $fail++; }
    printf("%s %-20s %s mat=%d layout=%s total=%s | %s\n", $ok ? 'OK  ' : 'FEHL', $p, $cfg['layout'], count($cfg['materials']), $cfg['layout'], $res['price']['total'] ?? json_encode($res['errors'] ?? $res), $groups);
}
$cases = [
    'Alu 3 mm 1000×500, 10 Stk, Standard' => [['productId' => $id('product.aluverbund'), 'materialId' => $id('material.alu-3mm'), 'format' => ['width' => 1000, 'height' => 500], 'motifs' => 1, 'perMotif' => 10], 181.60],
    'Alu 3 mm 1000×500, 10 Stk, 4/4 + Kontur + Bohrung + AG' => [['productId' => $id('product.aluverbund'), 'materialId' => $id('material.alu-3mm'), 'format' => ['width' => 1000, 'height' => 500], 'motifs' => 1, 'perMotif' => 10,
        'options' => ['farbigkeit' => '4-4', 'schnitt' => 'kontur', 'bohrung' => 'ja', 'veredelung' => 'ag-1']], null],
    'Frontlit 2000×1000, Saum+Ösen' => [['productId' => $id('product.pvc-banner'), 'materialId' => $id('material.banner-frontlit-510'), 'format' => ['width' => 2000, 'height' => 1000], 'motifs' => 1, 'perMotif' => 1, 'options' => ['konfektion' => 'saum-oesen']], 24.56],
    'Frontlit 2000×1000 × 10 (20 m² → Staffel ab 10)' => [['productId' => $id('product.pvc-banner'), 'materialId' => $id('material.banner-frontlit-510'), 'format' => ['width' => 2000, 'height' => 1000], 'motifs' => 1, 'perMotif' => 10, 'options' => ['konfektion' => 'saum-oesen']], 231.00],
    'Textil Auflicht 2000×1000, Flauschband' => [['productId' => $id('product.textilbanner'), 'materialId' => $id('material.textil-auflicht-210'), 'format' => ['width' => 2000, 'height' => 1000], 'motifs' => 1, 'perMotif' => 1, 'options' => ['konfektion' => 'flauschband']], 51.24],
    'Blockout 500×500, 4/4 (Mindestpreis)' => [['productId' => $id('product.pvc-banner'), 'materialId' => $id('material.banner-blockout-610'), 'format' => ['width' => 500, 'height' => 500], 'motifs' => 1, 'perMotif' => 1, 'options' => ['farbigkeit' => '4-4']], 11.87],
];
foreach ($cases as $label => [$config, $expected]) {
    [$c, $res] = call('POST', '/ld-mensura/calculate', ['configuration' => ['schema' => 1] + $config]);
    $total = $res['price']['total'] ?? null;
    $ok = $c === 200 && ($expected === null ? $total > 0 : abs($total - $expected) < 0.005);
    if (!$ok) { $fail++; }
    $opts = json_encode($res['configuration']['options'] ?? null);
    echo ($ok ? 'OK   ' : 'FEHL ') . "$label: $total" . ($expected !== null ? " (soll $expected)" : '') . "  Optionen $opts" . (isset($res['hints']) && $res['hints'] ? '  Hinweise: ' . implode(' / ', array_column($res['hints'], 'code')) : '') . "\n";
}
echo $fail === 0 ? "✓ Comdatek-Demo rechnet\n" : "✗ $fail Fehler\n";
exit($fail ? 1 : 0);
