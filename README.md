# Mensura – Konfigurator & Kalkulation

Shopware-6.7-Plugin der Lohndruckerei (technischer Name `LdMensura`).

*Mensura* (lat. „Maß“) ist ein Produkt-Konfigurator für Folien, Platten und weitere Druckprodukte:
Der Kunde wählt Material, Format, Auflage und Weiterverarbeitung und sieht sofort seinen Preis.
Im Backend werden dafür Kalkulationen mit eigenen Berechnungsgrundlagen gepflegt – nach Stückzahl,
Fläche, Staffeln, Mindestpreisen und Optionsaufschlägen, bei Bedarf mit eigener Formel.

- Konzept, Kalkulationsfelder und Formeln: [docs/KONZEPT.md](docs/KONZEPT.md)
- Administration: eigener Menüpunkt **Mensura** (Übersicht, Produktprofile, Kalkulationen, Materialien, Optionen,
  Optionssets), Reiter „Mensura“ in der Produktdetailseite,
  Design A/B/C umschaltbar (Plugin-Konfiguration als Standard, Schalter „Design“ je Benutzer)

## Stand

Version 0.4.0 – Grundgerüst (Hauptmenü mit Untermenüs, Design A/B/C), Datenmodell (26 Tabellen `ld_mensura_*`,
per Admin-API nutzbar, Rechte für die Rollenverwaltung) und **Preis-Engine mit Store-API**:

- Stammdaten: Kalkulationen, Materialien, Optionsgruppen/-werte, Optionssets (Konfigurationsfluss), Produktprofile
  (allgemeine und spezielle Produkte, Darstellung kompakt/geführt), Kundengruppen-Aufschläge, **Preistabellen**
  (Flyer, Broschüren), getrennte EK/VK-Staffeln, Einrichtepauschale, Lieferzeit-Verfügbarkeit, Annahmeschluss und
  Arbeitstage, Bestell-Schnappschuss; für den künftigen Produktberater **Merkmale, Anwendungsfälle, Zubehörregeln**
- Preis-Engine (`src/Service/Calculation`, reines PHP): Standardformel, Normalisierung mit Hinweisen/Fehlern
  (feste `code`s), Mindestpreis, Kundengruppen-Aufschlag, Bestpreisregel, Motiv-Modus, EK parallel
- Store-API: `GET /store-api/ld-mensura/product/{id}/configurator`, `POST /store-api/ld-mensura/calculate`
  (mit Preismatrix), `POST /store-api/ld-mensura/preset/validate` – ohne Einkaufspreise
- Demo-Daten: siehe Abschnitt **Demo**

## Demo

Beide Befehle sind wiederholbar (feste IDs, keine Doppelungen) und lassen sich in beliebiger Reihenfolge ausführen.

- `bin/console mensura:demo:seed` – Basis-Demo passend zu den Vertrags-Beispielfällen (`docs/contract/fixtures`):
  „Aluverbund (Mensura-Demo)“ und „Folie (Mensura-Demo)“. Nicht ändern, die Prüfungen rechnen damit.
- `bin/console mensura:demo:comdatek` – Sortiment aus der **Comdatek-Einkaufspreisliste 2026** (Daten im Code,
  `src/Service/Demo/ComdatekCatalog.php`):
  - je Excel-Gruppe eine Kalkulation (`cd-…`): Aluverbund, Kaschierte Platten, Acryl, Hartschaum 1–3 / 5–10 mm / schwarz,
    Hart-PVC & Polystyrol, Hohlkammer, Displaykarton & DISPA, Re-Board, Stadur, Banner, Textilbanner – 50 Materialien
  - **EK = Excel, VK = EK ÷ 0,75** (25 % Marge, auf Cent) für Materialpreise, Preisanteile, Staffeln und Mindestpreise;
    VK-Regel der Kalkulationen: Zielmarge 25 %, Korridor 20–30 %
  - **Logistikpauschale** (Vectura-Konzept 5.1, Modell D) als Preisanteil je m² jeder Kalkulation, VK = Vorschlag je
    Materialgruppe, EK = Versand-EK ÷ Fläche; im Shop nicht getrennt ausgewiesen. Der Wert kommt später aus Vectura.
  - 14 Shopware-Produkte mit Profil in der Kategorie „Mensura-Demo“ (je Gruppe eins plus „Plattendirektdruck“ über alle
    Plattenkalkulationen; Platten kompakt, Banner geführt), Steuer 19 %, sichtbar in jeder Storefront
  - mit aktivem **LieferLotse**: Lieferant „Comdatek GmbH“ (`COMDATEK`), Bestellweg „Manuell im Portal“
    (connect.comdatek.de – es wird nichts gesendet), Produktzuordnungen mit Comdatek-Produkt-ID; in Mensura
    `supplierId` und `supplierCodes` (Comdatek-Material-IDs) an Kalkulationen und Materialien
  - setzt Optionssets, Optionszuordnungen der Kalkulationen sowie Materialien und Optionsregeln der Profile seiner
    Produkte auf den Stand im Code zurück; gemeinsame Optionsgruppen werden nur ergänzt (z. B. Gruppe „Konfektion“)
  - Prüfen im laufenden Shop: `tests/Backend/Shop/comdatek-demo.php` (Store-API, Sollwerte)

Schnittstellen zu LieferLotse und künftigen Plugins: [docs/INTEGRATION.md](docs/INTEGRATION.md).
Fahrplan und nächste Schritte: siehe Konzept.

## Deinstallation

Ohne „Daten behalten“ löscht Mensura alle `ld_mensura_*`-Tabellen – **auch die Rechenweg-Schnappschüsse der
Bestellungen** (EK, VK, Deckungsbeitrag je Position). Bestellungen selbst (Preis, Menge, Konfiguration im Payload)
bleiben in Shopware erhalten. Werden die Schnappschüsse für Buchhaltung oder Auswertungen gebraucht, vorher
sichern, z. B. `mysqldump shopware ld_mensura_order_line_item > mensura-schnappschuesse.sql`, oder mit
„Daten behalten“ deinstallieren.

## Entwicklung

Backend und Frontend werden getrennt entwickelt und über einen Vertrag (`docs/contract/`) automatisch abgeglichen:
siehe [docs/ENTWICKLUNG.md](docs/ENTWICKLUNG.md). Alle Prüfungen lokal:
`docker exec shopware bash -c "cd /var/www/html/custom/plugins/LdMensura && bash .github/scripts/check-all.sh"`

```bash
docker exec shopware bash -c "cd /var/www/html && bin/console plugin:refresh && bin/console plugin:install --activate LdMensura"
docker exec shopware bash -c "cd /var/www/html && bin/build-administration.sh"
```
