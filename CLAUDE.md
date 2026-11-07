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
- Unabhängig vom LieferLotse lauffähig. Admin-Design kommt aus ~/projekte/ld-admin-design – dort ändern,
  nicht lokal: `src/Resources/app/administration/src/ld-admin-design/` ist generiert (`./sync.sh mensura`
  im Design-Projekt, Präfix `lm-`, Store `lmDesign`, Mixin `lm-design`); Regeln in dessen DESIGN.md.
  Mensura-eigene Seitenstile liegen in `module/ld-mensura/mensura.scss`.
- Eigener Hauptmenüpunkt: Modul ist als `type: 'core'` registriert, weil Shopware Plugin-Module nicht auf
  die erste Menüebene lässt. Neue Oberflächen in `module/ld-mensura/constants.js` (AREAS) + Route ergänzen.
- Admin einfach und übersichtlich halten – verbindliche Bedienregeln in docs/KONZEPT.md Abschnitt 3a
  (Hilfetext an jedem Feld mit Beispiel, Erklärkarten, Assistenten, Live-Vorschau, Warnungen beim Speichern)
- Grundprinzip: Kalkulationen rechnen, Produktprofile wählen aus und schränken ein (nie erweitern);
  Optionssets steuern, welche Gruppen erscheinen (KONZEPT 3.6/3.7)
- Entities: vollständige Getter/Setter (`bin/console dal:validate` muss für Mensura fehlerfrei sein; die
  Meldungen des LieferLotse sind dessen Sache), toMany-Setter nicht nullable, Constraint-Namen ≤ 64 Zeichen
- Preise netto, nur B2B; Basis-VK = Wiederverkäufer, andere Kundengruppen per Aufschlag (`ld_mensura_customer_group_rule`)
- Rechte je Rolle (Buchhaltung, Vertrieb, Chef, Admin …) über die Shopware-Rollenverwaltung; EK/Marge nur mit
  eigenem Recht und serverseitig geschützt (KONZEPT 3b)
- Einkaufspreise nie in die Store-API (kein ApiAware an EK-Feldern und am Schnappschuss `ld_mensura_order_line_item`)
- LieferLotse nur lose koppeln: Lieferanten-IDs ohne Fremdschlüssel, Andocken über dessen Events
  (Vertrag in docs/INTEGRATION.md – bei Änderungen dort pflegen; Payload/Schnappschuss nur erweitern, `schema` erhöhen bei Bruch)
- Preise immer serverseitig berechnen (nie dem Frontend vertrauen); Formeln nie per `eval`
- Kleine, nachvollziehbare Schritte; nach jedem Schritt im Shop testen

## Backend und Frontend (ein Repo, zwei Bereiche)
- Zuständigkeit je Ordner, Branch-Regeln und Abgleich: docs/ENTWICKLUNG.md
- Backend und Frontend sprechen nur über den Vertrag in docs/contract/ (Store-API, Schemas, Beispielfälle mit Sollwerten).
  Vertragsänderungen zuerst auf `contract/…` nach `main`, dann umsetzen. Nur erweitern, nie brechen.
- Arbeits-Branches `backend/…` bzw. `frontend/…` holen regelmäßig `main`; zusammenführen nur bei grüner Prüfung
  (.github/workflows/ci.yml). Lokal: `bash .github/scripts/check-all.sh` im Plugin-Verzeichnis des Containers.
- In einer Sitzung, die nur einen Bereich bearbeitet, keine Dateien des anderen Bereichs ändern.

## Git
- Repository: git@github.com:Lohndruckerei/sw6-mensura.git (Branch `main`)
- Zugriff über Deploy-Key `~/.ssh/mensura_deploy` (in `core.sshCommand` hinterlegt, ohne Passphrase)
- Nach jedem abgeschlossenen und im Shop getesteten Schritt committen und auf GitHub pushen;
  halbfertige Zwischenstände nicht pushen. Kurz melden, was gepusht wurde (Commit-Hash).
- Nie force-pushen und keine Historie umschreiben, außer der Benutzer verlangt es ausdrücklich.
