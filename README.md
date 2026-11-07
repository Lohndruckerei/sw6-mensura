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

Version 0.1.0 – Grundgerüst: Plugin, Hauptmenü mit Untermenüs, Designsystem, Konzept.
Nächster Schritt: Datenmodell (siehe Fahrplan im Konzept).

## Entwicklung

```bash
docker exec shopware bash -c "cd /var/www/html && bin/console plugin:refresh && bin/console plugin:install --activate LdMensura"
docker exec shopware bash -c "cd /var/www/html && bin/build-administration.sh"
```
