# STIKO-Regeln der App – zur fachlichen Prüfung

Stand der Regeln: **2026-10** · Datei: `data/schedules/de-stiko.json`
Quelle: STIKO-Empfehlungen 2026, Epidemiologisches Bulletin 4/2026 (Tab. 1A/1B, Tab. 2 mit Fußnoten); COVID-19-Aktualisierung 07/2026 (G-BA-Beschluss 09/2026); MenACWY/MenB-Stand laut KV Sachsen-Anhalt 02/2026.

Diese Tabelle fasst zusammen, **was die App prüft**. Bitte gegen den offiziellen Impfkalender gegenlesen; Abweichungen als Issue oder Pull-Request melden.

## Säuglinge und Kleinkinder

| Impfung | Dosen und Alter | Mindestabstände | Nachholung bis |
|---|---|---|---|
| Tetanus + Diphtherie | 2, 4, 11 Monate | G2 ≥ 4 Wo. nach G1, G3 ≥ 6 Mon. nach G2 | Serie bis 18 J., danach Auffrischung |
| Pertussis | 2, 4, 11 Monate | wie oben | 18 J. |
| Poliomyelitis | 2, 4, 11 Monate | wie oben | 18 J. |
| Hib | 2, 4, 11 Monate; Beginn ab 12 Mon.: 1 Dosis | wie oben | 5. Geburtstag |
| Hepatitis B | 2, 4, 11 Monate; Beginn ab 12 Mon.: 3 Dosen (0, 4 Wo., +5 Mon.) | wie oben | 18 J. |
| Pneumokokken | 2, 4, 11 Monate; Beginn ab 12 Mon.: 2 Dosen ≥ 8 Wo. | G2 ≥ 8 Wo., G3 ≥ 6 Mon. | 2. Geburtstag |
| Rotaviren | ab 6 Wochen, 2 Dosen | ≥ 4 Wochen | 32. Lebenswoche |
| Meningokokken B | 2, 4, 12 Monate; Beginn 12–23 Mon.: 3 Dosen; ab 24 Mon.: 2 Dosen | G2 ≥ 8 Wo., G3 ≥ 6 Mon. (bzw. ≥ 12 Mon.) | 5. Geburtstag |
| Masern, Mumps, Röteln | 11 und 15 Monate | ≥ 4 Wochen | 18 J. |
| Varizellen | 11 und 15 Monate | ≥ 4 Wochen | 18 J. |

Später Beginn (ab 12 Monaten) bei Tetanus/Diphtherie/Pertussis/Polio: vereinfacht 3 Dosen (0, +4 Wo., +6 Mon.).

## Kinder und Jugendliche

| Impfung | Dosis | Alter | überfällig ab |
|---|---|---|---|
| Tetanus, Diphtherie, Pertussis | A1 | 5–6 Jahre (≥ 5 J. nach G3) | 7. Geburtstag |
| Tetanus, Diphtherie, Pertussis | A2 | 9–16 Jahre (≥ 5 J. nach A1) | 17. Geburtstag |
| Poliomyelitis | A1 | 9–16 Jahre | 17. Geburtstag |
| HPV | 2 Dosen ≥ 5 Monate Abstand | 9–14 Jahre | 15. Geburtstag |
| HPV (Beginn ab 15) | 3 Dosen (0, +4 Wo., +5 Mon.) | bis 17 Jahre | – |
| Meningokokken ACWY | 1 Dosis (frühere MenC-Impfung zählt nicht) | 12–14 Jahre, Nachholung bis 24 J. | 15. Geburtstag |

## Erwachsene

| Impfung | Empfehlung |
|---|---|
| Tetanus + Diphtherie | Auffrischung alle 10 Jahre (überfällig 12 Monate nach Fälligkeit) |
| Pertussis | ab 18 Jahren einmal, zusammen mit der nächsten fälligen Td-Impfung (Tdap) |
| Masern | nach 1970 Geborene ab 18: eine MMR-Impfung, wenn nicht schon 2 Masern-Impfungen dokumentiert sind |
| Pneumokokken | ab 60: einmal PCV20 (Impfungen vor dem 60. Geburtstag zählen nicht) |
| Herpes zoster | ab 60: 2 Dosen im Abstand von 2–6 Monaten |
| Influenza | ab 60: jährlich, Saison 1. September bis 31. März (Impfung ab August zählt) |
| COVID-19 | ab 75: jährlich, Saison wie Influenza |
| RSV | ab 75: einmal |

## FSME (Indikationsimpfung)

Die STIKO empfiehlt FSME bei Zeckenexposition in Risikogebieten. Die App prüft FSME deshalb **nur, wenn mindestens eine FSME-Impfung eingetragen ist** – sonst erscheint keine Zeile.

| Dosis | Abstand | überfällig ab |
|---|---|---|
| G2 | 1–3 Monate nach G1 | 3 Monate nach G1 |
| G3 | 5–12 Monate nach G2 (FSME-IMMUN; Encepur 9–12) | 12 Monate nach G2 |
| 1. Auffrischung | 3 Jahre nach G3 | 4 Jahre nach G3 |
| weitere | alle 5 Jahre, ab 50 alle 3 Jahre | 12 Monate nach Fälligkeit |

Ab welchem Alter alle 3 Jahre aufgefrischt wird, hängt vom Impfstoff ab (Encepur ab 50, FSME-IMMUN ab 60). Die App nimmt vorsichtshalber 50.

## Wie die App bewertet

| Status | Bedeutung |
|---|---|
| Überfällig | Dosis fällig und Frist (Alter bzw. Abstand) überschritten |
| Fällig | Dosis jetzt empfohlen |
| Nicht dokumentiert | Empfehlung gilt, aber **keine** passende Impfung eingetragen – vermutlich Impfpass nicht vollständig erfasst |
| Demnächst | nächste Dosis innerhalb von 60 Tagen |
| Aktuell / Vollständig | nichts fällig; ggf. Datum der nächsten Auffrischung |

- Eine teilweise dokumentierte Serie gilt als echte Lücke (fällig/überfällig), eine komplett leere als „nicht dokumentiert“.
- Dosen, die laut Empfehlung nicht zählen (z. B. MenACWY vor dem 12. Geburtstag, Pneumokokken vor 60), werden als „frühere Dosis zählt nicht“ angezeigt; die Empfehlung gilt dann als fällig statt „nicht dokumentiert“.
- Eine Impfung zählt für eine Regel nur, wenn der Impfstoff **alle** Krankheiten der Regel abdeckt (z. B. Td zählt nicht für Pertussis).
- Mehrere Einträge am selben Tag zählen als eine Dosis.

## Bewusste Vereinfachungen

- Nachholschemata bei spätem Beginn sind vereinfacht.
- Rotaviren: unabhängig vom Impfstoff 2 Dosen (Rotarix: 2 Dosen bis 24 Wochen; RotaTeq: 3 Dosen bis 32 Wochen).
- HPV: Ein Abstand unter 5 Monaten zwischen 1. und 2. Dosis (dann 3. Dosis nötig) wird nicht erkannt.
- Frühgeborene (3+1-Schema) werden nicht gesondert behandelt.
- RSV-Prophylaxe für Säuglinge (Antikörper, keine Impfung) ist nicht enthalten.
- Nicht enthalten: weitere Indikations-, Berufs- und Reiseimpfungen (z. B. Hepatitis A, Typhus) – sie erscheinen unter „Weitere Impfungen“.
