#!/bin/bash
# Gibt das Ausgabeverzeichnis für Engine-Antworten aus (build/contract bzw. $MENSURA_BUILD).
# Ist es nicht beschreibbar (z. B. Container als www-data, Ordner gehört dem Host-Benutzer), wird auf /tmp ausgewichen.
# Wird von phpunit.sh und check-all.sh mit `source` eingebunden und setzt MENSURA_BUILD.
MENSURA_BUILD="${MENSURA_BUILD:-build/contract}"
if ! { mkdir -p "$MENSURA_BUILD" 2> /dev/null && touch "$MENSURA_BUILD/.writable" 2> /dev/null && rm -f "$MENSURA_BUILD/.writable"; }; then
    MENSURA_BUILD="${TMPDIR:-/tmp}/ld-mensura-build/contract"
    mkdir -p "$MENSURA_BUILD"
fi
export MENSURA_BUILD
