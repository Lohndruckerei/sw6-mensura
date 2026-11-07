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
      "area":        { "piece": 0.5, "total": 5.0 },
      "motifs": 2, "perMotif": 5,
      "options": [ { "group": "farbigkeit", "groupName": "Farbigkeit", "value": "4-4", "valueName": "4/4 beidseitig bedruckt" } ],
      "sku": "ALU3-1000x500-44-KON"
    },
    "ldDeliveryDate": "2026-11-20"
  }
  ```
- `ldDeliveryDate` setzt Mensura, wenn der Kunde eine Lieferzeit gewählt hat. Der `DeliveryDateResolver`
  des LieferLotse übernimmt den Termin dann in die Lieferantenbestellung.
- **Positionen, die mit Mensura berechnet wurden, gehen nur zusammen mit ihrer Konfiguration an einen Lieferanten.**

## 2. Rechenweg-Schnappschuss (intern)

Tabelle `ld_mensura_order_line_item`, 1:1 zur Bestellposition, als Extension `orderLineItem.extensions.ldMensura`:
Konfiguration, kompletter Rechenweg, **EK gesamt, VK gesamt**, vorgeschlagener Lieferant, Schema-Version.
Die Felder sind nicht `ApiAware`, sind also **nur über die Admin-API** erreichbar und nie in der Store-API.
Einkaufspreise erscheinen damit nie im Kundenkonto. Spätere Plugins für Rechnung und Buchhaltung lesen
EK/VK und Deckungsbeitrag von hier. Preisänderungen in den Stammdaten ändern bestehende Bestellungen nicht.

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

Mensura stellt dafür bereit (mit der Preis-Engine, Schritt 4):
- `ConfigurationReader::fromLineItem()`: liest die Konfiguration einer Bestellposition typisiert, unabhängig von der Schema-Version
- `MensuraPriceCalculatedEvent`: nach jeder Berechnung; andere Plugins können Zu- und Abschläge ergänzen, z. B. Kundenrabatte
- Store-API `POST /store-api/ld-mensura/calculate`: Live-Preis für das Frontend

**Stabilitätsregel:** Payload `ldMensura` und Schnappschuss tragen `schema`. Felder werden nur ergänzt, nie
umbenannt. Bei einem Bruch wird `schema` erhöht, und der `ConfigurationReader` liest alte Versionen weiter.
