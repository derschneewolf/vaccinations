# Vaccinations – Architektur & Entscheidungen

Stand: 22.10.2026

## Entscheidungen

| Thema | Entscheidung |
|---|---|
| Einsatz | Private Nextcloud (Familie) |
| Zielversionen | Nur aktuell unterstützte Majors: **NC 33–35** (`info.xml`), Min-Version anheben, sobald ein Major EOL ist (NC 33: Feb. 2027) |
| PHP | ≥ 8.2 (NC 35 selbst verlangt 8.3) |
| Coding Style | Nextcloud Coding Standards (PSR-12 via `nextcloud/coding-standard`, `@nextcloud/eslint-config`) |
| App-ID / Namespace | `vaccinations` / `OCA\Vaccinations` |
| Pflege der Daten | Jeder User pflegt sich selbst; zusätzlich **Betreuer** (Eltern) für zugeordnete User; Admins für alle |
| Stammdaten | Impfstoffe, Krankheiten, Impfkalender als JSON im Repo, **nicht** in der DB – Ergänzung per GitHub-PR, CI validiert |

## Datenebenen

1. **DB (Erfassung)**
   - `vacc_person` – Geburtsdatum, gewählter Impfkalender (Default `de-stiko`)
   - `vacc_record` – eine verabreichte Dosis: User, `vaccine_id`, Datum, Praxis?, Charge?, Notiz?, `created_by`
   - `vacc_guardian` – Betreuer → betreute Person (Eltern → Kind)
2. **`data/` (Open Data, per PR erweiterbar)**
   - `diseases.json` – Krankheitskatalog mit stabilen IDs
   - `vaccines/<id>.json` – ein Impfstoff pro Datei (weniger Merge-Konflikte), verweist auf Krankheits-IDs
   - `schedules/<id>.json` – Impfkalender (zuerst `de-stiko`)
   - `schema/*.schema.json` – JSON-Schemas; `bin/validate-data.mjs` prüft zusätzlich Dateiname = ID, Eindeutigkeit, Referenzen

## Katalog

`OCA\Vaccinations\Catalog\Catalog` liest `data/` lazy einmal pro Request (Dateien sind klein, kein persistenter Cache nötig).
Suche für das Autocomplete: Name, Aliase und Hersteller; Präfix-Treffer vor Teiltreffern, nicht mehr erhältliche Impfstoffe zuletzt (bleiben aber auffindbar, weil alte Impfpässe sie enthalten).

## API

Basis: `/ocs/v2.php/apps/vaccinations/api/v1`, Header `OCS-APIRequest: true`.

| Methode | Pfad | Zweck |
|---|---|---|
| GET | `/catalog` | Krankheiten und Impfstoffe |
| GET | `/persons` | pflegbare Personen (selbst zuerst; Admin: alle) |
| PUT | `/persons/{userId}` | Geburtsdatum setzen (`birthDate`, `null` löscht) |
| GET | `/persons/{userId}/records` | Impfungen einer Person |
| GET | `/persons/{userId}/status` | Impfstatus gegen den Impfkalender |
| POST | `/persons/{userId}/records` | Impfung anlegen |
| PUT | `/records/{id}` | Impfung ersetzen (alle Felder) |
| DELETE | `/records/{id}` | Impfung löschen |

Fehler: 400 `{field, message}`, 403 bei fremden Personen, 404 bei fremden oder unbekannten Einträgen (Existenz wird nicht verraten).

## Berechtigungen

`PermissionService::canManage(actor, person)`: selbst ODER Admin ODER Betreuer.
Lesen = Schreiben (wer ein Kind betreut, muss es auch pflegen können).
Betreuer-Zuordnungen pflegt der Admin in den Admin-Einstellungen der App.

## Impfkalender

Datei `data/schedules/de-stiko.json`, Schema `data/schema/schedule.schema.json`, fachliche Übersicht zum Gegenprüfen: [`STIKO-REGELN.md`](STIKO-REGELN.md).

Regelarten:
- `series` – Dosen nach Alter (`age`, `dueUntil`, `minInterval`), Varianten je nach Alter bei der ersten Dosis (`fromAge`), gilt bis `until`; optional `then.every` für Auffrischungen (Td alle 10 Jahre)
- `once` – ab `fromAge` (PCV20 ab 60, Zoster 2 Dosen mit `maxInterval`), optional `bornAfter`, `satisfiedByTotalDoses`, `alignWith` (Tdap mit der nächsten Td)
- `seasonal` – jährlich ab `fromAge` in der Saison `seasonStart`–`seasonEnd`

Eine Impfung zählt für eine Regel nur, wenn der Impfstoff alle Krankheiten der Regel abdeckt. Leere Historie → „nicht dokumentiert“, teilweise Historie → fällig/überfällig. Auswertung: `OCA\Vaccinations\Schedule\ScheduleEvaluator`, Tests in `tests/Unit/Schedule`.

## Impfkalender-Format (ursprünglicher Entwurf)

Regeln pro Krankheit, deklarativ. Die Engine wertet sie gegen die Dosen aus, die über das Mapping auf die Krankheit fallen.

```json
{
  "id": "de-stiko",
  "name": "STIKO-Empfehlungen",
  "version": "2026",
  "rules": [
    {
      "disease": "tetanus",
      "series": [
        { "label": "Grundimmunisierung", "doses": 3, "minAge": "P2M" }
      ],
      "booster": { "interval": "P10Y", "fromAge": "P5Y" }
    }
  ]
}
```

Zeitangaben als ISO-8601-Dauer (`P2M`, `P10Y`). Ergebnis pro Krankheit:
`complete` · `due` (fällig ab Datum) · `overdue` · `incomplete` · `unknown` (z. B. kein Geburtsdatum).

Indikations-, Berufs- und Reiseimpfungen sind noch nicht enthalten.

## Phasen

1. ✅ Gerüst: `info.xml`, Migration, Datenschemas, CI-Validierung, PermissionService
2. ✅ Entities/Mapper (`Person`, `Record`, `Guardian`), `Catalog` für `data/` inkl. Autocomplete-Suche, Aufräumen bei gelöschtem User, PHPUnit + PHPStan (Level 8) + CI
2b. ✅ Entwicklungsinstanz auf der DiskStation (`dev/`), `occ vaccinations:guardian:*`, Migration gegen PostgreSQL + NC 35 bestätigt
3. ✅ OCS-API + Vue-Frontend (Vue 3, @nextcloud/vue 9): Personenliste, Geburtsdatum, Schutz nach Krankheit, Impfhistorie, Erfassen/Bearbeiten/Löschen mit Impfstoff-Suche (auch nach Krankheit), Deutsch (Du/Sie)
4. ✅ Regel-Engine + STIKO-Kalender 2026 (Standardimpfungen aller Altersgruppen), 64 Impfstoffe im Katalog, Statusanzeige mit Fälligkeiten
5. Übersicht fällig/überfällig, Notifications via Background-Job
6. App-Store-Release
