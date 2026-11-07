#!/bin/bash
# Backend-Tests (Preis-Engine gegen docs/contract) ohne Shopware-Kernel. Aus dem Plugin-Verzeichnis aufrufen.
# Nutzt `phpunit` aus dem PATH (GitHub-Prüfung) oder lädt das Phar einmalig nach .cache/.
set -euo pipefail
if command -v phpunit > /dev/null 2>&1; then
    PHPUNIT=phpunit
else
    mkdir -p .cache
    if [ ! -f .cache/phpunit-11.phar ]; then
        curl -fsSL -o .cache/phpunit-11.phar https://phar.phpunit.de/phpunit-11.phar
    fi
    PHPUNIT="php .cache/phpunit-11.phar"
fi
rm -rf build/contract
$PHPUNIT -c tests/Backend/phpunit.xml.dist "$@"
