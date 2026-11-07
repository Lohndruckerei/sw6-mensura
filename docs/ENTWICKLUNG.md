# Entwicklung: Backend und Frontend getrennt, ein Repo

Mensura bleibt **ein Plugin und ein Repository**. Darin gibt es zwei Arbeitsbereiche, die nebeneinander entwickelt werden
können, z. B. in zwei Claude-Sitzungen oder von zwei Personen. Verbunden sind sie nur über den **Vertrag** in
`docs/contract/`.

## Wer ist wofür zuständig?

| Bereich | Ordner | Inhalt |
|---|---|---|
| **Backend** | `src/Core/`, `src/Service/`, `src/Migration/`, `src/Extension/`, `src/Controller/`, `src/Resources/config/`, `src/Resources/app/administration/`, `tests/Backend/` | Datenmodell, Preis-Engine, Store-API, Warenkorb, Administration |
| **Frontend** | `src/Resources/app/storefront/`, `src/Resources/views/storefront/`, `tests/Frontend/` | Konfigurator „kompakt“ und „geführt“ im Shop (Twig + JS-Plugin), später Produktberater-Anbindung |
| **Vertrag** (gemeinsam) | `docs/contract/` | Store-API-Beschreibung, Schemas, Beispielfälle mit Sollwerten |
| gemeinsam | `docs/`, `README.md`, `CLAUDE.md`, `composer.json`, `.github/` | Doku, Version, Prüfungen |

Jeder Bereich ändert nur seine eigenen Ordner. Braucht das Frontend ein neues Feld, wird es zuerst im Vertrag ergänzt
(siehe unten), dann setzt das Backend es um. Bis dahin arbeitet das Frontend gegen den Beispielfall.

## Branches

- `main`: immer lauffähig und im Shop getestet. Nur hierher wird zusammengeführt.
- `contract/<thema>`: Änderung am Vertrag (Schema + Beispielfall). Geht **zuerst** nach `main`.
- `backend/<thema>` bzw. `frontend/<thema>`: Arbeit in einem Bereich.
- **Abgleich:** Jeder Arbeits-Branch holt sich regelmäßig den aktuellen Stand von `main` (mindestens vor dem Zusammenführen).
  So hat das Frontend neue Backend-Funktionen und Vertragsänderungen und umgekehrt. Zusammengeführt wird nur, wenn die
  GitHub-Prüfung grün ist.
- Kein Force-Push, keine umgeschriebene Historie (siehe CLAUDE.md).

## Automatischer Abgleich (GitHub-Prüfung)

`.github/workflows/ci.yml` läuft bei jedem Push und Pull-Request:

| Prüfung | Was | ab wann |
|---|---|---|
| **Vertrag** | Schemas gültig; jeder Beispielfall passt zu seinem Schema; kein Einkaufspreis in Vertrag oder Fixtures; Sollpreise in sich stimmig (Gesamt = je Stück × Menge, außer bei Mindestpreis) | jetzt |
| **Backend** | PHP-Syntax aller Dateien; Snippets de-DE/en-GB vollständig | jetzt |
| **Backend gegen Vertrag** | Preis-Engine rechnet alle Beispielfälle aus `fixtures/calculate/` und muss die Sollwerte treffen; Antworten der Store-API passen zu den Schemas | mit der Preis-Engine (Fahrplan Schritt 4) |
| **Frontend gegen Vertrag** | Konfigurator-JS wird mit `fixtures/configurator/*.json` und `fixtures/calculate/*.expected.json` getestet (ohne Shop) | mit dem Konfigurator (Schritt 6) |

Damit fällt sofort auf, wenn eine Seite den Vertrag verlässt: Das Backend rechnet anders als im Beispielfall, oder das
Frontend erwartet ein Feld, das es nicht gibt.

Lokal dieselben Prüfungen ausführen:

```bash
docker exec shopware bash -c "cd /var/www/html/custom/plugins/LdMensura && bash .github/scripts/check-all.sh"
```

## Beispielfall anlegen (Ablauf)

1. Branch `contract/<thema>` anlegen.
2. Schema in `docs/contract/schema/` erweitern, nur optionale Felder ergänzen.
3. Beispielfall `fixtures/calculate/<name>.request.json` + `<name>.expected.json` anlegen, Sollwerte mit Herleitung im Commit.
4. Prüfung lokal laufen lassen, nach `main` bringen.
5. Backend und Frontend setzen es in ihren Branches um und holen sich vorher den Stand von `main`.
