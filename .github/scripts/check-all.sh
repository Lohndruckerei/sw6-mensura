#!/bin/bash
# Alle Prüfungen lokal (gleiche Schritte wie .github/workflows/ci.yml). Aus dem Plugin-Verzeichnis aufrufen.
set -euo pipefail
S=docs/contract/schema
F=docs/contract/fixtures
AJV="npx --yes ajv-cli@5.0.0"
OPTS="--spec=draft2020 --strict=false"

echo "== Vertrag: Schemas"
$AJV compile $OPTS -s $S/configuration.schema.json
$AJV compile $OPTS -s $S/calculation-result.schema.json -r $S/configuration.schema.json
$AJV compile $OPTS -s $S/configurator.schema.json -r $S/configuration.schema.json
echo "== Vertrag: Beispielfälle"
$AJV validate $OPTS -s $S/configuration.schema.json -d "$F/calculate/*.request.json"
$AJV validate $OPTS -s $S/calculation-result.schema.json -r $S/configuration.schema.json -d "$F/calculate/*.expected.json"
$AJV validate $OPTS -s $S/configurator.schema.json -r $S/configuration.schema.json -d "$F/configurator/*.json"
node .github/scripts/check-contract.mjs

echo "== Backend: PHP-Syntax"
find src -name '*.php' -print0 | xargs -0 -n1 php -l | grep -v '^No syntax errors' || true
if find src -name '*.php' -print0 | xargs -0 -n1 php -l 2>&1 | grep -q 'Parse error'; then echo "PHP-Syntaxfehler"; exit 1; fi
echo "== Snippets"
node .github/scripts/check-snippets.mjs

echo "== Backend gegen Vertrag: Preis-Engine trifft alle Beispielfälle (PHPUnit)"
bash .github/scripts/phpunit.sh
echo "== Backend gegen Vertrag: Antworten der Engine passen zu den Schemas"
$AJV validate $OPTS -s $S/calculation-result.schema.json -r $S/configuration.schema.json -d "build/contract/calculate/*.json"
$AJV validate $OPTS -s $S/configurator.schema.json -r $S/configuration.schema.json -d "build/contract/configurator/*.json"
echo "== Frontend gegen Vertrag: Konfigurator-JS mit fixtures (Node, ohne Shop)"
node --test tests/Frontend/*.test.mjs
echo "✓ Alle Prüfungen bestanden."
