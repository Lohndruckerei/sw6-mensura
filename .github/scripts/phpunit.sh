#!/bin/bash
# Backend-Tests (Preis-Engine gegen docs/contract) ohne Shopware-Kernel. Aus dem Plugin-Verzeichnis aufrufen.
# Nutzt `phpunit` aus dem PATH (GitHub-Prüfung) oder lädt das Phar einmalig nach .cache/ (bzw. /tmp, falls nicht beschreibbar).
# Die Antworten der Engine landen in $MENSURA_BUILD (Standard build/contract, siehe build-dir.sh).
set -euo pipefail
source "$(dirname "$0")/build-dir.sh"
if command -v phpunit > /dev/null 2>&1; then
    PHPUNIT=phpunit
else
    CACHE=.cache
    mkdir -p "$CACHE" 2> /dev/null && [ -w "$CACHE" ] || CACHE="${TMPDIR:-/tmp}/ld-mensura-cache"
    mkdir -p "$CACHE"
    if [ ! -f "$CACHE/phpunit-11.phar" ]; then
        curl -fsSL -o "$CACHE/phpunit-11.phar" https://phar.phpunit.de/phpunit-11.phar
    fi
    PHPUNIT="php $CACHE/phpunit-11.phar"
fi
rm -rf "${MENSURA_BUILD:?}"/calculate "${MENSURA_BUILD:?}"/configurator
status=0
$PHPUNIT -c tests/Backend/phpunit.xml.dist --do-not-cache-result "$@" || status=$?
# für andere Benutzer (Host ↔ www-data im Container) wieder beschreibbar machen
chmod -R a+rwX "$MENSURA_BUILD" 2> /dev/null || true
exit $status
