# Mensura – Schnittstellen zur Plugin-Familie

Stand: 07.11.2026. Mensura berechnet und speichert, **was** der Kunde bestellt hat und **was es kostet**.
Andere Plugins entscheiden, **wer** es fertigt, und verwalten den Auftrag. Es gibt keine harten
Abhängigkeiten: Mensura läuft ohne LieferLotse, und LieferLotse ohne Mensura.

```
Kunde ─► Mensura-Konfigurator ─► Warenkorb (Produktposition + Konfiguration) ─► Bestellung
                                                                                   │
              ┌────────────────────────────────────────────────────────────────────┤
              ▼                                                                    ▼
   Auftragsverteilung (künftig)                              Auftragsverwaltung, Versand,
   wählt Lieferant je Position ─► LieferLotse ─► Datenschnittstelle   Rechnung, Buchhaltung (künftig)
   (Vorschlag von Mensura)         (E-Mail, FTP, SFTP, API …)          lesen Konfiguration + EK/VK
```

## 1. Bestellposition

- Konfigurierte Artikel bleiben **normale Produktpositionen** (`type = product`, mit `productId`). Nur so
  findet der `SupplierResolver` des LieferLotse die Lieferanten des Produkts. Den Preis setzt Mensura
  serverseitig über einen eigenen CartProcessor.
- Die Konfiguration steht im Payload der Position unter dem Schlüssel `ldMensura` (öffentlich, ohne Preise):

  ```json
  {
    "ldMensura": {
      "schema": 1,
      "calculation": "aluverbund",
      "material":    { "id": "…", "key": "alu-3mm", "name": "Aluverbundplatte 3 mm" },
      "format":      { "width": 1000, "height": 500, "rotated": false, "unit": "mm" },
      "area":        { "piece": 0.5, "total": 5.0, "billed": 5.0 },
      "motifs": 2, "perMotif": 5,
      "options": [ { "group": "farbigkeit", "groupName": "Farbigkeit", "value": "4-4", "valueName": "4/4 beidseitig bedruckt" } ],
      "sku": "ALU3-1000x500-44-KON"
    },
    "ldDeliveryDate": "2026-11-20"
  }
  ```
- Umgesetzt in v0.4.0 (`Service/Cart`): zusätzlich `delivery` (technischer Name der Lieferzeit), `reference` und
  `configuration` (normalisierte Konfiguration laut `docs/contract/schema/configuration.schema.json`, daraus rechnet der
  `MensuraCartProcessor` bei jeder Warenkorbberechnung neu). Menge der Position = Gesamtmenge der Konfiguration; das
  Shopware-Produkt braucht eine ausreichende Höchstbestellmenge (`maxPurchase`), sonst kürzt Shopware vorher.
- `ldDeliveryDate` setzt Mensura, wenn der Kunde eine Lieferzeit gewählt hat. Der `DeliveryDateResolver`
  des LieferLotse übernimmt den Termin dann in die Lieferantenbestellung.
- **Positionen, die mit Mensura berechnet wurden, gehen nur zusammen mit ihrer Konfiguration an einen Lieferanten.**

## 2. Rechenweg-Schnappschuss (intern)

Tabelle `ld_mensura_order_line_item`, 1:1 zur Bestellposition, als Extension `orderLineItem.extensions.ldMensura`:
Konfiguration, kompletter Rechenweg, **EK gesamt, VK gesamt**, vorgeschlagener Lieferant, Schema-Version.
Die Felder sind nicht `ApiAware`, sind also **nur über die Admin-API** erreichbar und nie in der Store-API.
Einkaufspreise erscheinen damit nie im Kundenkonto. Spätere Plugins für Rechnung und Buchhaltung lesen
EK/VK und Deckungsbeitrag von hier. Preisänderungen in den Stammdaten ändern bestehende Bestellungen nicht.

Umgesetzt in v0.4.0 (`Service/Cart/OrderSnapshotSubscriber`): Beim Umwandeln des Warenkorbs in die Bestellung
(`CartConvertedEvent`) rechnet die Engine jede konfigurierte Position ein letztes Mal und speichert den Schnappschuss
zusammen mit der Bestellung. `breakdown` enthält `sale` und `purchase` (Rechenweg Zeile für Zeile), `contributionMargin`,
`orderedNetTotal` (vom Kunden bestätigter Positionspreis netto, zum Abgleich) und `calculatedAt`. Shopware selbst friert
an der Bestellposition Preis, Menge, Bezeichnung und Payload `ldMensura` (Namen von Material und Optionen) ein. Im Shop
geprüft: Preisänderung am Material nach der Bestellung ändert weder Bestellposition noch Schnappschuss.

## 3. Lieferant und Lieferantencodes

- **Lieferantenvorschlag:** Kalkulation und Material haben ein optionales `supplierId`, also eine Lieferanten-ID
  aus dem LieferLotse, ohne Fremdschlüssel. So kann z. B. Acryl von einem anderen Lieferanten kommen als Aluverbund,
  obwohl beides ein Shopware-Produkt ist. Mensura legt den Vorschlag im Schnappschuss ab
  (Material vor Kalkulation). **Entscheiden** tut die Auftragsverteilung; ohne Vorschlag gilt die
  Produktzuordnung des LieferLotse.
- **Lieferantencodes:** Kalkulation, Material und Optionswert haben `supplierCodes`
  (`[{ "supplierId": "…", "code": "124" }]`). Daraus setzt Mensura die Lieferanten-Artikelnummer der Position
  zusammen, z. B. Comdatek `124-29.1-57-3-134` (Produkt-Material.Optionen). Das Muster je Lieferant wird später an der Kalkulation
  gepflegt.

## 4. Andocken an den LieferLotse

Mensura registriert sich, wenn LieferLotse installiert ist, für `SupplierOrderBuiltEvent` und ergänzt je Position:

| Feld der Lieferantenposition | Inhalt |
|---|---|
| `options[]` (Text, schon vorhanden) | „Material: Aluverbundplatte 3 mm“, „Format: 1000 × 500 mm“, „Motive: 2 × 5 Stk“, „Farbigkeit: 4/4 …“ |
| `supplierProductNumber` | aus den Lieferantencodes zusammengesetzt, falls vorhanden; sonst bleibt die Zuordnung aus dem LieferLotse |
| **`attributes`** (neu im LieferLotse) | strukturierte Konfiguration (Payload `ldMensura` ohne Preise) für XML/JSON/API, z. B. `<Attributes><Width>1000</Width>…` |

**Nötige Erweiterung im LieferLotse** (kleiner, eigener Schritt dort): `SupplierOrderItem` bekommt ein
neutrales Feld `attributes` (Schlüssel/Wert, verschachtelt erlaubt), `toArray()` gibt es aus, und
die XML-, JSON- und CSV-Formatter schreiben es mit (CSV: flach, z. B. `attr.format.width`). Preise kommen dort
weiterhin nicht hinein. Bis dahin reicht der Text in `options[]`, und E-Mail-Bestellungen funktionieren schon so.

Die Lieferanschrift des Kunden (`shippingAddress`) und der Liefertermin stehen bereits in jeder
Lieferantenbestellung. Damit kann der Lieferant fertigen und direkt an den Kunden versenden.

## 5. Für künftige Plugins

| Plugin (künftig) | nutzt |
|---|---|
| Auftragsverteilung | Lieferantenvorschlag aus `ld_mensura_order_line_item.supplierId`, ruft LieferLotse `build()` und `send()` |
| Druckdaten-Freigabe | Position-ID als Schlüssel; Mensura reserviert `ldMensura.artwork` im Payload (Upload im Konfigurator) |
| Auftragsstatus / Versandmitteilung | Flow-Events des LieferLotse (`lieferlotse.supplier_order.sent/failed/shipping_confirmed`) |
| Rechnung, Buchhaltung | VK aus der Bestellung; EK und Deckungsbeitrag aus `ld_mensura_order_line_item` |
| Produktberater | Merkmale, Anwendungsfälle, Zubehörregeln, Presets, Store-API `catalog` (siehe Abschnitt 6) |

Mensura stellt dafür bereit:
- ✅ (v0.4.0) Store-API `POST /store-api/ld-mensura/calculate`: Live-Preis für das Frontend; `CalculationResult::toSnapshot()`
  liefert den Rechenweg mit EK/VK und Deckungsbeitrag für `ld_mensura_order_line_item` (geschrieben wird er mit dem Warenkorb)
- (mit Warenkorb/Bestellung, Schritt 6) `ConfigurationReader::fromLineItem()`: liest die Konfiguration einer Bestellposition
  typisiert, unabhängig von der Schema-Version
- (mit Warenkorb/Bestellung, Schritt 6) `MensuraPriceCalculatedEvent`: nach jeder Berechnung; andere Plugins können Zu- und
  Abschläge ergänzen, z. B. Kundenrabatte

## 6. Produktberater (künftiges eigenes Plugin)

Vorbild: pepeprint.de → „Produktberater“. Es gibt 9 Fragen, die je nach vorheriger Antwort verzweigen (Küche, Bad, Wohnzimmer).
Jede Antwort gewichtet **Kriterien** wie wasserfest, UV-fest, rückstandslos entfernbar, hitzebeständig, günstig,
einfache Montage oder untergrundtolerant. Materialien haben je Kriterium einen Wert, empfohlen wird das Material mit der höchsten
gewichteten Summe. Einzelne Antworten **erzwingen** ein Material (Gaskochfeld → Glas) oder geben einen **Hinweis** für
den nächsten Schritt (Außenbereich → UV-geschützte Veredelung). Dazu gibt es eine Vergleichstabelle der Materialien, und
„Auswahl übernehmen“ springt in den Konfigurator mit vorgewähltem Material.

**Aufgabenteilung:** Der Berater besitzt Fragen, Verzweigung, Gewichte, Bewertung und Oberfläche (inkl. Video-Erklärungen).
**Mensura liefert die Fachdaten und nimmt das Ergebnis entgegen.** Die Merkmale werden so oder so gebraucht, für Badges,
Vergleichstabellen und Infotexte im Konfigurator. Deshalb stehen sie in Mensura und nicht im Berater.

| Baustein in Mensura | Zweck für den Berater | geplant |
|---|---|---|
| **Merkmal-Katalog** `ld_mensura_feature` (+ Übersetzung): Schlüssel, Art (`bool`, `level` 0–5, `number` + Einheit, `enum`/`multi`), Gruppe (Haltbarkeit, Umgebung, Montage, Pflege, Nachhaltigkeit), Bezeichnung, Hilfetext, Icon | gemeinsame Sprache der Kriterien: `haltbarkeit_jahre`, `einsatz` (innen/außen), `uv`, `wasserfest`, `kratzfest`, `hitzebestaendig_c`, `rueckstandslos_entfernbar`, `brandschutz` (B1), `pvc_frei`, `selbst_zuschneidbar` … | ✅ v0.4.0 (Tabelle; `material.properties` bleibt vorerst bestehen) |
| **Merkmalswerte** `ld_mensura_feature_value`: Merkmal × Material / Optionswert / Produktprofil, Wert als JSON `{"value": …}` | Material- und Optionsbewertung, z. B. Laminat Anti-Graffiti: kratzfest 4, Glaslaminat: Optik 5 | ✅ v0.4.0 |
| **Anwendungsfälle** `ld_mensura_use_case` (+ Übersetzung, `ld_mensura_use_case_assignment`): Schild innen/außen, Fahrzeugbeschriftung, Schaufenster, Fußboden, Fassade, Rückwand Küche/Bad, Messe …; Zuordnung zu Produktprofilen und Materialien mit Eignung (0–5) und optionaler Vorbelegung (`preset`) | Einstieg „Wofür brauchst du es?“, Filter, Empfehlungen | ✅ v0.4.0 |
| **Zubehörregeln** `ld_mensura_accessory_rule` (+ Übersetzung `name`, `reason`): Auslöser (`triggerType` material, option_value, use_case oder feature + `featureCondition`) → Shopware-Produkt (Abstandshalter, Klebeband, Spanngummis, Montagekleber, Profile) mit **Mengenregel** (`fixed`, `per_piece`, `per_hole`, `per_perimeter`, `per_area` × `quantityFactor`), Begründungstext, `recommended` oder `required` | Zubehörempfehlung im Berater **und** im Konfigurator/Warenkorb (z. B. 4 Bohrungen × 10 Stk = 40 Abstandshalter) | ✅ v0.4.0 (Tabelle; Auswertung mit Warenkorb/Store-API `catalog`) |
| **Konfigurations-Vorlage (Preset)**: gleiches Schema wie Payload `ldMensura` (Produkt/Profil, Material, Format, Menge, Optionen), optional Felder als „gesperrt“ markiert; als URL-Parameter `?mensura=…` oder kurze ID | „Auswahl übernehmen“ → Konfigurator vorbelegt; ungültige Teile werden mit Hinweis korrigiert (gleiche Regeln wie beim Materialwechsel, KONZEPT 3.7) | mit dem Konfigurator (Schritt 6) |
| **Store-API** (ohne EK) | `GET /store-api/ld-mensura/catalog` (Profile, Materialien, Optionswerte mit Merkmalen, Anwendungsfällen, Bildern, „ab“-Preisen), `POST /store-api/ld-mensura/preset/validate` (Vorlage prüfen → gültige Konfiguration + Hinweise), `POST /store-api/ld-mensura/calculate` (Preis für Vergleichslisten) | mit Schritt 5/6 |
| **Events** | `MensuraCatalogLoadedEvent` (Berater ergänzt Daten), `MensuraPresetAppliedEvent` (Statistik: welche Empfehlung wurde übernommen), Zubehör als eigene Warenkorbpositionen mit Verweis auf die Mensura-Position (`payload.ldMensuraParent`) | mit Schritt 6 |

Erklärtexte („Was ist der Unterschied zwischen Laminat matt, glänzend und Anti-Graffiti?“) liegen als übersetzte
Beschreibungen an Optionswerten bzw. Merkmalen und werden in Konfigurator, Vergleichstabelle und Berater gemeinsam genutzt.
Gepflegt wird also nur an einer Stelle.

**Stabilitätsregel:** Payload `ldMensura` und Schnappschuss tragen `schema`. Felder werden nur ergänzt, nie
umbenannt. Bei einem Bruch wird `schema` erhöht, und der `ConfigurationReader` liest alte Versionen weiter.
