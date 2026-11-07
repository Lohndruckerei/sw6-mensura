# Projekt: sw6-mensura (Shopware 6 Plugin „Mensura“ der Lohndruckerei)

## Umgebung
- Shopware 6.7 läuft lokal im Docker-Container "shopware" (Dockware-Image, gesichert als `shopware-backup:2026-11-07`)
- Dieser Ordner wird im Container eingebunden unter:
  /var/www/html/custom/plugins/LdMensura
- Shopware-Befehle im Container ausführen, z. B.:
  docker exec shopware bash -c "cd /var/www/html && bin/console plugin:refresh"
- Administration bauen: docker exec shopware bash -c "cd /var/www/html && bin/build-administration.sh"
- Shop: http://localhost, Administration: http://localhost/admin

## Plugin
- Technischer Name: LdMensura
- Namespace: Lohndruckerei\Mensura
- Tabellen-/Entity-Präfix: `ld_mensura_` (z. B. `ld_mensura_calculation`), ACL-Schlüssel `ld_mensura`
- Admin: Modul `ld-mensura` (Routen `ld.mensura.*`), Komponenten-/CSS-Präfix `lm-`
- Zweck: Produkt-Konfigurator (Frontend) für Folien, Platten u. a. und flexible Flächen-/Preiskalkulation
  (Backend): Kalkulationen, Materialien, Optionsgruppen/-werte, Staffeln, Mindestpreise, eigene Formeln.
- Konzept, Kalkulationsfelder, Formeln und Fahrplan: docs/KONZEPT.md (bei Änderungen mitpflegen)

## Konventionen
- PHP 8.2+, strict_types, Shopware-Coding-Standards
- Texte und Snippets auf Deutsch (de-DE) und Englisch (en-GB)
- Keine Fremdplugins als Abhängigkeit; Composer-Bibliotheken sind erlaubt
- Unabhängig vom LieferLotse lauffähig; dessen Designsprache (A · Hafen, B · Druckbogen, C · Kompass)
  ist hier als eigene Kopie mit Präfix `lm-` enthalten (Store `lmDesign`, Mixin `lm-design`)
- Eigener Hauptmenüpunkt: Modul ist als `type: 'core'` registriert, weil Shopware Plugin-Module nicht auf
  die erste Menüebene lässt. Neue Oberflächen in `module/ld-mensura/constants.js` (AREAS) + Route ergänzen.
- Admin einfach und übersichtlich halten
- Preise immer serverseitig berechnen (nie dem Frontend vertrauen); Formeln nie per `eval`
- Kleine, nachvollziehbare Schritte; nach jedem Schritt im Shop testen

## Git
- Repository: git@github.com:Lohndruckerei/sw6-mensura.git (Branch `main`)
- Zugriff über Deploy-Key `~/.ssh/mensura_deploy` (in `core.sshCommand` hinterlegt, ohne Passphrase)
- Nach jedem abgeschlossenen und im Shop getesteten Schritt committen und auf GitHub pushen;
  halbfertige Zwischenstände nicht pushen. Kurz melden, was gepusht wurde (Commit-Hash).
- Nie force-pushen und keine Historie umschreiben, außer der Benutzer verlangt es ausdrücklich.
