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

Version 0.2.0 – Grundgerüst (Hauptmenü mit Untermenüs, Design A/B/C) und Datenmodell
(12 Tabellen `ld_mensura_*`, per Admin-API nutzbar, Rechte für die Rollenverwaltung).
Schnittstellen zu LieferLotse und künftigen Plugins: [docs/INTEGRATION.md](docs/INTEGRATION.md).
Nächster Schritt: Admin-Oberflächen für Kalkulationen, Materialien und Optionen (siehe Fahrplan im Konzept).

## Entwicklung

```bash
docker exec shopware bash -c "cd /var/www/html && bin/console plugin:refresh && bin/console plugin:install --activate LdMensura"
docker exec shopware bash -c "cd /var/www/html && bin/build-administration.sh"
```
