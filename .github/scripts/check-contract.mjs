// Inhaltliche Prüfungen des Vertrags, die ein JSON-Schema nicht ausdrücken kann.
// Aufruf: node .github/scripts/check-contract.mjs  (aus dem Plugin-Verzeichnis)
import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';

const root = 'docs/contract';
let failures = 0;
const fail = (file, msg) => { failures++; console.error(`✗ ${file}: ${msg}`); };

// 1. Keine Einkaufspreise in Vertrag und Beispielfällen
const forbidden = /"(purchase\w*|pricePurchase|ek|einkauf\w*|margin\w*|contributionMargin)"\s*:/i;
const walk = (dir) => readdirSync(dir, { withFileTypes: true }).flatMap((e) => (e.isDirectory() ? walk(join(dir, e.name)) : [join(dir, e.name)]));
for (const file of walk(root).filter((f) => /\.(json|ya?ml)$/.test(f))) {
  const text = readFileSync(file, 'utf8');
  if (forbidden.test(text)) fail(file, 'enthält ein Einkaufspreis-/Margenfeld');
}

// 1b. Kein Logistikanteil im Vertrag (KONZEPT 3d.4): er ist für den Kunden unsichtbar, nur der Gesamtpreis zählt
for (const file of walk(root).filter((f) => /\.(json|ya?ml|md)$/.test(f))) {
  if (/logisti/i.test(readFileSync(file, 'utf8')) || /logisti/i.test(file)) fail(file, 'erwähnt den Logistikanteil (darf nie in den Vertrag)');
}

// 2. Beispielfälle: Paare vollständig, Sollpreise in sich stimmig
const calcDir = join(root, 'fixtures', 'calculate');
const files = readdirSync(calcDir);
const names = new Set(files.map((f) => f.replace(/\.(request|expected)\.json$/, '')));
for (const name of names) {
  const reqFile = join(calcDir, `${name}.request.json`);
  const expFile = join(calcDir, `${name}.expected.json`);
  if (!files.includes(`${name}.request.json`) || !files.includes(`${name}.expected.json`)) { fail(name, 'request/expected unvollständig'); continue; }
  const req = JSON.parse(readFileSync(reqFile, 'utf8'));
  const exp = JSON.parse(readFileSync(expFile, 'utf8'));
  if (exp.configuration.productId !== req.productId) fail(expFile, 'productId weicht von der Anfrage ab');
  if (exp.valid && !exp.price) fail(expFile, 'valid=true, aber kein price');
  if (!exp.valid && exp.price) fail(expFile, 'valid=false darf keinen price haben');
  if (!exp.valid && exp.errors.length === 0) fail(expFile, 'valid=false ohne errors');
  if (exp.price) {
    const p = exp.price;
    const q = req.motifs * req.perMotif;
    const area = (req.format.width * req.format.height) / 1e6 * q;
    if (p.quantity !== q) fail(expFile, `quantity ${p.quantity} ≠ Motive × Stück ${q}`);
    if (Math.abs(p.area - area) > 0.0005) fail(expFile, `area ${p.area} ≠ ${area}`);
    if (Math.abs(p.perPiece * q - p.total) > 0.011) fail(expFile, `perPiece × quantity ≠ total`);
    if (Math.abs(p.total / area - p.perSqm) > 0.011) fail(expFile, `total / area ≠ perSqm`);
    if (p.areas) {
      const a = p.areas;
      if (Math.abs(a.total - p.area) > 0.0005) fail(expFile, `areas.total ${a.total} ≠ area ${p.area}`);
      if (Math.abs(a.piece * q - a.total) > 0.0005) fail(expFile, 'areas.piece × quantity ≠ areas.total');
      if (a.billed < a.total - 0.0005) fail(expFile, 'areas.billed kleiner als die Gesamtfläche');
      if (a.minArea != null && a.billed < a.minArea - 0.0005) fail(expFile, 'areas.billed kleiner als minArea');
    }
    const hasMinHint = exp.hints.some((h) => h.code === 'price.minimum_applied');
    if (p.minimumApplied !== hasMinHint) fail(expFile, 'minimumApplied und Hinweis price.minimum_applied passen nicht zusammen');
  }
}

if (failures) { console.error(`\n${failures} Fehler im Vertrag.`); process.exit(1); }
console.log(`✓ Vertrag in Ordnung (${names.size} Beispielfälle).`);
