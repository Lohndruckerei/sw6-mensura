# Produktbeispiele Comdatek – Berechnungsarten

Stand: 07.11.2026. Ermittelt über die Preis-API des Comdatek-Konfigurators (connect.comdatek.de, VK netto).
Damit wird geprüft, ob das Datenmodell (v0.2.0) alle Rechenarten abbildet. Die Einkaufspreise stehen in
der Excel-Liste (siehe KONZEPT 2a).

## Was der Comdatek-Konfigurator grundsätzlich kennt

Preisaufruf: `Produkt / Material / Festformat / Breite / Höhe / Motive / Stück / Optionswerte / Druckpositionen`

| Baustein | Beispiel | in Mensura |
|---|---|---|
| Material je Produkt | Banner: Frontlit 510 g, Mesh, Backlit, Blockout, Textil | ✅ Kalkulation ↔ Material |
| freies Format mit Grenzen | Banner 100–20.000 × 100–60.000 mm | ✅ |
| **Festformate**, die Optionen sperren | Falzflyer: „DIN A4 hoch“ sperrt „Mittelfalz horizontal“ | ⚠️ nur `fixedFormats` (JSON) ohne Sperren → siehe Lücke 3 |
| Format kommt aus einer Option | `format_type 3`: Optionswert hat eigene Breite/Höhe | ⚠️ Lücke 3 |
| **offenes/fertiges Format** | Falz vertikal = Breite × 2, horizontal = Höhe × 2 | ✅ `widthFactor`/`heightFactor` |
| Ausschlüsse zwischen Optionen | `exclusions`, gesperrte Kombi liefert Preis 90.000.000 | ✅ `conditions.excludes` |
| **Druckpositionen** (Mehrfachauswahl) | Textil: Brust, Rücken, Ärmel … mit Maßen | ❌ Lücke 5 (erst bei Textilprodukten) |
| Motive × Stück | Flyer: 2 × 500 kostet wie 1 × 1.000 | ⚠️ Lücke 4 |

## Die vier Rechenarten

### A · Fläche (Platten, Banner, Etiketten) – passt ins Modell

| Produkt | Formel (VK) |
|---|---|
| Aluverbund | (Material + Optionen) €/m² × Fläche + 0,50 €/Stk, min. 12,50 € |
| PVC-Banner 510 g | **Staffel über die Gesamtfläche der Position**: < 10 m² 5,55 · ab 10 m² 5,25 · ab 25 5,05 · ab 50 4,70 · ab 100 4,55 · ab 200 4,45 €/m²; kein Stückpreis; min. 7,00 € |
| Mesh-Banner | < 10 m² 5,25 · ab 10 m² 4,85 €/m² **+ 0,20 €/Stk** |
| Blockout-Plane | 7,55 €/m² + 0,20 €/Stk, min. 8,90 € (im **EK** dagegen „unter/über 1 m² je Stück“!) |
| Banner-Konfektion | **je m², nicht je lfm**: rundum umsäumt +1,00 €/m², umsäumt + Ösen alle 50 cm +1,30 €/m² (gleicher Preis bei 3×3 m, 9×1 m, 4,5×2 m) → offene Rückfrage geklärt |
| Etiketten (Haftfolie PP 60 µ) | nur Fläche zählt, nicht die Form (50×50 = 25×100 = 20×125); Staffel über Gesamtfläche: bis < 10 m² ≈ 6,80 €/m², ab 10 m² 2,41 €/m²; Konturschnitt + ≈ 2,33 €/m² |

**Staffelsprung:** Bei Etiketten kosten 3.000 Stück 51,00 €, 4.000 Stück aber nur 24,10 €. Mensura sollte eine
**Bestpreisregel** haben: Wenn eine größere Menge weniger kostet, gilt deren Preis („mehr bestellen lohnt sich“)
bzw. wird der Kunde darauf hingewiesen.

### B · Stückpreis nach Auflagenstaffel und Formatklasse (Flyer, Falzflyer) – neu

Flyer plano, 135 g matt, 4/0:

| Auflage | 100 | 250 | 300 | 500 | 1.000 | 1.500 | 2.500 | 5.000 | 10.000 |
|---|---|---|---|---|---|---|---|---|---|
| DIN A4 | 11,00 | 25,00 | 30,00 | 45,00 | 60,00 | 75,00 | 100,00 | 200,00 | 400,00 |

- Preis je Stück nach **Mengenstaffel** (0,11 → 0,04 €), Zwischenmengen über die Stufe darunter (300 Stk = 300 × 0,10).
- **Formatklasse statt Fläche:** A4 1.000 Stk = 60 €, A5 = 45 €, A6 = 25 €; ein freies Format 200 × 280 oder 215 × 300 kostet
  wie A4. Das Format wird also auf die nächste Formatklasse gerundet.
- Material und Farbigkeit ändern die Tabelle: 170 g = 65 €, 350 g = 100 €, 4/4 = 70 € (A4, 1.000 Stk).
- **Motive teilen sich die Staffel:** 2 Motive × 500 = 60 € = 1 × 1.000.
- Falzflyer: Festformat (fertig) + Falzart → offenes Format; DIN A4 gefalzt (offen A3) 1.000 Stk = 90 €, A5 gefalzt = 65 €.

### C · Seiten × Auflage (Broschüren) – neu

Broschüre Drahtheftung A5, 100 g Offset:

| Seiten | 100 Stk | 500 Stk |
|---|---|---|
| 8 | 69,55 | 117,77 |
| 16 | 78,55 | 192,77 |
| 32 | 146,55 | 312,77 |
| 64 | 254,55 | 552,77 |

Der Preis ist weder linear zu Seiten noch zur Auflage, also eine Tabelle oder eine eigene Formel (Bogenrechnung). Dazu
kommen Umschlag (Material und Farbigkeit) als zweite Material-/Optionsebene und die Bindungsart.

### D · Laufmeter und Sonderfälle

- Textilbanner mit Gummilippe: Näherei je Umfang (EK „Preis/LFM“), Verpackung „auf Kern gerollt“.
- Textil (T-Shirts, Hoodies): Druckpositionen, Größen und Farben, Stückpreis + Druck je Position.

## Lücken im Datenmodell v0.2.0 → Vorschlag v0.3.0

| # | Lücke | Vorschlag |
|---|---|---|
| 1 | EK und VK haben teils **unterschiedliche Staffelarten** (Blockout: EK je Stückfläche, VK fest) | `tiers` getrennt nach EK und VK, je mit eigener Staffelbasis: `{purchase: {basis, steps}, sale: {basis, steps}}` |
| 2 | **Preistabellen** (Flyer, Broschüren): Stückpreis bzw. Gesamtpreis je Auflage für Kombinationen aus Formatklasse × Material × Farbigkeit × Seiten | neue Tabelle `ld_mensura_price_table` (je Kalkulation, Schlüssel = Material + gewählte Optionswerte, Zeilen = Auflage → EK/VK, Art „je Stück“ oder „gesamt“) mit **CSV-Import/-Export** (Pflege per Excel) |
| 3 | **Festformate als Optionen** mit Sperren und Formatklassen | Optionsgruppe `type = format`: Werte mit Breite/Höhe; freie Formate werden der nächsten Klasse zugeordnet; Sperren über `conditions` wie bei allen Optionen |
| 4 | **Staffelbezug bei Motiven**: Flyer = Summe aller Motive, andere ggf. je Motiv | Feld `tierScope` an der Kalkulation: `total` (Standard) oder `per_motif` |
| 5 | **Druckpositionen** (Mehrfachauswahl mit Maß) | Optionsgruppe `displayType = multi` mit Preis je gewählter Position; erst wenn Textil kommt |
| 6 | **Bestpreisregel** bei Staffelsprüngen | Schalter an der Kalkulation: „günstigere größere Menge anbieten/anwenden“ |
| 7 | Grundpreis je Stück hängt am Material (Mesh 0,20 €, PVC 510 g 0 €) | `priceComponents` auch am Material (heute nur über Optionspreis je Material möglich) |
| 8 | Zweite Materialebene (Umschlag bei Broschüren) | Optionsgruppe mit Material-Bezug oder Kalkulation mit `coverMaterial`; klären, wenn Broschüren kommen |

Rechenart A ist mit 1, 6 und 7 vollständig abgebildet. Für B und C braucht es 2, 3 und 4. Die eigene Formel bleibt die
Rückfalloption für Produkte, die in keine Tabelle passen (z. B. Bogenrechnung).
