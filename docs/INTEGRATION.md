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
      "weight":      { "perSqm": 3.8, "piece": 1.9, "total": 19.0, "unit": "kg" },
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
- **Gewicht und Maße für den Versand:** `weight` = Gewicht je m² des Materials × Fläche (kg). Zusätzlich setzt der
  CartProcessor die Shopware-Lieferinformation der Position (Gewicht je Stück in kg, Breite/Höhe in mm aus der
  Konfiguration). So rechnen Shopware-Versandregeln (z. B. Warenkorbgewicht) und ein späteres Versand-Plugin mit den
  echten Werten statt mit den Produkt-Stammdaten. Ohne Gewicht am Material bleibt `weight` null.
- `ldDeliveryDate` setzt Mensura, wenn der Kunde eine Lieferzeit gewählt hat. Der `DeliveryDateResolver`
  des LieferLotse übernimmt den Termin dann in die Lieferantenbestellung.
- **Positionen, die mit Mensura berechnet wurden, gehen nur zusammen mit ihrer Konfiguration an einen Lieferanten.**

## 2. Rechenweg-Schnappschuss (intern)

Tabelle `ld_mensura_order_line_item`, 1:1 zur Bestellposition, als Extension `orderLineItem.extensions.ldMensura`:
Konfiguration, kompletter Rechenweg, **EK gesamt, VK gesamt**, vorgeschlagener Lieferant, Schema-Version.
Die Felder sind nicht `ApiAware`, sind also **nur über die Admin-API** erreichbar und nie in der Store-API.
Einkaufspreise erscheinen damit nie im Kundenkonto. Der geplante Logistikanteil (KONZEPT 3d) steht nicht hier, sondern in
der eigenen Tabelle `ld_mensura_order_line_item_logistics` mit eigenem Recht (Abschnitt 7.1). Spätere Plugins für Rechnung und Buchhaltung lesen
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
| Vectura (Versand, geplant) | Logistikanteil und Produktionszeit-Aufschlag je Position aus `ld_mensura_order_line_item_logistics`; liefert Vorschläge für den Logistikanteil (siehe Abschnitt 7) |
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

## 7. Vectura (Versand): Logistikanteil – geplant, Stand 07.11.2026

Fachliche Grundlage: `sw6-vectura/docs/LOGISTIKPAUSCHALE.md` Abschnitt 5, Konzept in Mensura: [KONZEPT.md](KONZEPT.md) 3d.
Vectura sammelt die tatsächlichen Versand- und Servicekosten der Lieferanten. Mensura rechnet einen **für den Kunden
unsichtbaren Logistikanteil** in den VK ein. Beide laufen ohne den anderen.

**Gemeinsame Sprache ohne Fremdschlüssel:**

| Begriff | Mensura | Vectura |
|---|---|---|
| Material | `ld_mensura_material.technicalName` (im Payload `ldMensura.material.key`) | `materialKey` |
| Materialgruppe | `ld_mensura_calculation.technicalName` | `calculationKey` |
| Produktionszeit | `technicalName` des Optionswerts einer Gruppe vom Typ `delivery` (Payload `ldMensura.delivery`) | `deliveryKey` |
| Kostenart | `cost_type` der Logistikregel | Kostenart der Ist-Kosten: `base`, `quantity`, `bulky`, `express`, `multi_source`, `other` |
| Längenbereich | Rampe `rampFrom`/`rampTo` (mm, längste Seite) | Längenbereich der Auswertung (`lengthFrom`/`lengthTo`) |

Ein umbenannter Schlüssel trennt die Verbindung. Die Administration warnt deshalb beim Ändern eines `technicalName`,
wenn Logistikregeln daran hängen. Vectura meldet Vorschläge mit unbekanntem Schlüssel als „nicht zugeordnet“.

### 7.1 Mensura → Vectura: Erlösseite je Position

Tabelle `ld_mensura_order_line_item_logistics` (1:1 zur Bestellposition, Extension
`orderLineItem.extensions.ldMensuraLogistics`, nur Admin-API und DAL, nie Store-API). Geschrieben wird sie zusammen mit dem
Rechenweg-Schnappschuss beim Umwandeln des Warenkorbs:

```json
{
  "schemaVersion": 1,
  "logisticsSale": 19.85,
  "productionTimeSale": 0.0,
  "sharePercent": 12.4,
  "goodsContributionMargin": 21.30,
  "lines": [
    { "ruleId": "…", "costType": "base",  "form": "per_sqm", "value": 1.60,  "materialKey": "hartschaum-5mm",
      "longestSide": 1500, "fRamp": 1.0, "fThreshold": 1.0, "amount": 2.40, "minimumApplied": false },
    { "ruleId": "…", "costType": "bulky", "form": "per_sqm", "value": 11.63, "materialKey": "hartschaum-5mm",
      "rampFrom": 1000, "rampTo": 1400, "longestSide": 1500, "fRamp": 1.0, "fThreshold": 1.0, "amount": 17.45 }
  ]
}
```

Umgesetzt (Schritt 5): Zeilen tragen statt IDs die Schlüssel `calculationKey`, `materialKey` und `deliveryKey` (bzw.
`optionKey` bei anderen Optionen). `productionTimeSale` = Preis mit gewählter − mit Standard-Lieferzeit, beide ohne Logistik.
`goodsContributionMargin` ist ohne `ld_mensura.purchase` null (EK-Schutz).
Beträge netto im Endpreis der Position (nach Mindestpreis-Anteil und Kundengruppen-Aufschlag). Vectura liest die Tabelle
per DAL über den Entity-Namen, wenn Mensura installiert ist. Ohne Mensura fehlt die Erlösseite, und Vectura zeigt nur die
Kosten. Felder werden nur ergänzt. Bei einem Bruch steigt `schemaVersion`.

### 7.2 Vectura → Mensura: Vorschläge

Mensura ruft `GET /api/_action/ld-vectura/logistics/suggestions?from=YYYY-MM-DD&to=YYYY-MM-DD` auf (Admin-API, Token des
angemeldeten Benutzers):

- **Nur wenn Vectura installiert ist.** Die Administration prüft das über die geladenen Bundles
  (`Shopware.Context.app.config.bundles` enthält `LdVectura`). Fehlt Vectura, erscheint die Vorschlagskarte nicht, und
  der Logistikanteil wird von Hand gepflegt. Antwortet die Route mit 404, gilt das ebenso.
- **Rechte:** Mensura zeigt die Karte nur mit `ld_mensura.logistics`. Die Route prüft Vectura selbst. Bei 403 zeigt die
  Karte „Keine Berechtigung für Vectura-Vorschläge“, alles andere bleibt bedienbar.
- **Ohne Filter auf ein Ziel:** Mensura holt alle Vorschläge des Zeitraums (Standard: rollierend 3 Monate, offene Frage in
  LOGISTIKPAUSCHALE 7.3) und ordnet sie über die Schlüssel der aktuellen Kalkulation, des Materials bzw. Optionswerts zu.

Erwartetes Antwortformat (Vorschlag von Mensura, wird mit der Vectura-Entwicklung abgestimmt):

```json
{
  "apiVersion": 1,
  "period": { "from": "2026-08-01", "to": "2026-10-31" },
  "generatedAt": "2026-11-07T08:00:00+01:00",
  "targetMargin": 25,
  "suggestions": [
    {
      "id": "hartschaum-bulky-1200-2449",
      "cause": {
        "materialKeys": ["hartschaum-5mm", "hartschaum-10mm"],
        "calculationKey": null,
        "deliveryKey": null,
        "lengthFrom": 1200, "lengthTo": 2449,
        "thresholdBasis": null, "thresholdFrom": null
      },
      "costType": "bulky",
      "costs": 1011.00,
      "revenue": 1090.00,
      "coverage": 1.08,
      "targetCoverage": 1.25,
      "cases": 27,
      "proposal": {
        "form": "per_sqm", "value": 11.63,
        "rampFrom": 1000, "rampTo": 1400,
        "thresholdBasis": null, "thresholdFrom": null, "thresholdTo": null,
        "minimumAmount": null, "minimumScope": null
      },
      "current": { "form": "per_sqm", "value": 0.0 },
      "stable": true,
      "warnings": ["few_cases", "step_limit_exceeded"]
    }
  ]
}
```

- `stable` = Korridor zwei Zeiträume hintereinander verlassen (Hysterese, LOGISTIKPAUSCHALE 3.4 Nr. 6). Das prüft Vectura.
  Mensura zeigt nicht stabile Vorschläge nur zur Information, ohne „Übernehmen“.
- `warnings` (feste Codes): `few_cases` (unter 10 Aufträge), `step_limit_exceeded` (mehr als ±1 €/m² bzw. ±2 Prozentpunkte),
  `outliers_capped` (Kosten über dem 95. Perzentil gekappt), `unmapped_key` (Schlüssel in Mensura unbekannt).
  Mensura prüft die Schrittgrenze zusätzlich selbst gegen die gültige Regel.
- **Revenue** zählt nur den Logistikanteil (getrennte Rechnung). Versandpauschale, Verpackungszuschlag und
  Produktionszeit-Aufschlag zählen nicht.
- **Übernehmen** legt in Mensura eine **neue** Regel `ld_mensura_logistics_rule` an (gültig-ab wählbar, Standard nächster
  Montag), mit `source = vectura` und `sourceReference` (`suggestionId`, Zeitraum, `coverage`, `targetCoverage`, `cases`,
  Benutzer, Zeitpunkt). Die bisherige Regel bleibt als Verlauf erhalten. Bei mehreren `materialKeys` legt Mensura je Material
  eine Regel an, oder eine Regel an der Kalkulation, wenn alle Materialien der Kalkulation betroffen sind (Auswahl im Dialog).
  Vectura bekommt keinen Rückruf. Die Wirkung sieht es an den folgenden Schnappschüssen.

Umgesetzt in Mensura (Schritt 8): Beispielantwort `src/Resources/app/administration/src/module/ld-mensura/service/
vectura-suggestions.example.json` (passend zu den Demo-Daten) – Vectura kann sie als Sollformat nehmen. Zuordnung:
Material über `cause.materialKeys`, Kalkulation über `cause.calculationKey` (nur ohne Materialliste), Optionswert über
`cause.deliveryKey` bzw. `cause.optionKey`. Zeitraum standardmäßig rollierend 3 Monate (`from`/`to` als Query).

Mensura schreibt nie in Vectura-Tabellen und Vectura nie in Mensura-Tabellen.

**Stabilitätsregel:** Payload `ldMensura` und Schnappschuss tragen `schema`. Felder werden nur ergänzt, nie
umbenannt. Bei einem Bruch wird `schema` erhöht, und der `ConfigurationReader` liest alte Versionen weiter.
