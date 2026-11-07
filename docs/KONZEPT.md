# Mensura – Konzept Kalkulation & Konfigurator

Stand: 07.11.2026 · Grundlage: Analyse des Comdatek-Konfigurators (connect.comdatek.de,
Produkt „Aluminiumverbundplatten“), eigene Anforderungen der Lohndruckerei.

## 1. Was der Kunde im Frontend tut (Vorbild Comdatek)

Konfiguration in 4 Schritten, Preis wird bei jeder Änderung live neu berechnet:

| Schritt | Eingabe | Beispiel Aluverbund |
|---|---|---|
| 1 Material | Auswahl | Aluverbund 3 mm / 4 mm / 6 mm / gebürstet (Bronze, Gold, Kupfer, Silber, Schwarz) |
| 2 Endformat | Breite × Höhe in mm (frei) | min. 100 × 100, max. 1500 × 3000 (Drehen erlaubt) |
| 3 Auflage | Anzahl Motive × Stück je Motiv | 1 × 1 |
| 4 Druck & Weiterverarbeitung | Optionsgruppen | Farbigkeit, Lochbohrungen, Schneiden, Veredelung |

Angezeigt werden: **Gesamtpreis**, **Preis je m²**, **Preis je Stück** (netto, zzgl. MwSt./Versand),
Materialeigenschaften (Gewicht/m², innen/außen, UV-beständig …) und eine Artikelnummer aus den
gewählten Werten (z. B. `124-29.1-57-3-134`).

Die Comdatek-Preis-API liefert zusätzlich `price_per_liter`, `price_per_kg`, `price_per_m`,
`discount` und `individual_prices` – die Engine dort kennt also auch Liter-, Kilo- und
Laufmeter-Artikel, Rabatte und kundenindividuelle Preise. Optionswerte haben dort außerdem
`width`/`height` (Formatzugabe in mm), `multiplier_width`/`multiplier_height` und `exclusions`
(Ausschlüsse); Produkte kennen `fixed_formats`, `print_positions`, `unfolded_format` (offenes Format).

## 2. Ermittelte Preisformel Aluverbund (Comdatek, netto)

Durch Variieren von Format, Menge und Optionen nachgerechnet – alle Stichproben stimmen auf den Cent:

```
Fläche je Stück [m²] = Breite [mm] × Höhe [mm] / 1.000.000          (exakt, ohne Rundung)
Satz je m²          = Materialpreis/m² + Σ Optionsaufschläge/m²
Stückpreis          = Satz je m² × Fläche + Grundpreis/Stk (0,50 €) + Σ Optionsaufschläge/Stk
Menge               = Motive × Stück je Motiv
Gesamtpreis         = max(Mindestauftragswert 12,50 €, Stückpreis × Menge)
```

Beispiel 1000 × 500 mm, 3 mm, 4/4, Lochbohrung, Konturschnitt, Anti-Graffiti beidseitig, 10 Stk:
(29 + 9 + 2 + 29) €/m² × 0,5 m² + 0,50 € + 0,30 € = 35,30 €/Stk → **353,00 €**.

**Materialpreise je m²:** 3 mm 29,00 · 4 mm 32,00 · 6 mm 50,00 · gebürstet Bronze/Gold/Kupfer/Silber 63,00 ·
gebürstet Schwarz 58,89

**Optionsaufschläge** (unabhängig vom Material):

| Gruppe | Wert | Aufschlag |
|---|---|---|
| Farbigkeit | 4/0 einseitig | ± 0 |
| | 4/4 beidseitig | + 9,00 €/m² |
| | 0/0 unbedruckt | − 8,00 €/m² (Abschlag!) |
| Lochbohrungen | Nein / Ja | 0 / + 0,30 €/Stk |
| Schneiden | rechteckig / Konturschnitt | 0 / + 2,00 €/m² |
| Veredelung | UV-Lack 4/0 glänzend / 4/4 glänzend | + 6,00 / + 12,00 €/m² |
| | UV-Lack 4/0 matt / 4/4 matt | + 6,25 / + 12,75 €/m² |
| | Laminat matt oder glänzend 1-seitig / 2-seitig | + 8,00 / + 15,00 €/m² |
| | Anti-Graffiti-Laminat 1-seitig / 2-seitig | + 15,00 / + 29,00 €/m² |

**Beobachtungen:** keine Mengenstaffel (29,00 €/m² auch bei 500 m²), keine Einrichtungskosten je Motiv
(2 Motive × 1 = 1 Motiv × 2), keine Mindestfläche je Stück (100 × 100 mm × 100 Stk = 0,79 €/Stk),
Mindestpreis gilt **je Position**, nicht je Stück. Formate werden bei Bedarf gedreht (1600 × 1000 ist erlaubt).

## 2a. Comdatek-Einkaufspreisliste 2026 (Excel der Lohndruckerei)

Quelle: `Verwaltung/kalkulation/comdatek_preise_Lohndruckerei_2026.xlsx` – das sind **Einkaufspreise**
der Lohndruckerei (z. B. Alu 3 mm 26 €/m² EK ggü. 29 €/m² im Comdatek-Shop; 4/4 +6 EK ggü. +9).
Das Verhältnis ist nicht einheitlich (×1,12 bis ×2) → EK und VK müssen getrennt pflegbar sein.

| Blatt / Produktgruppe | Berechnungsart | Neu ggü. Aluverbund-Beispiel |
|---|---|---|
| Schilder & Platten: Aluverbund, Acryl, Hartschaum (2 Gruppen), Hart-PVC/Polystyrol, Hohlkammer, Displaykarton/DISPA, Re-Board, Stadur | €/m² Material + €/m² Optionen + €/Stk Schnitt/Bohrung, Mindestpreis je Gruppe (9,80 / 12,50 / 15,00 €) | Ein Optionswert hat **mehrere Preisanteile zugleich** (Konturschnitt Alu: 3 €/m² **und** 0,50 €/Stk); **gleiche Option, anderer Preis je Materialgruppe** (4/4: Alu +6, Hartschaum 5–10 mm +8, Hohlkammer +3); **rechteckiger Zuschnitt kostet je Stück** (0,05–0,35 €); Mindestpreis hängt an der Gruppe |
| Kaschierte Platten (Orajet 3551 + Laminat) | Trägerplatte €/m² + Druck 4/0 €/m² (15) + Schnitt €/Stk, Mindestpreis 15 € | Druck ist eigener Preisbaustein, Träger ist das „Material“ |
| Signicolor-Aluminium | nur Überschrift, Mindestpreis 12,50 € | Werte fehlen noch |
| Banner: PVC 510 g, Mesh 300 g | €/m² nach **Staffel über die Gesamtfläche** (ab 0/10/25/50/100/200 m²), Konfektion €/m² (Saum + Ösen 1 €, Hohlsaum 3 €, Keder 3 €) | Flächenstaffel |
| Banner: Blockout 610 g | €/m² je nach **Fläche je Stück** („U 1 qm“ 7,50 / „Ü 1 qm“ 6,50), zweiseitig +5 €/m² | Staffel nach Stückfläche |
| Banner: Textil Greyback, PVC-Linoleum-Boden | €/m² fest, Konturschnitt €/m² | – |
| Textilbanner: Auflicht, Blockout, Pro-Light | €/m² nach Flächenstaffel (ab 1/10/50 m²), Saum/Hohlsaum €/m², **Flauschband/Gummilippe €/lfm** | Preis je laufendem Meter (Umfang) |

**Entscheidungen (geklärt am 07.11.2026):**
- **Flächenstaffel je Position:** Fläche × Menge einer Konfiguration (5 Banner à 3 m² = 15 m² → „ab 10 m²“).
  Andere Positionen im Warenkorb zählen nicht mit.
- **Mindestpreis je Position:** jede Konfiguration kostet mindestens den Mindestpreis, wie im Comdatek-Shop.
- **Banner-Konfektion (Saum + Ösen, Hohlsaum, Keder):** noch offen, wird bei Comdatek erfragt. Deshalb bekommt
  jeder Optionswert beide Preisanteile (je m² und je lfm Umfang); was gilt, wird je Option eingestellt.
- **EK und VK werden getrennt gepflegt**, dazu ein **Margenrechner** (siehe 3.5). Bei Comdatek liegt die Marge
  meist bei 20–30 %; bei anderen Lieferanten kann der VK anders entstehen.

**Folgerungen fürs Datenmodell** (unten eingearbeitet):
1. Jeder Preis hat ein **EK**- und ein **VK**-Feld, beide selbst gepflegt; der Margenrechner hilft beim Ausfüllen.
   Testrechner und Listen zeigen die Marge. Damit liegen auch die Einkaufspreise, die der LieferLotse bewusst
   nicht führt, in Mensura.
2. Optionswerte bekommen **Preisanteile** (beliebig kombinierbar: €/m², €/Stk, €/lfm, pauschal, %) statt einer einzigen Preisart.
3. Optionen sind eine wiederverwendbare Bibliothek (Name, Werte); **Preise werden je Kalkulation** gepflegt,
   optional je Material überschrieben.
4. Staffelbasis zusätzlich **Fläche je Stück**.
5. Mindestpreis auch am Material (überschreibt die Kalkulation).

## 2b. Weitere Entscheidungen (07.11.2026)

- **Nur B2B, immer netto.** Basis-VK = Kundengruppe **Wiederverkäufer**. Andere Gruppen bekommen einen
  Aufschlag in %, z. B. **Gewerbe** +10 bis 15 %. Gewerbe gilt auch für nicht eingeloggte Besucher und für Konten ohne
  Wiederverkäufer-Freischaltung. Dafür wird in Shopware die Standard-Kundengruppe des Verkaufskanals auf
  Gewerbe gestellt. Umgesetzt in `ld_mensura_customer_group_rule` (global oder je Kalkulation).
- **Produktions- und Lieferzeit** wählt der Kunde je Position, als Aufschlag (Optionsgruppe `type = delivery`,
  Werte mit Werktagen). Offen ist, ob eine Wahl **je Lieferant** für alle seine Positionen gelten soll. Dafür hat die Gruppe
  `scope = position | supplier`, durchgesetzt wird es später im Warenkorb. Mensura errechnet daraus `ldDeliveryDate` für den LieferLotse.
- **Produkte:** beides möglich. Ein allgemeines Produkt („Plattendirektdruck“, alle Materialien) oder ein Produkt je
  Material. **Abhängigkeiten:** Optionen gelten je Material (Material A → Veredelung A + B, Material B → C + D)
  und können andere Optionen voraussetzen oder ausschließen.
- **Motive und Druckdaten:** z. B. 20 Motive à 1 Stück. Hochladen ist schon im Konfigurator möglich; weitere Wege
  (Kundenkonto, Link) bleiben offen. Prüfung und Freigabe (Auflösung, Stückzahl, Format, Beschnitt) übernimmt ein eigenes
  Plugin. Mensura reserviert dafür `artwork` im Schnappschuss und im Payload.
- **LieferLotse und künftige Plugins:** siehe [INTEGRATION.md](INTEGRATION.md).

## 3. Kalkulationsfelder (Datenmodell)

**Umgesetzt in v0.2.0** (Migration `Migration1794120000CreateMensuraTables`, alle Preise netto als EK/VK):

| Tabelle | Inhalt |
|---|---|
| `ld_mensura_calculation` | Rechenvorlage: Einheit, Format (Grenzen, feste Formate, Drehen), Mindestfläche/Rundung, Grundpreise als Preisanteile, Mindestpreis EK/VK, Staffelbasis + Staffeln, Formel, VK-Regel + Margenkorridor, Lieferant + Lieferantencodes |
| `ld_mensura_calculation_material` | Kalkulation ↔ Material (n:m) |
| `ld_mensura_material` (+ `_translation`) | Preis je Einheit EK/VK, eigener Mindestpreis/Staffel, Rohformat, Max.-Format, Gewicht, Eigenschaften, Lieferant + Codes, Bild |
| `ld_mensura_option_group` (+ `_translation`) | Optionsgruppe: Typ (`standard`/`delivery`), Geltung (`position`/`supplier`), Darstellung, Pflicht |
| `ld_mensura_option_value` (+ `_translation`) | Wert: Kürzel, Formatzugabe/-faktor, Werktage, Ausschlüsse, Lieferantencodes, Bild |
| `ld_mensura_calculation_option_value` | Wert in einer Kalkulation, optional je Material: Preisanteile EK/VK, Staffeln, Bedingungen (`requires`/`excludes`), Standardwert |
| `ld_mensura_customer_group_rule` | VK-Aufschlag % je Kundengruppe, global oder je Kalkulation |
| `ld_mensura_product_calculation` | Produkt ↔ Kalkulation (`product.extensions.ldMensura`), festes Material, eingeschränkte Auswahl, Vorbelegung |
| `ld_mensura_order_line_item` | Rechenweg-Schnappschuss je Bestellposition (`orderLineItem.extensions.ldMensura`), nur Admin-API |

Die folgenden Unterabschnitte beschreiben die fachliche Bedeutung der Felder.

Ziel: Jeder Artikel hängt an einer **Kalkulation** (Rechenvorlage). Die Standardformel deckt Platten und
Folien ab; jede Kalkulation kann einzelne Bausteine an- und abschalten oder eine **eigene Formel** nutzen.
Tabellen-/Entity-Präfix: `ld_mensura_`.

### 3.1 Kalkulation (`ld_mensura_calculation`)
| Feld | Zweck |
|---|---|
| Name, technischer Name, aktiv | |
| Mengeneinheit | `piece`, `area` (m²), `length` (lfm), `weight` (kg), `volume` (l) |
| Formatmodus | frei / feste Formate / beides; Liste fester Formate |
| Breite/Höhe min/max (mm), Drehen erlaubt | Formatprüfung |
| Mindestfläche je Stück (m²), Flächenrundung (auf x m² aufrunden) | z. B. Folien „mind. 0,25 m²“ |
| Grundpreis je Stück, je Motiv, je Auftrag (Rüstkosten) | |
| Mindestpreis je Position | Comdatek: 12,50 € |
| Staffelbasis | keine / Stückzahl / Gesamtfläche / Fläche je Stück / Gesamtpreis |
| VK-Regel (Vorgabe für den Margenrechner) | Zielmarge %, Aufschlag %, Faktor oder frei (siehe 3.5) |
| Preisfaktor (Aufschlag %) und Endpreis-Rundung | z. B. auf 0,05 € |
| Max. Motive, max. Menge | |
| Formel (optional) | eigener Ausdruck statt Standardformel |

### 3.2 Material (`ld_mensura_material`)
Name, Beschreibung, Bild, Kürzel (für Artikelnummer), **Preis je Einheit** (meist €/m², EK und VK),
eigener Mindestpreis (optional), eigene Staffeln, Lieferant (optional, Verweis auf LieferLotse ohne harte Abhängigkeit), Rohformat/Plattengröße (z. B. 1500 × 3050 – später für Nutzen-/Verschnittberechnung),
Max.-Format (falls kleiner als Kalkulation), Eigenschaften (Gewicht/m², innen/außen, UV, Brandschutz …),
Zuordnung zu Kalkulationen (n:m).

### 3.3 Optionsgruppe (`ld_mensura_option_group`) und Optionswert (`ld_mensura_option_value`)
Gruppe: Name, Darstellung (Auswahlliste, Kacheln mit Bild, Radio, Checkbox, Zahlenfeld), Pflicht, Position,
Hilfetext, Zuordnung zu Kalkulationen.

Wert: Name, Kürzel, Standardwert, Bild. Gruppen und Werte sind eine wiederverwendbare Bibliothek;
die **Preise** hängen an der Zuordnung *Kalkulation × Optionswert* (optional je Material überschrieben)
und bestehen aus einem oder mehreren **Preisanteilen** (je EK und VK), z. B. Konturschnitt = 3 €/m² + 0,50 €/Stk:

| Preisanteil | Wirkung |
|---|---|
| `per_area` | € je m² (auch negativ, z. B. „unbedruckt“) |
| `per_piece` | € je Stück |
| `per_motif` | € je Motiv |
| `per_order` | pauschal je Position |
| `per_perimeter` | € je lfm Umfang (z. B. Kantenschutz, Hohlsaum) |
| `percent` | % auf Zwischensumme |
| `factor` | Multiplikator auf Satz je m² |

Dazu: Formatzugabe Breite/Höhe in mm (z. B. Hohlsaum, Beschnitt) mit Multiplikator, **Ausschlüsse**
(Wert X nicht mit Wert Y), Preis je Material abweichend (optional), eigene Staffeln.

### 3.4 Staffel (`ld_mensura_price_tier`)
Gehört zu Kalkulation, Material oder Optionswert. Felder: ab Menge (Stück, Gesamt-m² oder m² je Stück –
je Staffelbasis), Wert als **Preis** (ersetzt, EK/VK) oder **Rabatt %**.
Beispiele: PVC-Banner ab 0/10/25/50/100/200 m² Gesamtfläche; Blockout unter/über 1 m² je Stück.

### 3.5 Margenrechner (EK → VK)
EK und VK sind getrennte Felder; gespeichert wird immer, was im VK-Feld steht. Der Margenrechner
schlägt den VK nur vor, er rechnet nicht still im Hintergrund.

- **VK-Regel** je Kalkulation (optional je Material oder Lieferant überschreibbar):
  | Regel | VK-Vorschlag | Beispiel EK 26,00 € |
  |---|---|---|
  | Zielmarge % (auf den VK) | EK ÷ (1 − Marge) | 25 % → 34,67 € |
  | Aufschlag % (auf den EK) | EK × (1 + Aufschlag) | 25 % → 32,50 € |
  | Faktor | EK × Faktor | 1,3 → 33,80 € |
  | frei | kein Vorschlag, VK wird von Hand gepflegt | – |
- Optional **Rundung** des Vorschlags (z. B. auf 0,10 € oder auf ,50/,90).
- Neben jedem Preis zeigt die Administration **Marge % und Aufschlag %** in Farbe: grün im Zielkorridor,
  gelb darunter, rot bei VK ≤ EK. Der Korridor ist je VK-Regel einstellbar, für Comdatek als Vorgabe 20–30 %.
- **Sammelfunktion** „VK neu vorschlagen“ für eine Kalkulation, ein Material oder alle Preise eines Lieferanten.
  Die Änderungen werden als Vorschau gezeigt und erst nach Bestätigung übernommen.
- Wichtig: Marge (auf den VK) und Aufschlag (auf den EK) sind verschiedene Zahlen (25 % Marge = 33,3 % Aufschlag),
  daher werden immer beide angezeigt.
- Der Testrechner zeigt für eine Beispielkonfiguration EK gesamt, VK gesamt, Deckungsbeitrag in € und die Marge in %.
  Bei einer Position mit Mindestpreis ist die Marge oft deutlich höher; das wird dort sichtbar.

### 3.6 Produktzuordnung
Shopware-Produkt bekommt per Extension eine Kalkulation und die erlaubten Materialien/Optionen.
Warenkorb: eigener Line-Item-Typ bzw. Preis über CartProcessor, Konfiguration als Payload
(Material, Format, Motive, Menge, Optionen) – erscheint so auch in Bestellung und für LieferLotse.

## 4. Standardformel (Engine)

```
B, H           = Endformat + Formatzugaben der Optionen (mm), ggf. gedreht
Fläche         = max(Mindestfläche, aufrunden(B × H / 1e6, Flächenrundung))
Umfang         = 2 × (B + H) / 1000
Menge          = Motive × Stück je Motiv
Satz/m²        = (Materialpreis[Staffel] + Σ per_area) × Π factor
Stückpreis     = Satz/m² × Fläche + Grundpreis/Stk + Σ per_piece + Umfang × Σ per_perimeter
Zwischensumme  = Stückpreis × Menge + Motive × (Grundpreis/Motiv + Σ per_motif) + Rüstkosten + Σ per_order
Zwischensumme  = Zwischensumme × (1 − Staffelrabatt) × (1 + Σ percent) × (1 + Preisfaktor)
Gesamt         = runden(max(Mindestpreis, Zwischensumme))
```

Die Engine rechnet dieselbe Formel zweimal – mit VK-Werten (Kundenpreis) und mit EK-Werten
(Einkauf) – und liefert den Rechenweg samt Deckungsbeitrag. Alle Preisanteile (per_area, per_piece …)
kommen aus Material, Kalkulation und den gewählten Optionswerten, jeweils nach Staffel aufgelöst.

**Eigene Formeln:** Für Artikel, die nicht ins Schema passen, bekommt die Kalkulation einen Ausdruck mit
den Variablen oben (`breite`, `hoehe`, `flaeche`, `umfang`, `menge`, `motive`, `materialpreis`,
`optionen.<gruppe>` …). Umsetzung mit sicherem Ausdrucks-Parser (Kandidat: `symfony/expression-language`
als Composer-Abhängigkeit – ist in Shopware nicht enthalten, also bewusst ergänzen) oder eigener kleiner
Parser. Nie `eval`.

Die Berechnung läuft **immer serverseitig** (Store-API-Route für den Live-Preis, CartProcessor für den
Warenkorb) – das Frontend zeigt nur an, damit niemand Preise manipulieren kann.

## 5. Administration

Eigener Hauptmenüpunkt **Mensura** mit Untermenüs (erweiterbar über `module/ld-mensura/constants.js`):
Übersicht · Kalkulationen · Materialien · Optionen – später z. B. Formeln testen, Produktzuordnung,
Import. Designs A/B/C wie im LieferLotse, je Benutzer umschaltbar. Jede Kalkulation bekommt einen
**Testrechner** (Format/Menge/Optionen eingeben → Rechenweg Zeile für Zeile), damit Formeln ohne
Shop-Frontend geprüft werden können.

## 6. Fahrplan

1. ✅ Grundgerüst, Menü, Designs, Konzept
2. ✅ Entitäten + Migration (Kalkulation, Material, Optionen, Preisanteile EK/VK, Staffeln, VK-Regel, Kundengruppen,
   Produktverknüpfung, Bestell-Schnappschuss)
3. Admin: Listen & Detailseiten je Bereich, Preisfelder EK/VK mit Margenrechner
4. Preis-Engine (PHP, mit Unit-Tests anhand der Comdatek-Werte oben) + Testrechner im Admin
5. Produktzuordnung + Store-API „Preis berechnen“
6. Storefront-Konfigurator (4 Schritte, Live-Preis, Lieferzeit, Upload) + Warenkorb/Bestellung + Schnappschuss
6a. LieferLotse: Feld `attributes` in der Lieferantenposition + Mensura-Subscriber auf `SupplierOrderBuiltEvent`
7. Weitere Artikel (Folien, Banner …) – eigene Kalkulationen/Formeln
