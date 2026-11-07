# Vertrag Backend ↔ Frontend

Backend und Frontend von Mensura reden **nur** über die hier beschriebene Store-API und das Konfigurationsschema
miteinander. Solange beide Seiten diesen Vertrag erfüllen, können sie unabhängig voneinander entwickelt werden.
Die GitHub-Prüfung (`.github/workflows/ci.yml`) stellt das bei jedem Push sicher.

| Datei | Inhalt |
|---|---|
| `store-api.yaml` | OpenAPI-Beschreibung der Store-API-Routen (ohne Einkaufspreise) |
| `schema/configuration.schema.json` | Konfiguration einer Position, wie sie Frontend → Backend schickt und im Payload `ldMensura` landet |
| `schema/configurator.schema.json` | Antwort „Konfigurator-Daten eines Produkts“ (Materialien, Sets, Gruppen, Werte, Grenzen, Lieferzeiten) |
| `schema/calculation-result.schema.json` | Antwort der Preisberechnung (gültig?, Fehler, Hinweise, Preis, Lieferzeiten, normalisierte Konfiguration) |
| `fixtures/` | Beispielfälle: Anfrage + **Sollergebnis**. Das Backend muss diese Ergebnisse liefern, das Frontend nutzt sie als Testdaten, solange das Backend noch fehlt |

## Regeln

1. **Erst der Vertrag, dann der Code.** Neue oder geänderte Felder werden zuerst hier eingetragen, mit Beispielfall.
   Das passiert auf einem eigenen Branch `contract/…`, der vor Backend und Frontend nach `main` geht.
2. **Nur erweitern, nicht brechen.** Neue Felder sind optional, und nichts wird umbenannt oder entfernt. Ein Bruch erhöht `schema` in der
   Konfiguration bzw. `apiVersion` in den Antworten, und das Backend liest alte Versionen weiter.
3. **Beispielfälle sind Sollwerte.** Ein Fixture enthält `request` und `expected`. Preise stehen netto mit zwei Nachkommastellen
   und stammen aus echten Kalkulationen (Aluverbund = Comdatek-VK). Ändert sich ein Sollwert, steht im Commit, warum.
4. **Keine Einkaufspreise** in irgendeiner Store-API-Antwort und keinem Fixture.
5. Fehler und Hinweise haben einen festen **`code`** (z. B. `format.height_max`), damit das Frontend sie unabhängig vom
   Text behandeln kann. Den Text liefert das Backend übersetzt mit.
