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

## Storefront-Routen (Aufruf aus dem Shop)

Shopware 6.7 gibt den Kontext-Token nicht mehr in HTML/JS heraus (`context.token` in Twig wirft einen Fehler). Damit Preise
für die Kundengruppe der Sitzung gelten und „In den Warenkorb“ im Warenkorb der Sitzung landet, ruft der Konfigurator im
Shop Storefront-Routen auf. Sie laufen im Sitzungskontext und haben **dieselben Bodies und Antworten** wie die Store-API-Routen:

| Storefront-Route (relativ zur Storefront-URL inkl. Sprachpräfix) | entspricht Store-API |
|---|---|
| `GET /ld-mensura/configurator/{productId}` (`frontend.ld-mensura.configurator`) | `GET /store-api/ld-mensura/product/{productId}/configurator` |
| `POST /ld-mensura/calculate` (`frontend.ld-mensura.calculate`) | `POST /store-api/ld-mensura/calculate` |
| `POST /ld-mensura/cart/add` (`frontend.ld-mensura.cart.add`) | `POST /store-api/ld-mensura/cart/add` |

## Vorauswahl (Wunsch des Benutzers, 08.11.2026)

Je Optionsgruppe lässt sich im Backend einstellen, ob ein Wert vorausgewählt wird – in der Kalkulation, im Produktprofil
überschreibbar (auch je Material). Standard ist „vorauswählen“ (bisheriges Verhalten).

- Ohne Vorauswahl fehlt die Gruppe in `defaults.options` (bzw. `materials[].defaults`); `groups[].preselect` ist `false`.
- `calculate` ohne Wert für eine solche **Pflichtgruppe**: `valid: false`, Fehler `option.choose` mit `field: options.<gruppe>`,
  **kein Preis** und keine Preismatrix, `cart/add` → 400. Freiwillige Gruppen (`required: false`) dürfen leer bleiben.
- Folge-Sets einer noch nicht gewählten Gruppe erscheinen erst nach der Wahl.
- Feste und ausgeblendete Gruppen (Profilregel `fixed`/`hidden`) haben immer ihren Wert.
- `materials[].defaults` („immer den günstigsten Fall vorauswählen“) – Auslegung bestätigt vom Benutzer am 08.11.2026:
  günstigster erlaubter Wert **ohne Abschläge** (z. B. „0/0 unbedruckt“ wird nie vorausgewählt), bei gleichem Aufpreis
  der Standardwert der Kalkulation, sonst die Reihenfolge.

Die Store-API selbst bleibt für Headless/Apps (mit `sw-context-token` des Clients) und für die Testseite.

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
