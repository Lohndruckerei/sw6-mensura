// Prüft, dass de-DE und en-GB dieselben Snippet-Schlüssel haben (Administration und später Storefront).
import { existsSync, readFileSync } from 'node:fs';

const pairs = [
  ['src/Resources/app/administration/src/snippet/de-DE.json', 'src/Resources/app/administration/src/snippet/en-GB.json'],
  ['src/Resources/snippet/storefront.de-DE.json', 'src/Resources/snippet/storefront.en-GB.json'],
];
const keys = (obj, prefix = '') => Object.entries(obj).flatMap(([k, v]) => (v && typeof v === 'object' ? keys(v, `${prefix}${k}.`) : [`${prefix}${k}`]));
let failures = 0;
for (const [de, en] of pairs) {
  if (!existsSync(de) && !existsSync(en)) continue;
  if (!existsSync(de) || !existsSync(en)) { console.error(`✗ Paar unvollständig: ${de} / ${en}`); failures++; continue; }
  const a = new Set(keys(JSON.parse(readFileSync(de, 'utf8'))));
  const b = new Set(keys(JSON.parse(readFileSync(en, 'utf8'))));
  for (const k of a) if (!b.has(k)) { console.error(`✗ fehlt in ${en}: ${k}`); failures++; }
  for (const k of b) if (!a.has(k)) { console.error(`✗ fehlt in ${de}: ${k}`); failures++; }
}
if (failures) process.exit(1);
console.log('✓ Snippets de-DE/en-GB vollständig.');
