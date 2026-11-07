# Entwicklung: Backend und Frontend getrennt, ein Repo

Mensura bleibt **ein Plugin und ein Repository**. Darin gibt es zwei Arbeitsbereiche, die nebeneinander entwickelt werden
können, z. B. in zwei Claude-Sitzungen oder von zwei Personen. Verbunden sind sie nur über den **Vertrag** in
`docs/contract/`.

## Wer ist wofür zuständig?

| Bereich | Ordner | Inhalt |
|---|---|---|
| **Backend** | `src/Core/`, `src/Service/`, `src/Migration/`, `src/Extension/`, `src/Controller/`, `src/Resources/config/`, `src/Resources/app/administration/`, `tests/Backend/` | Datenmodell, Preis-Engine, Store-API, Warenkorb, Administration |
| **Frontend** | `src/Resources/app/storefront/`, `src/Resources/views/storefront/`, `tests/Frontend/` | Konfigurator „kompakt“ und „geführt“ im Shop (Twig + JS-Plugin), später Produktberater-Anbindung |
| **Vertrag** (gemeinsam) | `docs/contract/` | Store-API-Beschreibung, Schemas, Beispielfälle mit Sollwerten |
| gemeinsam | `docs/`, `README.md`, `CLAUDE.md`, `composer.json`, `.github/` | Doku, Version, Prüfungen |

Jeder Bereich ändert nur seine eigenen Ordner. Braucht das Frontend ein neues Feld, wird es zuerst im Vertrag ergänzt
(siehe unten), dann setzt das Backend es um. Bis dahin arbeitet das Frontend gegen den Beispielfall.

## Branches

- `main`: immer lauffähig und im Shop getestet. Nur hierher wird zusammengeführt.
- `contract/<thema>`: Änderung am Vertrag (Schema + Beispielfall). Geht **zuerst** nach `main`.
- `backend/<thema>` bzw. `frontend/<thema>`: Arbeit in einem Bereich.
- **Abgleich:** Jeder Arbeits-Branch holt sich regelmäßig den aktuellen Stand von `main` (mindestens vor dem Zusammenführen).
  So hat das Frontend neue Backend-Funktionen und Vertragsänderungen und umgekehrt. Zusammengeführt wird nur, wenn die
  GitHub-Prüfung grün ist.
- Kein Force-Push, keine umgeschriebene Historie (siehe CLAUDE.md).

## Automatischer Abgleich (GitHub-Prüfung)

`.github/workflows/ci.yml` läuft bei jedem Push und Pull-Request:

| Prüfung | Was | ab wann |
|---|---|---|
| **Vertrag** | Schemas gültig; jeder Beispielfall passt zu seinem Schema; kein Einkaufspreis in Vertrag oder Fixtures; Sollpreise in sich stimmig (Gesamt = je Stück × Menge, außer bei Mindestpreis) | jetzt |
| **Backend** | PHP-Syntax aller Dateien; Snippets de-DE/en-GB vollständig | jetzt |
| **Backend gegen Vertrag** | Preis-Engine rechnet alle Beispielfälle aus `fixtures/calculate/` (und baut `fixtures/configurator/`) und muss die Sollwerte exakt treffen (PHPUnit, `tests/Backend/`, Demo-Daten aus `src/Service/Demo/DemoCatalog.php`); die Antworten landen in `build/contract/` und werden gegen die Schemas geprüft | jetzt (v0.4.0) |
| **Frontend gegen Vertrag** | Konfigurator-JS wird mit `fixtures/configurator/*.json` und `fixtures/calculate/*.expected.json` getestet (ohne Shop) | mit dem Konfigurator (Schritt 6) |

Damit fällt sofort auf, wenn eine Seite den Vertrag verlässt: Das Backend rechnet anders als im Beispielfall, oder das
Frontend erwartet ein Feld, das es nicht gibt.

Lokal dieselben Prüfungen ausführen:

```bash
docker exec shopware bash -c "cd /var/www/html/custom/plugins/LdMensura && bash .github/scripts/check-all.sh"
```

Nur die Backend-Tests (PHPUnit als Phar in `.cache/`, ohne Shopware-Kernel):

```bash
docker exec shopware bash -c "cd /var/www/html/custom/plugins/LdMensura && bash .github/scripts/phpunit.sh"
```

Im laufenden Shop: `bin/console mensura:demo:seed` legt das Demo-Produkt aus den Beispielfällen an
(Produkt-ID `0194e1a0000070008000000000000a01`); danach liefert die Store-API dieselben Sollwerte wie die Fixtures.

## Beispielfall anlegen (Ablauf)

1. Branch `contract/<thema>` anlegen.
2. Schema in `docs/contract/schema/` erweitern, nur optionale Felder ergänzen.
3. Beispielfall `fixtures/calculate/<name>.request.json` + `<name>.expected.json` anlegen, Sollwerte mit Herleitung im Commit.
4. Prüfung lokal laufen lassen, nach `main` bringen.
5. Backend und Frontend setzen es in ihren Branches um und holen sich vorher den Stand von `main`.

## Frontend: Konfigurator im Shop (kompakt/geführt)

**Aufbau** (`src/Resources/app/storefront/src/ld-mensura-configurator/`, ES-Module ohne Abhängigkeiten):

| Datei | Aufgabe |
|---|---|
| `state.js` | gemeinsames Zustandsmodell beider Varianten (rein funktional, `apply(state, action)`): aktive Gruppen aus Optionssets, Materialwechsel nach KONZEPT 3.7 (Format/Menge bleiben, gemeinsame Gruppen behalten den Wert, wegfallende werden gemerkt und bei Rückkehr wiederhergestellt, Grenzen werden nie still geändert), Schritte der geführten Darstellung, URL-Hash `#lm?…` |
| `api-client.js` | `StoreApiClient` (Store-API laut `docs/contract/store-api.yaml`, Header `sw-access-key`/`sw-context-token`) und `FixtureClient` (beantwortet Anfragen aus `docs/contract/fixtures`; Preise nur für Beispielfälle, sonst Hinweis `fixture.no_price`) |
| `controller.js` | hält den Zustand, rechnet entprellt über `calculate` (veraltete Antworten werden verworfen, geführt mit `matrix: true`) |
| `render.js` | HTML beider Varianten + Preis, „Ihr Produkt“, Vorschau mit Schnittmarken; Fehler/Hinweise nach `code`, `fix` als Schaltfläche |
| `mount.js` / `configurator.plugin.js` | DOM-Anbindung bzw. Shopware-JS-Plugin `LdMensuraConfigurator` (PluginManager, `main.js`) |

- **Preise rechnet nur das Backend.** Das Frontend prüft lokal nur Formatgrenzen (sofortige Markierung) und sichtbare Gruppen;
  maßgeblich ist immer das frische Ergebnis von `calculate`. Ohne gültiges, frisches Ergebnis kein „In den Warenkorb“.
- **Twig:** `views/storefront/component/buy-widget/buy-widget.html.twig` ersetzt Preis und Kaufformular durch den Konfigurator,
  wenn `product.extensions.ldMensura` gesetzt ist (Backend muss die Assoziation auf der Produktseite laden).
  `component/ld-mensura/configurator.html.twig` übergibt Store-API-Zugang und Texte (`lmTextKeys` = Snippets `ldMensura.configurator.*`).
- **Warenkorb:** `component/line-item/type/product.html.twig` zeigt bei `payload.ldMensura` die Konfiguration, den Liefertermin
  (`ldDeliveryDate`) und „Konfiguration ändern“ (Link mit demselben Hash wie der Konfigurator); `element/quantity.html.twig`
  sperrt die Menge (sie kommt aus Motive × Stück). Snippet `checkout.ld-mensura-configuration-invalid` für entfernte Positionen.
- **Schritte:** kompakt 1 Material · 2 Format & Auflage · 3 Druck & Weiterverarbeitung; geführt Material · Format & Stückzahl ·
  je Optionsgruppe · Lieferzeit. Die Stückzahl steht beim Format, die Gesamtfläche (B × H × Stück) wird dort angezeigt (vom Backend,
  sobald das Ergebnis frisch ist). `steps[].key` „quantity“ aus den Konfigurator-Daten = Lieferzeit-Schritt.
- **Festformat:** Hat ein Wert der Format-Gruppe eine `id`, sendet das Frontend `format.formatValueId` (Vertrag `contract/format-value-id`).
- **Gestaltung = Shopware-Theme:** Bootstrap-Klassen (btn, form-select, alert, nav-tabs, table, badge, card) und `--bs-*`-Variablen,
  `scss/base.scss` nur für Layout. Das Admin-Designsystem gilt im Storefront nicht. Breite per Container-Query: in der Kaufbox
  einspaltig, auf voller Breite zweispaltig.
- **Variante:** `layout` aus den Konfigurator-Daten; mit `allowLayoutSwitch` kann der Kunde umschalten (gemerkt im Browser,
  `localStorage`), die Auswahl bleibt erhalten. Konfiguration steht im URL-Hash (teilen/wiederherstellen).

**Prüfen ohne Shop**

```bash
docker exec shopware bash -c "cd /var/www/html/custom/plugins/LdMensura && node --test tests/Frontend/*.test.mjs"
```

Statische Testseite (Fixtures; mit `?api=http://localhost/store-api&key=<sw-access-key>` gegen die echte Store-API):

```bash
docker exec -d shopware bash -c "cd /var/www/html/custom/plugins/LdMensura && node tests/Frontend/static/serve.mjs 8888"
```

Dann http://localhost:8888/ öffnen (Port 8888 ist im Dockware-Container freigegeben).

**Im Shop:** `docker exec shopware bash -c "cd /var/www/html && bin/build-storefront.sh"` (schreibt `src/Resources/app/storefront/dist`,
der Ordner muss für `www-data` beschreibbar sein).
