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
- **Banner-Konfektion (Saum + Ösen, Hohlsaum, Keder):** laut Comdatek-Shop je m² (siehe 2b). Beide Preisanteile
  (je m² und je lfm Umfang) bleiben möglich, z. B. für Gummilippe/Flauschband je lfm.
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
- **Banner-Konfektion je m²** (Comdatek-VK geprüft: gleicher Aufschlag bei 3×3, 9×1 und 4,5×2 m).
- Weitere Rechenarten (Flächenstaffel, Preistabellen für Flyer/Broschüren, Festformate, Seiten) und die daraus
  folgenden Erweiterungen des Datenmodells: [PRODUKTBEISPIELE.md](PRODUKTBEISPIELE.md).

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
| `ld_mensura_product_calculation` | Produkt ↔ Kalkulation (`product.extensions.ldMensura`), festes Material, eingeschränkte Auswahl, Vorbelegung. **Wird in v0.3.0 durch Produktprofile ersetzt** (3.6), weil Spezialprodukte Materialien aus mehreren Kalkulationen brauchen |
| `ld_mensura_order_line_item` | Rechenweg-Schnappschuss je Bestellposition (`orderLineItem.extensions.ldMensura`), nur Admin-API |

**Ergänzt in v0.4.0** (Migration `Migration1794140000PriceTablesDeliveryAdvisor`):

| Tabelle / Feld | Inhalt |
|---|---|
| `ld_mensura_price_table` (+ `_row`) | Preistabelle je Kalkulation: Schlüssel Material (optional) + Optionswerte, Art `piece`/`total`, Zeilen Auflage → EK/VK |
| Kalkulation `setupPricePurchase`/`setupPriceSale`, `quantitySteps` | Einrichtepauschale je Position; Zeilen der Preismatrix |
| Optionswert `availability`, Übersetzung `badges` | Lieferzeit-Verfügbarkeit `{maxQuantity, maxArea, materialIds}`; Badges |
| Material-Übersetzung `badges` | Badges für Kacheln, z. B. „außen“, „Bestseller“ |
| Produktprofil `layout`, `quantitySteps` | Darstellung `compact`/`guided` (leer = Plugin-Einstellung), eigene Mengenstufen |
| `ld_mensura_feature` (+ `_translation`, `ld_mensura_feature_value`) | Merkmal-Katalog und Werte je Material/Optionswert/Profil (`value` als `{"value": …}`) |
| `ld_mensura_use_case` (+ `_translation`, `_assignment`) | Anwendungsfälle mit Eignung 0–5 und Vorbelegung je Profil/Material |
| `ld_mensura_accessory_rule` (+ `_translation`) | Zubehörregeln: Auslöser → Shopware-Produkt, Mengenregel, empfohlen/erforderlich, Begründung |
| Plugin-Einstellungen | `storefrontLayout`, `allowLayoutSwitch`, `layoutTestShare`, `orderCutoff`, `workingDays`, `holidays` |

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

### 3.6 Produktprofile: allgemeine und spezielle Produkte

**Grundidee: Kalkulationen rechnen, Produkte wählen aus.** Preise, Optionen und Abhängigkeiten werden nur
einmal gepflegt, in der Kalkulation je Materialgruppe (Aluverbund, Hartschaum, Acryl …). Ein Shopware-Produkt
bekommt ein **Produktprofil**, das daraus auswählt und einschränkt. Es erweitert nie: Was die Kalkulation nicht
kann, kann auch kein Produkt. So bleiben Preise an einer Stelle gepflegt.

| Produkt | Profil |
|---|---|
| **Plattendirektdruck** (allgemein) | alle Materialien aus den Kalkulationen Aluverbund, Hartschaum, Acryl, PVC …, alle Optionen |
| **Aluverbundplatten** | alle Materialien der Kalkulation Aluverbund, alle Optionen |
| **Alu 3 mm** | festes Material (Schritt „Material“ entfällt) |
| **Duschrückwände** (speziell) | nur Hartschaum 5 mm, Aluverbund 3 mm, Acryl 5 mm (aus drei Kalkulationen); Laminat **fest** „Glaslaminat“; Lochbohrung **ausgeblendet** (= Nein); Schnitt nur rechteckig; Format max. 1.500 × 2.550 mm |

Tabellen (ersetzen `ld_mensura_product_calculation` aus v0.2.0, die noch keine Daten enthält):

| Tabelle | Inhalt |
|---|---|
| `ld_mensura_product_profile` | 1:1 zum Produkt (`product.extensions.ldMensura`): Art (`general`/`special`, nur zur Übersicht), Formatgrenzen enger als die Kalkulation, Mindestmenge/-preis, Preisaufschlag des Produkts (% und/oder €/m², z. B. Spezialprodukt +10 %), Vorbelegung, Schritte/Texte im Konfigurator, Vorlage (`templateProfileId`) |
| `ld_mensura_product_profile_material` | erlaubte Materialien **über Kalkulationen hinweg**: Kalkulation + Material (oder „alle Materialien der Kalkulation“), Reihenfolge, Anzeigename im Produkt, Aufschlag je Material |
| `ld_mensura_product_profile_option` | Regel je Optionsgruppe, optional nur für ein Material: Modus `inherit` (alles aus der Kalkulation) / `restrict` (nur diese Werte) / `fixed` (ein Wert, Gruppe nur als Info sichtbar) / `hidden` (Gruppe entfällt, fester Wert wird trotzdem berechnet und an den Lieferanten übermittelt), erlaubte Werte, Standardwert, Anzeigename, Reihenfolge |

**Welche Kalkulation rechnet?** Die des gewählten Materials. Wechselt der Kunde bei den Duschrückwänden von
Hartschaum auf Acryl, gelten sofort die Acryl-Preise, -Optionen und -Formatgrenzen. Das Profil schränkt sie
weiter ein. Optionsgruppen gibt es nur einmal in der Bibliothek, deshalb kann eine Regel wie „Laminat fest Glaslaminat“
für alle drei Materialien gleichzeitig gelten.

**Reihenfolge der Regeln:** Kalkulation (Grundmenge und Abhängigkeiten je Material) → Profil des Produkts →
Bedingungen der gewählten Werte (`requires`/`excludes`). Bleibt in einer Pflichtgruppe kein Wert übrig, ist die
Kombination nicht bestellbar. Die Administration warnt beim Speichern des Profils, und der Konfigurator bietet sie nicht an.

**Kategorisierung:** Die Kategorien im Shop sind normale Shopware-Kategorien. Ein Produkt kann in vielen Kategorien stehen
(„Plattendirektdruck“, „Küche & Bad“, „Gastronomie“). Mensura erfindet hier nichts Eigenes. In der Administration
lassen sich Kalkulationen, Materialien und Profile zusätzlich mit **Shopware-Tags** gruppieren und filtern
(z. B. „Platten“, „Folien“, „Banner“, „Papier“). Neue Spezialprodukte entstehen schnell durch **„Profil kopieren“**
aus einer Vorlage.

Warenkorb: normale Produktposition mit Konfiguration im Payload, Preis über CartProcessor (siehe INTEGRATION.md).

### 3.7 Optionssets: der Konfigurationsfluss (umgesetzt in v0.3.0)

Ein **Optionsset** (`ld_mensura_option_set` + `ld_mensura_option_set_group`) ist eine geordnete Folge von
Optionsgruppen, also ein Konfigurationsschritt. Welche Gruppen der Kunde sieht, ergibt sich aus den aktiven Sets:

```
Start-Set der Kalkulation  →  Folge-Set des gewählten Materials  →  Folge-Sets der gewählten Optionswerte (rekursiv)
z. B. „Grundlagen“ (Farbigkeit)  →  Aluverbund: „Plattenveredelung“ (Laminat, Lochbohrung, Schnitt)
                                 →  Wert „Kaschierung auf Alu“: „Alu-Weiterverarbeitung“
```

- Nicht relevante Gruppen verschwinden, weil sie in keinem aktiven Set stehen. Das Produktprofil kann zusätzlich
  einschränken, festlegen oder ausblenden (3.6).
- **Frühere Wahl ändern (z. B. Aluverbund → Acryl):**
  - **Format, Menge und Motive bleiben erhalten.**
  - **Gruppen, die auch im neuen Set vorkommen, behalten ihren Wert,** solange er dort erlaubt ist. Optionsgruppen sind eine
    gemeinsame Bibliothek, deshalb ist „Laminat“ bei Alu und Acryl dieselbe Gruppe. Andernfalls gilt der Standardwert:
    **immer der günstigste erlaubte Wert des Materials** (Entscheidung 08.11.2026; Vertrag `materials[].defaults`).
  - Gruppen, die wegfallen, werden aus der Konfiguration entfernt. Kehrt der Kunde zurück, wird die frühere Wahl
    wiederhergestellt, solange er die Seite nicht verlassen hat.
  - **Grenzen des neuen Materials** (z. B. Acryl max. 2.000 mm Höhe): Der Wert wird **nicht still verändert**. Das Feld
    wird markiert und mit einem Hinweis versehen („Acryl 5 mm: max. 2.000 mm“), dazu gibt es die Schaltfläche „Auf 2.000 mm setzen“.
    Bis zur Korrektur gibt es keinen Preis und kein „In den Warenkorb“.
  - Der Kunde sieht kurz, was sich durch den Wechsel geändert hat (z. B. „Lochbohrung entfällt bei Acryl“).
- Die Engine prüft dieselben Regeln serverseitig. Der Warenkorb nimmt nur gültige Konfigurationen an.

Weitere Felder in v0.3.0: Kalkulation `motifMode` (`multiply`/`split`), `tierScope` (`total`/`per_motif`),
`bestPriceMode` (`none`/`hint`/`apply`), Material `priceComponents` (z. B. 0,20 €/Stk), Optionsgruppe Typ
`format` mit Werten `width`/`height` (Festformate wie DIN A0–A2).

## 3a. Bedienung: intuitiv und mit Hilfe-Texten (verbindlich für alle Oberflächen)

Der Funktionsumfang ist groß. Die Bedienung muss trotzdem ohne Schulung gehen:

1. **Hilfetext an jedem Feld** (de/en), in Alltagssprache und mit Beispiel aus dem Druckalltag, z. B. „Mindestpreis:
   Eine Position kostet nie weniger, z. B. 12,50 € bei Aluverbund, auch wenn nur ein kleines Schild bestellt wird.“
2. **Erklärkarten** für die Grundbegriffe (Kalkulation, Material, Optionsgruppe, Optionsset, Produktprofil) oben auf der
   jeweiligen Seite, einklappbar, mit einer kleinen Skizze des Ablaufs.
3. **Assistenten statt leerer Formulare**: „Neue Kalkulation“ fragt Schritt für Schritt (Was wird verkauft? Wie wird
   gerechnet: Fläche, Stück, Tabelle? Mindestpreis? Staffel?). Vorlagen gibt es z. B. für „Platte wie Aluverbund“, „Banner mit
   Flächenstaffel“, „Etikett“ und „Flyer mit Preistabelle“.
4. **Live-Vorschau**: Neben jeder Kalkulation bzw. jedem Profil steht der Konfigurator so, wie ihn der Kunde sieht, mit Testrechner
   und Rechenweg Zeile für Zeile (EK, VK, Marge).
5. **Warnungen beim Speichern** statt Fehlern im Shop: Pflichtgruppe ohne Wert, VK ≤ EK, Staffelsprung, Material ohne Preis,
   Option ohne Preis in einer Kalkulation, Profil ohne gültiges Material.
6. **Wenig Klicks für Massenpflege**: Tabellenansicht mit Inline-Bearbeitung für Preise, Kopieren von Kalkulationen und
   Profilen, CSV-Import/-Export.
7. Die Designs A/B/C gelten auch hier. Fachbegriffe werden konsequent gleich benannt.

## 3c. Zwei Konfigurator-Varianten im Shop (zum Testen)

Beide Varianten nutzen **dieselben Daten, dieselbe Preis-Engine und dieselbe Store-API**. Es ändert sich nur die
Darstellung. Optionssets, Produktprofile, Abhängigkeiten und das Verhalten beim Materialwechsel (3.7) gelten in beiden gleich.

| | **Kompakt** (Vorbild Comdatek) | **Geführt** (Vorbild flyeralarm) |
|---|---|---|
| Für wen | Stammkunden und Profis, die wissen, was sie wollen | Neukunden, Gelegenheitskäufer, Beratungsbedarf |
| Aufbau | **eine Seite**, nummerierte Abschnitte untereinander: 1 Material · 2 Format & Auflage (Breite × Höhe, Motive × Stück, Gesamtfläche) · 3 Druck & Weiterverarbeitung | **Reiter je Schritt**: Material · Format & Stückzahl · je Optionsgruppe (aus den Optionssets) · Lieferzeit; nach jeder Wahl automatisch weiter, rechts „Ihr Produkt“ |
| Optionen | Auswahllisten, kurze Hilfe-Symbole; nicht relevante Gruppen verschwinden | Kacheln mit Bild, Kurzinfo, Badges; Infotexte ausklappbar |
| Menge | **zusammen mit dem Format** (Stück und Motive) – daraus ergibt sich früh die Gesamtfläche Breite × Höhe × Stück, auf der die folgenden flächenbezogenen Optionen rechnen (Entscheidung 07.11.2026) | wie kompakt im Format-Schritt; Preismatrix Menge × Lieferzeit nur noch zum Vergleichen (Klick übernimmt beides) |
| Lieferzeit | Auswahlliste mit Datum und Aufschlag | eigener letzter Schritt: Kacheln mit Datum und Aufschlag |
| Preis | live, unten bzw. rechts: Gesamt, je Stück, je m² (netto) | live in der Zusammenfassung und in der Matrix |
| Tastatur | durchgehend per Tastatur bedienbar (Tab, Eingaben), Formatfelder vorne | Mausbedienung im Vordergrund |
| Wiederbestellen | „Konfiguration übernehmen“ aus früheren Bestellungen | wie kompakt |

**Umschalten und Testen**
- Plugin-Einstellung „Standard-Darstellung im Shop“ (kompakt/geführt), dazu je **Produktprofil** überschreibbar
  (z. B. Duschrückwände geführt, Aluverbund kompakt).
- Optional darf der Kunde selbst umschalten („Kompaktansicht“ / „Geführt“). Seine Wahl wird im Kundenkonto gespeichert und
  bei Gästen in einem funktionalen Cookie.
- **Testbetrieb:** Ein Anteil der neuen Besucher bekommt zufällig die andere Variante. Gemessen wird nur mit Einwilligung:
  Konfigurator geöffnet, Preis gesehen, in den Warenkorb, bestellt, Abbruchschritt. Ausgewertet wird je Variante in der
  Administration (Übersicht).
- Technisch: Store-API und Konfigurationsschema sind für beide gleich. Die Varianten sind zwei Storefront-Komponenten
  (Twig + JS-Plugin) auf demselben Zustandsmodell. Ein Wechsel der Variante behält die aktuelle Konfiguration.

**Bewusst nicht übernommen:** die Art von wir-machen-druck (tiefe Kategoriebäume, Kombi-Dropdowns, Preis erst nach
„Berechnen“). Sie ist in der Praxis zu komplex. Die heutige Seite von lohndruckerei.eu ist kein Vorbild. Vorbild für „kompakt“ ist Comdatek.

Datenmodell (v0.4.0): Feld `layout` am Produktprofil (`compact`/`guided`/leer = Standard) und Plugin-Einstellungen
`storefrontLayout`, `allowLayoutSwitch`, `layoutTestShare` (%).

**Anzeige-Schalter** (Freigabe 07.11.2026, „Fokus auf den Gesamtpreis, nicht auf den Aufpreis“): Plugin-Einstellungen
`showPriceHints`, `showArea`, `showPerSqm`, `showPerPiece`, `showFromPrice` (Standard: alles anzeigen), je Produktprofil
überschreibbar (`display`), dazu je Optionsgruppe `showPriceHint` (Aufpreise der Werte ausblenden). Die Store-API liefert sie
im Konfigurator (`display`, `groups[].showPriceHint`); der Preis selbst wird unverändert berechnet.

## 3b. Benutzerrechte je Rolle (für alle Plugins der Familie)

Alle Plugins (LieferLotse, Mensura, künftige) melden ihre Rechte bei der **Shopware-Rollenverwaltung** an
(Einstellungen → Benutzer & Rechte → Rollen). Dort werden Rollen wie *Buchhaltung, Vertrieb, Chef, Admin* je Plugin und
Bereich freigegeben. Menüpunkte, Reiter und Schaltflächen ohne Recht werden ausgeblendet, und die API lehnt solche Zugriffe ab.

Geplante Rechte für Mensura (heute gibt es `ld_mensura` mit Ansehen/Bearbeiten/Anlegen/Löschen):

| Recht | Inhalt | typisch für |
|---|---|---|
| `ld_mensura.catalog` | Kalkulationen, Materialien, Optionen, Sets ansehen/pflegen (nur VK) | Vertrieb (ansehen), Produktmanagement (pflegen) |
| `ld_mensura.profiles` | Produktprofile pflegen | Produktmanagement, Shop-Pflege |
| `ld_mensura.purchase` | **Einkaufspreise, Margen, Deckungsbeitrag** sehen und pflegen, Margenrechner | Chef, Buchhaltung, Einkauf |
| `ld_mensura.customer_groups` | Kundengruppen-Aufschläge | Chef, Vertrieb (Leitung) |
| `ld_mensura.orders` | Rechenweg-Schnappschuss in Bestellungen (mit EK nur zusammen mit `purchase`) | Buchhaltung, Auftragsbearbeitung |
| `ld_mensura.settings` | Plugin-Einstellungen, Import/Export | Admin |

**Wichtig:** EK-Felder liegen heute in denselben Datensätzen wie die VK-Felder (`pricePurchase`, `purchase` in
den Preisanteilen). Damit eine Rolle ohne `ld_mensura.purchase` sie auch über die API nicht sieht und nicht ändert, filtert ein
serverseitiger Schutz die EK-Werte beim Lesen heraus und weist Schreibzugriffe darauf ab. LieferLotse und spätere Plugins
(Auftragsverwaltung, Rechnung, Buchhaltung) folgen demselben Muster.

**Umsetzung (v0.4.0, Admin-Schritt 6):**
- Rollenverwaltung: Raster „Mensura – Katalog“ = Schlüssel `ld_mensura` mit Ansehen/Bearbeiten/Anlegen/Löschen (entspricht
  `ld_mensura.catalog`, Name beibehalten wegen bestehender Rollen); zusätzliche Rechte `ld_mensura.profiles`, `.purchase`,
  `.customer_groups`, `.orders`, `.settings` unter „Zusätzliche Berechtigungen“. Produktprofile und Kundengruppen-Aufschläge sind
  mit dem Katalog nur lesbar; Bestell-Schnappschüsse nur mit `orders`.
- `Service\Security\PurchaseProtectionSubscriber` (Regeln in `PurchaseFieldGuard`) gilt für Benutzer und Integrationen der
  Admin-API ohne Admin-Rolle und ohne `ld_mensura.purchase`; Shop, Warenkorb und Systemprozesse sind nicht betroffen.
  - Lesen: `pricePurchase`, `minimumPricePurchase`, `setupPricePurchase`, `purchaseTotal` = null; in `priceComponents` `purchase`
    = null, in `tiers` die EK-Seite entfernt, im Schnappschuss `breakdown.purchase` und `contributionMargin` entfernt
    (Material, Kalkulation, Optionszuordnung, Preistabellenzeile, Schnappschuss).
  - Suchen: Filtern, Sortieren und Aggregieren über EK-Felder → HTTP 403 `LD_MENSURA__PURCHASE_ACCESS_DENIED`.
  - Schreiben: EK-Feld ändern → 403; bei Preisanteilen und Staffeln wird der EK-Teil aus dem gespeicherten Stand übernommen
    (VK-Änderung löscht keinen EK); Neuanlagen ohne Recht (auch Kopien) übernehmen keinen EK.
  - Testrechner: rechnet intern mit EK, liefert EK-Rechenweg und Deckungsbeitrag nur mit Recht.
- Oberfläche: EK-Felder, Margen und DB nur mit `purchase`; Reiter „Kundengruppen“ nur mit `customer_groups`; Profile bearbeiten
  nur mit `profiles` + Produkte bearbeiten (sonst Hinweis „Nur ansehen“); Bestellkennzahlen der Übersicht nur mit `orders`.
- Geprüft: Unit-Test `tests/Backend/PurchaseFieldGuardTest.php` (check-all) und im laufenden Shop
  `tests/Backend/Shop/purchase-protection.php` (zwei Test-Integrationen ohne/mit Recht, 14 Prüfungen).

Ergänzung für den Logistikanteil (3d), als **Zusatzrechte** (Kategorie „Weitere Rechte“ der Rollenverwaltung):

| Recht | Inhalt | typisch für |
|---|---|---|
| `ld_mensura.logistics` | Logistikanteile sehen: Karte „Logistikanteil“ (nur lesen), Logistik-Zeilen im Admin-Rechenweg und im Schnappschuss, Vectura-Vorschläge ansehen | Chef, Buchhaltung, Einkauf |
| `ld_mensura.logistics_edit` | Logistikanteile pflegen und Vectura-Vorschläge übernehmen (setzt `ld_mensura.logistics` voraus) | Chef, Einkauf |

Anders als die EK-Felder liegt der Logistikanteil von Anfang an in **eigenen Tabellen** (3d.5). Die Entity-Rechte
(`ld_mensura_logistics_rule:read` usw.) gehören nur zu diesen Zusatzrechten, nicht zu `ld_mensura.viewer`. Damit schützt
Shopware ihn in der Admin-API ohne eigenen Filter.

## 3d. Logistikanteil (unsichtbar im VK) – Konzept freigegeben 07.11.2026, Umsetzung läuft (3d.8)

Fachliche Vorlage: `sw6-vectura/docs/LOGISTIKPAUSCHALE.md` (Modell D, Abschnitte 3.2–3.4 und 5). Entscheidung vom
07.11.2026: Versandpauschale (DE 11,90 €, AT 17,90 €) und Verpackungszuschlag bleiben sichtbar und decken die eigene
Abwicklung. Sie werden **getrennt gerechnet**, also nicht angerechnet. Die tatsächlichen Versand- und Servicekosten der
Lieferanten (Grundpaket, Paketmenge, Sperrgut, Express) deckt allein ein **Logistikanteil**, den Mensura unsichtbar in den
VK einrechnet. Ziel ist im Schnitt 20–30 % Marge auf diese Kosten (Zielwert 25 %). Comdatek-Daten aus 500 Aufträgen:
im Schnitt 6,9 % auf den Waren-EK, aber sehr ungleich verteilt (Aluverbund fast 0, Hartschaum 1.200–2.449 mm Sperrgut
ca. 11,63 €/m², 24h-Express mindestens 25 € je Bestellung).

### 3d.1 Woran der Logistikanteil hängt

Ein Logistikanteil hängt an dem, was die Kosten **verursacht**. Jede Regel hat eine **Kostenart**, dieselbe wie in
Vectura. So kann Vectura Kosten und Erlöse je Verursacher und Kostenart gegenüberstellen.

| Ziel | Typische Kostenart | Beispiel |
|---|---|---|
| **Kalkulation** | `base` Grundversand | Folien: 0,43 €/m² für alle Materialien der Kalkulation |
| **Material** | `base`, `bulky` Sperrgut, `quantity` Paketmenge | Hartschaum 5 mm: Grund 1,60 €/m² + Sperrgut 11,63 €/m² mit Rampe 1.000 → 1.400 mm |
| **Optionswert** | `express` (Produktionszeit), selten `bulky` | 24h Express: 4,18 €/m², mindestens 25 € je Bestellung |

Kostenarten: `base` (Grundversand), `quantity` (Paketmenge), `bulky` (Sperrgut), `express` (Express-Service),
`multi_source` (zweite Sendung), `other`. Der Auslandsanteil (Zone AT) läuft weiter über die sichtbare AT-Pauschale.

**Vorrang:** Je Kostenart gilt die spezifischste Regel. Eine Material-Regel `base` ersetzt die Kalkulations-Regel `base`,
eine Material-Regel `bulky` kommt zusätzlich dazu. Regeln an Optionswerten kommen immer dazu, weil sie einen anderen
Verursacher haben. Eine Optionswert-Regel lässt sich auf eine Kalkulation beschränken, z. B. Express bei Hartschaum
anders als bei Folie.

### 3d.2 Form, Rampe, Mengenschwelle, Mindestbetrag

| Feld | Bedeutung | Beispiel |
|---|---|---|
| **Form** | `percent` = % vom Waren-EK der Position (EK-Zwischensumme, Entscheidung 3d.8 Nr. 1) · `per_sqm` = € je abgerechnetem m² der Position · `per_piece` = € je Stück · `per_position` = € je Position | Magnet 12 % · Hartschaum 1,60 €/m² · Acryl groß 29,77 € je Position |
| **Wert** | Betrag netto (VK-Seite, Basis Wiederverkäufer) bzw. Prozentsatz | 11,63 |
| **Rampe längste Seite** (optional, von–bis mm) | Faktor 0 bis „von“, linear steigend bis „bis“, darüber 1. Längste Seite = max(Breite, Höhe) je Stück **inkl. Formatzugaben**. Kein Sprung, auch nicht oberhalb von „bis“ („Rampe weiterführen“). | 1.000 → 1.400 mm: bei 1.200 mm wirkt die Hälfte |
| **Mengenschwelle** (optional, Basis Gesamtfläche m² oder Stückzahl, von–bis) | ebenfalls gleitend: Faktor 0 unter „von“, linear bis „bis“, darüber 1. Der Faktor gilt für die ganze Position. Rampe und Mengenschwelle werden multipliziert. | Papier/Karton: 10 → 15 m² |
| **Mindestbetrag** (optional) + **Geltung** `position` / `order` | Der Anteil der Regel ist mindestens so hoch. `order`: einmal je Bestellung und Optionswert, verteilt auf alle Positionen mit diesem Wert (3d.3). Gedacht für Produktionszeit-Optionen. | Eilproduktion 15 €, 48h 18,75 €, 24h 25 € je Bestellung |
| **Gültig ab** (Datum, optional) | Regeln werden nicht überschrieben. Eine Änderung legt eine neue Zeile mit gültig-ab an. Je Ziel und Kostenart gilt die Zeile mit dem jüngsten gültig-ab ≤ heute. So sind geplante Änderungen möglich, und der Verlauf bleibt für Vectura nachvollziehbar. | ab 01.12.2026 2,10 €/m² |
| **Herkunft** | `manual` oder `vectura` (mit Verweis auf den Vorschlag: Zeitraum, Deckung vorher/nachher, Fallzahl) | „Vectura 01.08.–31.10.: Deckung 1,08 → 1,25, 27 Aufträge“ |

Formel je Regel:

```
L          = max(Breite, Höhe) je Stück inkl. Formatzugaben (mm)
fRampe     = Rampe ? clamp((L − von) / (bis − von), 0, 1) : 1
fMenge     = Schwelle ? clamp((Menge − von) / (bis − von), 0, 1) : 1      (Menge = Gesamtfläche m² oder Stückzahl)
Basis      = percent: Warenwert/100 · per_sqm: abgerechnete Fläche · per_piece: Stückzahl · per_position: 1
Anteil     = Wert × Basis × fRampe × fMenge
Anteil     = max(Anteil, Mindestbetrag)    (Geltung position; bei order siehe 3d.3)
Logistik   = Σ Anteil aller wirksamen Regeln
```

Eine Rampe mit „von“ = „bis“ wäre wieder ein Sprung. Die Administration lässt das nicht zu („bis“ muss mindestens
100 mm größer sein).

### 3d.3 Rechenweg

Neuer Schritt **nach den Optionen und vor dem Mindestpreis** (LOGISTIKPAUSCHALE 3.4 Nr. 3):

```
Grundpreis → Optionen → Staffelrabatt/% → Produktprofil-Aufschlag → Logistik → Mindestpreis → Kundengruppen-Aufschlag → Rundung
```

- Staffelrabatt, %-Preisanteile und Profil-Aufschlag wirken **nicht** auf den Logistikanteil. Er bleibt der Betrag, der
  gepflegt bzw. von Vectura vorgeschlagen wurde. Den **Kundengruppen-Aufschlag** trägt er mit, so zahlt Gewerbe
  verhältnismäßig mit.
- **Mindestpreis:** `Gesamt = max(Mindestpreis, Zwischensumme + Logistik)`. Greift der Mindestpreis, steckt der
  Logistikanteil anteilig darin: `Logistik im Endpreis = Endpreis × Logistik ÷ (Zwischensumme + Logistik)`. So bleibt die
  Summe für Vectura stimmig.
- Nur die **VK-Seite** bekommt einen Logistikanteil. Die EK-Seite rechnet wie bisher, die Ist-Kosten kennt Vectura.
- **Überall gleich:** Produktseite (`calculate`), Preismatrix, Warenkorb (CartProcessor) und Schnappschuss nutzen dieselbe
  Funktion `PriceEngine::price()`. Die Regeln werden mit dem Katalog geladen (`CatalogLoader`, Systemkontext, gültig am
  Rechentag).
- **Mindestbetrag je Bestellung** (`order`): `calculate` und die Preismatrix rechnen die Position allein, also mit dem vollen
  Mindestbetrag. Der CartProcessor sammelt alle Positionen mit demselben Optionswert (z. B. alle mit „24h Express“) und
  verteilt `max(Mindestbetrag, Σ lineare Anteile)` nach den linearen Anteilen, ersatzweise nach Fläche. Ergebnis: Der Preis
  im Warenkorb ist nie höher als auf der Produktseite, bei mehreren Express-Positionen sinkt er. Kommt eine Position hinzu oder
  fällt weg, rechnet der Warenkorb neu. Hat die Lieferzeit-Gruppe `scope = supplier`, wird zusätzlich nach Lieferant
  getrennt (`supplierId` aus Material bzw. Kalkulation).
- **Plausibilität:** Der Rechenweg enthält `logisticsSharePercent` = Logistik im Endpreis ÷ Endpreis. Über der Warnschwelle
  (Plugin-Einstellung `logisticsWarnPercent`, Vorschlag 15 %) warnt die Administration in Vorschau, Testrechner und
  Schnappschuss. Ab dort ist ein Angebot oder ein sichtbarer Sperrgutzuschlag ehrlicher. Der Kunde sieht diese Warnung nie.

### 3d.4 Unsichtbar machen

1. **Keine eigene Zeile für den Kunden.** Der Anteil steckt im Grundpreis (Kalkulation, Material) bzw. im Aufpreis der
   verursachenden Option. Die Summen gehen für den Kunden immer auf.
2. **Aufpreis-Hinweise** (`priceHint` der Konfigurator-Daten, „+ 4,18 €/m²“): Der lineare Teil einer Optionswert-Regel ohne
   Rampe und Schwelle (`per_sqm`, `per_piece`, `per_position`) wird in den Hinweis eingerechnet. Material- und
   Kalkulations-Regeln `per_sqm` ohne Rampe stecken im „ab“-Preis je m² (`fromPricePerSqm`). Nicht lineare Teile (Rampe,
   Mengenschwelle, `percent`, Mindestbetrag) passen in keinen festen Text. Dann zeigt die Administration den Hinweis
   „Aufpreis-Hinweis dieser Gruppe ist nicht exakt“ und empfiehlt, den Aufpreis-Hinweis der Gruppe auszublenden
   (`showPriceHint = false`, gibt es schon). Exakte Hinweise je Konfiguration wären eine eigene Vertragserweiterung und
   kommen vorerst nicht (3d.8 Nr. 3).
3. **Nie nach außen:** Store-API (`configurator`, `calculate`, Matrix), Payload `ldMensura`, Warenkorb, Kundenkonto,
   Dokumente und Lieferantenbestellung (LieferLotse `options[]`/`attributes`) enthalten nur Gesamtpreise. Der
   Logistikanteil steht nur im Admin-Rechenweg und im Logistik-Teil des Schnappschusses, beides mit `ld_mensura.logistics`.
4. **Auch Rollen ohne Recht sehen ihn nicht:** Der Verkaufs-Rechenweg im Schnappschuss (`breakdown.sale`) enthält keine
   Logistik-Zeile, sondern nur die Zwischensumme inklusive Logistik. Die Einzelheiten stehen in der eigenen Tabelle
   `ld_mensura_order_line_item_logistics` (3d.5).
5. **Prüfung im CI:** Ein Negativtest stellt sicher, dass keine Store-API-Antwort in `build/contract/` und kein Fixture ein
   Feld oder einen Text mit `logistic` enthält (wie die Prüfung „kein Einkaufspreis“).

### 3d.5 Datenmodell (Vorschlag, Migration `Migration1794200000LogisticsShare`)

**Neue Tabelle `ld_mensura_logistics_rule`** (Entity `LogisticsRuleDefinition`, nicht `ApiAware`):

| Spalte | Typ | Bemerkung |
|---|---|---|
| `id` | BINARY(16) | |
| `calculation_id` | BINARY(16) NULL, FK → `ld_mensura_calculation` ON DELETE CASCADE | allein = Regel der Kalkulation; zusammen mit `option_value_id` = Beschränkung auf diese Kalkulation |
| `material_id` | BINARY(16) NULL, FK → `ld_mensura_material` CASCADE | |
| `option_value_id` | BINARY(16) NULL, FK → `ld_mensura_option_value` CASCADE | |
| `cost_type` | VARCHAR(32) | `base` · `quantity` · `bulky` · `express` · `multi_source` · `other` |
| `form` | VARCHAR(16) | `percent` · `per_sqm` · `per_piece` · `per_position` |
| `value` | DOUBLE | netto, VK, Basis Wiederverkäufer |
| `ramp_from`, `ramp_to` | INT NULL | mm, längste Seite |
| `threshold_basis` | VARCHAR(16) NULL | `total_area` · `quantity` |
| `threshold_from`, `threshold_to` | DOUBLE NULL | |
| `minimum_amount` | DOUBLE NULL | |
| `minimum_scope` | VARCHAR(16) NULL | `position` · `order` |
| `valid_from` | DATE NULL | NULL = sofort |
| `active` | TINYINT(1) | |
| `source` | VARCHAR(16) | `manual` · `vectura` |
| `source_reference` | JSON NULL | Vectura-Vorschlag: `suggestionId`, Zeitraum, Deckung vorher/nachher, Fallzahl, übernommen von/am |
| `note` | LONGTEXT NULL | |
| `created_at`, `updated_at` | DATETIME(3) | |

Prüfungen: genau ein Ziel (Kalkulation allein, Material oder Optionswert) und gültige Werte der Auswahlfelder prüft der
`LogisticsRuleValidator` beim Schreiben, weil MySQL kein CHECK auf Spalten mit FK-CASCADE erlaubt. Die Datenbank prüft
`ramp_to ≥ ramp_from + 100`, Schwelle vollständig oder leer und `minimum_scope` nur zusammen mit `minimum_amount`.
Indizes auf (`material_id`, `cost_type`, `valid_from`) und entsprechend für die anderen Ziele. **Keine Rück-Assoziation**
an Kalkulation, Material und Optionswert: Die Regel wird nie versehentlich mitgeladen. Der Admin sucht sie mit einem
Filter auf das Ziel, und nur mit `ld_mensura.logistics` (umgesetzt in Schritt 1).

**Neue Tabelle `ld_mensura_order_line_item_logistics`** (1:1 zum Schnappschuss, Extension
`orderLineItem.extensions.ldMensuraLogistics`, nur Admin-API, nur mit `ld_mensura.logistics`):

| Spalte | Inhalt |
|---|---|
| `order_line_item_id`, `order_line_item_version_id` | wie beim Schnappschuss, FK CASCADE |
| `logistics_sale` | Logistikanteil im Endpreis (netto, nach Mindestpreis-Anteil und Kundengruppen-Aufschlag) |
| `production_time_sale` | Produktionszeit-Aufschlag im Endpreis: Preis mit gewählter Lieferzeit − Preis mit der Standard-Lieferzeit, beide ohne Logistik |
| `share_percent` | Logistik im Endpreis ÷ Endpreis |
| `goods_contribution_margin` | Deckungsbeitrag der Ware ohne Logistik-Erlös (`contributionMargin` − `logistics_sale`) |
| `lines` | JSON: je Regel `ruleId`, `costType`, `form`, `value`, Verursacher-Schlüssel (`calculationKey`, `materialKey`, `deliveryKey`), `longestSide`, `fRamp`, `fThreshold`, `amount`, `minimumApplied`, bei `order` der verteilte Anteil |
| `schema_version` | 1 |

`production_time_sale` ist für sich nicht geheim, er ist ja ein sichtbarer Aufpreis. Er steht hier, weil Vectura ihn
zusammen mit dem Logistikanteil braucht und der Schnappschuss ihn bisher nicht getrennt ausweist. Alternative: beide als
Spalten in `ld_mensura_order_line_item`. Das ist einfacher, aber dann sieht jede Rolle mit `ld_mensura.orders` den
Logistikanteil.

**Plugin-Einstellung** `logisticsWarnPercent` (Standard 15). Ein Schalter „Logistik an/aus“ ist nicht nötig: Ohne Regeln
ist der Anteil 0, und alle bisherigen Beispielfälle bleiben unverändert.

### 3d.6 Administration: Karte „Logistikanteil“ (`lm-logistics-card`)

Erscheint auf den Detailseiten von Kalkulation, Material und Optionswert, **nur** mit `ld_mensura.logistics`. Ohne Recht
erscheint sie gar nicht, auch nicht als deaktivierter Platzhalter. Ohne `ld_mensura.logistics_edit` ist sie nur lesbar.
Gestaltung nach `ld-admin-design` (Präfix `lm-`, Tokens, Bausteine `lm-chip`, `lm-status`):

```
┌ mt-card  [regular-truck] Logistikanteil · für Kunden unsichtbar ─────────────── [+ Logistikanteil hinzufügen] ┐
│ ⓘ mt-banner info (schließbar): „Deckt die Versand- und Servicekosten des Lieferanten. Wird unsichtbar in den  │
│   Preis eingerechnet, der Kunde sieht nur den Gesamtpreis. Versandpauschale und Verpackung bleiben getrennt.“ │
│                                                                                                                │
│  Kostenart        Form        Wert          Rampe / Schwelle      gültig ab    Status                          │
│  [Grund   c1]     €/m²        1,60 €        –                     sofort       ✓ aktiv                         │
│  [Sperrgut c2]    €/m²        11,63 €       1.000 → 1.400 mm      01.12.2026   ⚠ geplant                       │
│  [Sperrgut c2]    €/m²        2,10 €        1.000 → 1.400 mm      –            ⊘ abgelöst ab 01.12.             │
│                                                                                                                │
│ ┌ Vorschlag aus Vectura (nur wenn Vectura installiert und Recht vorhanden) ─────────────────────────────────┐  │
│ │ Sperrgut 1.200–2.449 mm: 11,63 €/m², Rampe 1.000 → 1.400 mm · Deckung 1,08 → 1,25 · 27 Aufträge,          │  │
│ │ 01.08.–31.10.2026 · ⚠ Änderung größer als ±1 €/m²    [Vorschau]  [Übernehmen ab 01.12.2026]               │  │
│ └────────────────────────────────────────────────────────────────────────────────────────────────────────────┘  │
│                                                                                                                │
│  Vorschau (Beispielformate änderbar)                                                                           │
│  Format            Menge  VK ohne Logistik  Logistik (unsichtbar)  VK gesamt   Anteil                          │
│  1.000 × 500 mm    1      38,40 €           0,80 €                 39,20 €     2 %   ✓                          │
│  1.500 × 1.000 mm  1      82,50 €           11,13 €                93,63 €     12 %  ✓                          │
│  2.400 × 1.200 mm  2      398,00 €          70,58 €                468,58 €    15 %  ⚠ über 15 %               │
│  [kleine Linie: Satz €/m² über der längsten Seite 0 … 3.000 mm, Rampe sichtbar]                               │
└────────────────────────────────────────────────────────────────────────────────────────────────────────────────┘
```

(Beträge in der Skizze sind Platzhalter.)

**Bearbeiten** im `sw-modal` „Logistikanteil bearbeiten“, Felder mit `help-text` (de/en, je mit Beispiel):

| Feld | Hilfetext (de) |
|---|---|
| Kostenart | „Welche Versandkosten deckt dieser Anteil? Muss zur Auswertung in Vectura passen, z. B. Sperrgut bei langen Hartschaumplatten.“ |
| Form | „Wie der Anteil wächst: % vom Warenwert, € je m², € je Stück oder fest je Position. Z. B. Sperrgut je m², damit kleine Schilder nicht mitbezahlen.“ |
| Wert | „Betrag netto für Wiederverkäufer. Kundengruppen-Aufschläge kommen automatisch dazu. Z. B. 11,63 €/m².“ |
| Rampe längste Seite | „Der Anteil steigt gleitend zwischen diesen Längen von 0 auf den vollen Wert, damit beim Formatvergleich kein Preissprung auffällt. Z. B. 1.000 → 1.400 mm.“ |
| Mengenschwelle | „Der Anteil wirkt erst ab einer Menge und steigt gleitend. Z. B. Papier ab 10 m² Gesamtfläche, voll ab 15 m².“ |
| Mindestbetrag | „Der Anteil ist mindestens so hoch. Z. B. 25 € für 24h Express, weil der Lieferant das je Bestellung berechnet.“ |
| Geltung Mindestbetrag | „Je Position oder einmal je Bestellung, verteilt auf alle Positionen mit dieser Option. Auf der Produktseite gilt immer der volle Betrag, im Warenkorb kann der Preis dann sinken.“ |
| Gültig ab | „Ab diesem Tag gilt der neue Wert. Leer = sofort. Der alte Wert gilt bis dahin und bleibt danach als Verlauf erhalten.“ |
| Nur für Kalkulation (bei Optionswerten) | „Leer = in allen Kalkulationen. Z. B. Express bei Hartschaum anders als bei Folie.“ |
| Notiz | „Warum dieser Wert? Z. B. ‚Comdatek Sperrgut ab 1.200 mm, Auswertung 03–10/2026‘.“ |

- **Warnungen beim Speichern** (`mt-banner attention`): Anteil in der Vorschau über `logisticsWarnPercent`; Rampe kürzer als
  100 mm; Änderung größer als ±1 €/m² bzw. ±2 Prozentpunkte zum gültigen Wert; nicht lineare Regel an einer Option mit
  sichtbarem Aufpreis-Hinweis (3d.4 Nr. 2); Mindestbetrag `order` an einem Optionswert, der keine Lieferzeit ist.
- **Leerer Zustand:** Icon `regular-truck`, „Noch kein Logistikanteil · Lege fest, welche Versandkosten dieses Material
  verursacht, z. B. Sperrgut ab 1.200 mm. · [Logistikanteil hinzufügen]“. Ohne Vectura darunter eine leise Zeile: „Mit
  Vectura bekommst du Vorschläge aus den echten Versandkosten.“
- **Vorschau** rechnet serverseitig über eine Admin-Route (3d.7) mit Demo-Formaten oder frei gewählten Formaten. Die
  Linie „Satz über Länge“ zeichnet der Browser aus den Regelfeldern (dafür sind keine Preise nötig).
- **Kostenart-Farben** (Kategorie-Farben c1–c4): `base` c1, `bulky` c2, `quantity` c3, `express` c4, sonst ohne Farbe.
  Diese Zuordnung kommt in DESIGN.md, sobald Vectura dieselben Chips nutzt (vorher abstimmen).
- **Icon:** `regular-truck` ist in DESIGN.md für „Lieferant“ belegt. Ein eigenes Paket-Icon nur nach Prüfung im
  Icon-Kit und Eintrag in DESIGN.md.
- Später möglich: eigener Bereich **„Logistik“** im Mensura-Menü (`constants.js` AREAS), der alle Regeln als Liste zeigt
  (Filterchips je Kostenart, „geplant“, „aus Vectura“), für die Massenpflege.

### 3d.7 Schnittstellen

- **Admin-Route** `POST /api/_action/ld-mensura/logistics/preview` (ACL `ld_mensura_logistics_rule:read`): Regel(n) im
  Entwurf + Beispielkonfigurationen → je Beispiel VK ohne/mit Logistik, Anteil, Warnung. Der Testrechner (Fahrplan 4)
  nutzt später dieselbe Rechnung und zeigt Logistik-Zeilen nur mit Recht.
- **Vectura → Mensura:** Mensura ruft `GET /api/_action/ld-vectura/logistics/suggestions` nur auf, wenn Vectura installiert
  ist. Rechte, Antwortformat und Übernahme stehen in [INTEGRATION.md](INTEGRATION.md) Abschnitt 7.
- **Mensura → Vectura:** Schnappschuss-Teil `ld_mensura_order_line_item_logistics` (3d.5).
- **Vertrag Backend ↔ Frontend (`docs/contract/`): keine Schemaänderung.** Der Logistikanteil erscheint dort nicht. Es
  ändern sich nur Gesamtpreise und Aufpreis-Hinweise, und das nur für Kalkulationen mit Regeln. Dazu kommen ein
  Beispielfall mit Logistikregel in den Demo-Daten (Sollwert nur als Gesamtpreis, Herleitung im Commit) und der Negativtest
  aus 3d.4 Nr. 5. Dafür reicht ein kleiner Branch `contract/logistik-beispiel`.

### 3d.8 Umsetzungsplan (kleine Schritte, je Schritt im Shop testen, committen, pushen)

| # | Branch | Inhalt | Test |
|---|---|---|---|
| 1 | `backend/logistikanteil` | Migration + Entity `ld_mensura_logistics_rule`, Assoziationen (nicht ApiAware), Zusatzrechte `ld_mensura.logistics` / `logistics_edit`, Snippets de/en | `dal:validate`; Regel über Admin-API anlegen; Rolle ohne Recht bekommt 403 |
| 2 | `contract/logistik-beispiel` → `main` | Beispielfall (z. B. Demo-Material mit Sperrgut-Rampe, Format 1.500 × 1.000) nur mit Gesamtpreis; Negativtest „kein logistic in Store-API/Fixtures“ in `check-all.sh` | Vertragsprüfung grün, Backend-Test erwartungsgemäß rot |
| 3 | `backend/logistikanteil` | Engine: Regeln im `CatalogLoader` (gültig am Rechentag, Vorrang 3d.1), Schritt „Logistik“ in `PriceEngine::price()` (nur VK), Mindestbetrag `position`, Rechenweg-Zeilen intern; Demo-Daten | PHPUnit: Rampe, Schwelle, Formen, Vorrang, gültig-ab, Mindestpreis-Anteil; alle alten Fixtures unverändert, neuer Fixture grün |
| 4 | `backend/logistikanteil` | Unsichtbarkeit: `breakdown.sale` ohne Logistik-Zeile, Aufpreis-Hinweise und „ab“-Preis mit linearem Teil, Negativtest grün | Store-API im Shop prüfen: Gesamtpreis stimmt, kein Logistikfeld |
| 5 | `backend/logistikanteil` | Schnappschuss `ld_mensura_order_line_item_logistics` inkl. Produktionszeit-Aufschlag | Testbestellung, Admin-API mit und ohne Recht |
| 6 | `backend/logistikanteil` | Mindestbetrag `order`: Verteilung im CartProcessor | zwei Express-Positionen im Warenkorb, Summe = max(Minimum, Σ linear) |
| 7 | `backend/logistikanteil` | Admin-Route `preview` + Karte `lm-logistics-card` (Liste, Modal, Hilfetexte, Vorschau, Warnungen, Einstellung `logisticsWarnPercent`), eingebunden in Kalkulation/Material/Optionswert | im Admin in Design A/B/C, mit/ohne Recht, schmales Fenster |
| 8 | `backend/vectura-vorschlaege` (nach Vectura-Schritt 4) | Karte „Vorschlag aus Vectura übernehmen“ gegen das Antwortformat aus INTEGRATION.md 7; bis dahin mit Beispiel-JSON | ohne Vectura unsichtbar; mit Vectura legt „Übernehmen“ eine neue Regel mit gültig-ab an |

**Stand 08.11.2026:** Schritte 1–8 umgesetzt. Schritt 8 (auf `backend/logistikanteil` statt eigenem Branch, gegen das
Beispiel-JSON, weil Vectura die Route noch nicht liefert): Bereich „Vorschläge aus Vectura“ in der Karte, Zuordnung über
die Schlüssel (`service/vectura-suggestions.js`, Node-Test `tests/Backend/vectura-suggestions.test.mjs`), „Übernehmen ab
…“ öffnet den Dialog vorbelegt (Standard: nächster Montag), gespeichert mit `source = vectura` und `sourceReference`.
Nicht stabile Vorschläge nur zur Information; Schrittgrenze prüft Mensura zusätzlich. Ohne Vectura unsichtbar;
Beispieldaten im Browser mit `localStorage["ld-mensura.vectura-demo"] = "1"`.
Davor: Schritte 1–7 umgesetzt (Schritt 7: Reiter „Logistik“ an Kalkulation, Material und Optionsgruppe
(Wert wählen) mit `lm-logistics-card`; Karte speichert eigenständig, Änderung einer gültigen Regel legt eine neue Zeile
mit „gültig ab“ an; Vorschau über `POST /api/_action/ld-mensura/logistics/preview` (Entwurf + gespeicherte Regeln,
`LogisticsPreview`); Plugin-Einstellung `logisticsWarnPercent`; Testrechner zeigt „davon Logistikanteil“ nur mit Recht.
Admin-Build und Bedienung im Shop noch nicht geprüft, Templates mit dem Vue-Compiler geprüft).
Davor: Schritte 1–6 umgesetzt (Schritt 6: `OrderMinimum` verteilt den Mindestbetrag je Bestellung;
CartProcessor und Bestell-Schnappschuss rechnen erst jede Position allein, dann mit den verteilten Anteilen; eine einzelne
Position behält den vollen Betrag; Gruppen mit `scope = supplier` werden je Lieferant getrennt).
Davor: Schritte 1–5 umgesetzt (Schritt 4: linearer Anteil in Aufpreis-Hinweis und „ab“-Preis, gleichartige
Aufpreise eines Werts zu einem Betrag zusammengefasst; Schritt 5: `ld_mensura_order_line_item_logistics` wird beim
Bestellen geschrieben, `goodsContributionMargin` zusätzlich vom EK-Schutz behandelt, Recht `ld_mensura.logistics` liest ihn) (Branch `backend/logistikanteil`, Vertrag `contract/logistik-beispiel`),
Unit- und Vertragstests grün, Migration in einer Test-Datenbank geprüft. Offen: Test im laufenden Shop (`dal:validate`,
Admin-API mit/ohne Recht, `mensura:demo:seed`, Store-API Acryl 1.500 × 1.000 = 120,16 €). Der Admin-Testrechner zeigt den
Anteil bis Schritt 7 nicht (er nutzt den Schnappschuss-Rechenweg ohne Logistik).

Schritt 7 braucht die Detailseiten von Material, Kalkulation und Optionswert. An denen arbeitet gerade eine andere Sitzung
(nicht committet). Die Karte wird als eigenständige Komponente gebaut und erst eingebunden, wenn diese Seiten auf `main` sind.

**Entscheidungen (07.11.2026, Plan freigegeben):**
1. **Basis von `percent`** ist der Waren-**EK** der Position (EK-Zwischensumme vor Mindestpreis). Das passt zu Vectura
   („6,9 % auf den Waren-EK“) und bleibt bei Margenänderungen stabil. Fehlt der EK (0), wirkt die Regel nicht, und die
   Administration warnt.
2. **Mindestbetrag je Bestellung** wie in 3d.3: Produktseite und Matrix mit vollem Betrag, im Warenkorb verteilt.
3. **Aufpreis-Hinweise:** Der lineare Teil steht im Hinweis. Bei nicht linearen Regeln empfiehlt die Administration, den
   Hinweis der Gruppe auszublenden. Exakte Hinweise je Konfiguration kommen vorerst nicht.
4. **Rechte und Schnappschuss:** zwei Zusatzrechte (`ld_mensura.logistics`, `ld_mensura.logistics_edit`) und die eigene
   Tabelle `ld_mensura_order_line_item_logistics`.
5. **Deckungsbeitrag:** Der Logistik-Schnappschuss weist zusätzlich `goodsContributionMargin` aus, also den
   Deckungsbeitrag der Ware ohne Logistik-Erlös (= `contributionMargin` − `logisticsSale`). Er steht dort, weil er den
   Logistikanteil verrät.

## 4. Standardformel (Engine)

```
B, H           = Endformat + Formatzugaben der Optionen (mm), ggf. gedreht
Fläche         = max(Mindestfläche, aufrunden(B × H / 1e6 × Menge, Flächenrundung)) ÷ Menge   (je Position, Standard;
                 bei areaScope = piece wie bisher je Stück)
Umfang         = 2 × (B + H) / 1000
Menge          = Motive × Stück je Motiv
Satz/m²        = (Materialpreis[Staffel] + Σ per_area) × Π factor
Stückpreis     = Satz/m² × Fläche + Grundpreis/Stk + Σ per_piece + Umfang × Σ per_perimeter
Zwischensumme  = Stückpreis × Menge + Motive × (Grundpreis/Motiv + Σ per_motif) + Rüstkosten + Σ per_order
Zwischensumme  = Zwischensumme × (1 − Staffelrabatt) × (1 + Σ percent) × (1 + Preisfaktor)
Logistik       = Σ Logistikanteile (nur VK, 3d.2)                                   (geplant, 3d)
Gesamt         = runden(max(Mindestpreis, Zwischensumme + Logistik))
```

Die Engine rechnet dieselbe Formel zweimal – mit VK-Werten (Kundenpreis) und mit EK-Werten
(Einkauf) – und liefert den Rechenweg samt Deckungsbeitrag. Alle Preisanteile (per_area, per_piece …)
kommen aus Material, Kalkulation und den gewählten Optionswerten, jeweils nach Staffel aufgelöst.

**Umsetzung v0.4.0** (`src/Service/Calculation/PriceEngine.php`), festgelegte Details:

| Thema | Regel |
|---|---|
| Reihenfolge der Prüfung | Material (muss im Profil erlaubt sein, sonst Standard + Hinweis `material.reset`) → Festformat → Optionen in Set-Reihenfolge (Start-Set → Material-Set → Folge-Sets der gewählten Werte) → entfallene Optionen (`option.removed`) → Format (`format.*`, mit `fix`) → Menge (`quantity.*`) → Lieferzeit (`delivery.reset`) → Preis (`price.*`) |
| Optionswerte | erlaubt = in der Kalkulation zugeordnet (Zeile je Material vor allgemeiner) ∩ Profilregel; Bedingungen gegen die bereits gewählten Werte; sonst Standard (Profil-Standard → `isDefault` → erster) mit Hinweis `option.reset` / `option.fixed` / `option.excluded` |
| Staffeln | `tiers` im alten Format (Liste, Basis aus `tierBasis`) oder getrennt `{purchase:{basis,steps}, sale:{basis,steps}}`; Schritt `price` ersetzt, `discount` gibt Rabatt %. Material-Staffel ersetzt den Materialpreis; Kalkulations-Staffel mit `price` ebenso (wenn das Material keine hat), mit `discount` = Staffelrabatt auf die Zwischensumme; Staffel am Optionswert ersetzt dessen ersten Preisanteil (bzw. `base_area`) |
| Fläche (Entscheidung 07.11.2026) | **Fläche der Position = Länge × Breite × Stück** (inkl. Formatzugaben). Mindestfläche und Flächenrundung gelten standardmäßig für diese Gesamtfläche (`areaScope = total`), z. B. Folie: bis 1 m² gleicher Grundpreis, darüber in Schritten von `areaRounding` (0,1 m² → 1,23 m² wird 1,3 m²). Alle Preisanteile je m² (Material, Laminat, Schnitt …) rechnen mit dieser abgerechneten Fläche, Preisanteile je Stück mit der Stückzahl. `areaScope = piece` rechnet Mindestfläche/Rundung wie früher je Stück. Die Flächenwerte (je Stück, gesamt, abgerechnet, Mindestfläche, Schrittweite, Format, Umfang) stehen im Rechenweg, im Payload `ldMensura.area` und im Schnappschuss – für eigene Formeln und spätere Plugins (z. B. Versanddienstleister nach Format). `price.area` der Store-API ist die Gesamtfläche L × B × Stück |
| Gewicht (07.11.2026) | Gewicht je Stück = Fläche je Stück × `weightPerSqm` des Materials (kg); gesamt × Stück. Steht im Rechenweg, im Payload `ldMensura.weight`, im Schnappschuss und in der Shopware-Lieferinformation der Warenkorbposition (Versandregeln, späteres Versand-Plugin für Versand- und Verpackungskosten). Offen: Gewichtszuschläge durch Optionen (z. B. Laminat g/m²) und Verpackung |
| Bruttopreis | `price.totalGross` = Netto × (1 + Steuersatz); Steuersatz aus der Steuer des Produkts nach den Steuerregeln des Kunden (wie im Warenkorb, z. B. 0 % bei steuerfreier Lieferung). Ohne bekannten Steuersatz entfällt das Feld |
| „ab“-Preis | `fromPricePerSqm` = (Materialpreis + Preisanteile je m² aus Kalkulation, Material, Produktprofil) × (1 + Profil-Aufschlag % + Profil-Material-Aufschlag %) × (1 + Kundengruppen-Aufschlag %), ohne Optionen, Staffeln und Mindestpreis |
| `base_area` | Optionswert ersetzt den Materialpreis je m² samt Material-Staffel (Sticker-Zuschnitt) |
| Preistabelle | spezifischste passende Tabelle (alle Schlüsselwerte gewählt; mehr Schlüssel und Material vor ohne); Zeile mit größter Auflage ≤ Menge (darunter die erste); `total` wird auf den Stück-Preis der Zeile umgerechnet. Hat die Kalkulation Tabellen, aber keine passt → `price.not_available` |
| Aufschläge VK | Produktprofil (% + je Material %) vor dem Mindestpreis; Mindestpreis = max(Material bzw. Kalkulation, Profil); **Kundengruppen-Aufschlag nach dem Mindestpreis** (auch der Mindestpreis steigt), danach Rundung (Regel der Kundengruppe vor Kalkulation, sonst Cent) |
| Bestpreisregel | Schwellen aus Staffeln (Basis Menge oder Gesamtfläche) und Preistabellen; kostet eine größere Menge weniger: `hint` → Hinweis `price.best_price` mit `fix: {perMotif}`, `apply` → günstigerer Preis gilt (`price.best_price_applied`) |
| Lieferzeit | Gruppe vom Typ `delivery`, Wert mit Werktagen und `availability` `{maxQuantity, maxArea, materialIds}`; Termin ab heute bzw. nach Annahmeschluss/an freien Tagen ab dem nächsten Arbeitstag |

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
2a. ✅ v0.3.0: Optionssets (Konfigurationsfluss), Produktprofile (allgemein/speziell, Materialien über Kalkulationen,
   Optionsregeln), Motiv-/Staffel-/Bestpreis-Modus, Preisanteile am Material, Festformate
2b. ✅ v0.4.0 (Datenmodell): Preistabellen (Flyer, Broschüren); getrennte EK/VK-Staffeln; aus dem
   Wettbewerbsvergleich ([WETTBEWERB.md](WETTBEWERB.md)): Verfügbarkeitsregeln und Annahmeschluss für Lieferzeiten,
   Arbeitstage-Kalender, Einrichtepauschale, Badges je Material/Wert, Referenztext je Position (`reference` in der
   Konfiguration), Darstellung je Produktprofil; für den künftigen **Produktberater** (INTEGRATION.md Abschnitt 6):
   Merkmal-Katalog + Merkmalswerte, Anwendungsfälle, Zubehörregeln mit Mengenregel.
   Offen aus 2b: CSV-Import/-Export der Preistabellen (mit Schritt 3), Versand im Preis, Services (Muster, Proof,
   Datencheck – nie vorausgewählt; als Optionsgruppe ohne Pflicht abbildbar)
3. Admin: Listen & Detailseiten je Bereich, Preisfelder EK/VK mit Margenrechner
4. ✅ v0.4.0: Preis-Engine (`src/Service/Calculation`, reines PHP) mit PHPUnit-Vertragstest gegen
   `docs/contract/fixtures` (Comdatek-Werte auf den Cent) und Unit-Tests der Einzelregeln; offen: Testrechner im Admin,
   eigene Formeln (Ausdrucks-Parser), Formatklassen für Preistabellen (freies Format → nächste Klasse)
5. ✅ v0.4.0: Store-API `configurator`, `calculate` (mit Preismatrix) und `preset/validate` laut `docs/contract`;
   Demo-Daten `bin/console mensura:demo:seed`; `cart/add` + CartProcessor (Preis serverseitig bei jeder
   Warenkorbberechnung), Produktseite lädt das Profil, Rechenweg-Schnappschuss mit EK/VK bei der Bestellung;
   offen: Store-API `catalog`
6. Storefront-Konfigurator in **zwei Varianten** (3c): **kompakt** (Comdatek: eine Seite, 4 nummerierte Abschnitte) und
   **geführt** (flyeralarm: Reiter je Optionsset, Kacheln, Zusammenfassung, Preismatrix Menge × Lieferzeit), beide mit
   Live-Preis, Liefertermin + Annahmeschluss, Konfiguration in der URL, Angebot als PDF, Upload; umschaltbar je Profil,
   optional durch den Kunden, mit Testbetrieb; dazu Warenkorb/Bestellung + Schnappschuss
6a. LieferLotse: Feld `attributes` in der Lieferantenposition + Mensura-Subscriber auf `SupplierOrderBuiltEvent`
6b. Logistikanteil (unsichtbar im VK, Vorschläge aus Vectura): Konzept und Schritte in 3d, Schnittstelle INTEGRATION.md 7
7. Weitere Artikel (Folien, Banner …) – eigene Kalkulationen/Formeln
