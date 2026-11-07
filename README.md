# Mensura – Konfigurator & Kalkulation

Shopware-6.7-Plugin der Lohndruckerei (technischer Name `LdMensura`).

*Mensura* (lat. „Maß“) ist ein Produkt-Konfigurator für Folien, Platten und weitere Druckprodukte:
Der Kunde wählt Material, Format, Auflage und Weiterverarbeitung und sieht sofort seinen Preis.
Im Backend werden dafür Kalkulationen mit eigenen Berechnungsgrundlagen gepflegt – nach Stückzahl,
Fläche, Staffeln, Mindestpreisen und Optionsaufschlägen, bei Bedarf mit eigener Formel.

- Konzept, Kalkulationsfelder und Formeln: [docs/KONZEPT.md](docs/KONZEPT.md)
- Administration: eigener Menüpunkt **Mensura** (Übersicht, Kalkulationen, Materialien, Optionen),
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
- Demo-Daten passend zu den Vertrags-Beispielfällen: `bin/console mensura:demo:seed`

Schnittstellen zu LieferLotse und künftigen Plugins: [docs/INTEGRATION.md](docs/INTEGRATION.md).
Nächste Schritte: Admin-Oberflächen mit Testrechner, Storefront-Konfigurator (parallel), Warenkorb/Schnappschuss
(siehe Fahrplan im Konzept).

## Entwicklung

Backend und Frontend werden getrennt entwickelt und über einen Vertrag (`docs/contract/`) automatisch abgeglichen:
siehe [docs/ENTWICKLUNG.md](docs/ENTWICKLUNG.md). Alle Prüfungen lokal:
`docker exec shopware bash -c "cd /var/www/html/custom/plugins/LdMensura && bash .github/scripts/check-all.sh"`

```bash
docker exec shopware bash -c "cd /var/www/html && bin/console plugin:refresh && bin/console plugin:install --activate LdMensura"
docker exec shopware bash -c "cd /var/www/html && bin/build-administration.sh"
```
