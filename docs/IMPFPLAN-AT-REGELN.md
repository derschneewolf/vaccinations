# Impfplan Österreich – Regeln der App zur fachlichen Prüfung

Stand der Regeln: **Impfplan Österreich 2026/27, Version 1.1 (14.10.2026)** · Datei: `data/schedules/at-impfplan.json`

Quelle: [sozialministerium.gv.at/impfplan](https://www.sozialministerium.gv.at/Themen/Gesundheit/Impfen/impfplan.html). Der Volltext ließ sich bei der Erstellung nur bis zum Kapitel Hib maschinell auslesen. Deshalb:

- ✅ **gesichert** – aus dem Impfplan 2026/27 selbst
- ⚠️ **zu prüfen** – aus Sekundärquellen (Stand 2025/26 oder Pressemeldungen zu 2026/27); bitte gegen die Originaltabelle halten

## Säuglinge und Kleinkinder

| | Impfung | Dosen und Alter | Nachholung bis |
|---|---|---|---|
| ✅ | Diphtherie, Tetanus, Pertussis (6-fach) | 2+1: 3. Lebensmonat, +2 Monate, mit 10–12 Monaten (≥ 6 Mon. nach G2) | Serie bis 18 J. |
| ✅ | Poliomyelitis (6-fach) | wie oben | 18 J. |
| ✅ | Hib (6-fach) | wie oben; Beginn ab 12 Mon.: 1 Dosis | vollendetes 5. Lebensjahr |
| ⚠️ | Hepatitis B (6-fach) | wie oben; später 3 Dosen (0, +4 Wo., +5 Mon.) | Grundimmunisierung bis 60 J. |
| ⚠️ | Pneumokokken | ab 6. Woche, +8 Wochen, 3. Dosis mit 11–15 Monaten | 2. Geburtstag |
| ⚠️ | Rotaviren | ab 6. Woche, 2 Dosen ≥ 4 Wochen | 32. Lebenswoche |
| ⚠️ | Meningokokken B | 2–5 Mon., ≥ 2 Mon. später, 12–15 Mon. (nicht kostenfrei) | 2. Geburtstag (Grenze unklar) |
| ⚠️ | Meningokokken ACWY (Kleinkind) | 1 Dosis im 13. Lebensmonat (nicht kostenfrei) | 10. Geburtstag |
| ⚠️ | Masern, Mumps, Röteln | ab 9 Monaten, 2. Dosis ab 12 Monaten (≥ 4 Wochen) | jedes Alter |
| ⚠️ | Varizellen | ab 1 Jahr, 2 Dosen ≥ 4 Wochen (nicht kostenfrei) | 18 J. |
| ⚠️ | Hepatitis A | ab 1 Jahr, 2 Dosen im Abstand von 6–12 Monaten (nicht kostenfrei) | 18 J. |
| ✅ | FSME | ab 1 Jahr, siehe unten | jedes Alter |

## Schulkinder und Jugendliche

| | Impfung | Dosis | Alter |
|---|---|---|---|
| ✅ | Diphtherie, Tetanus, Pertussis, Polio | A1 | im 6. Lebensjahr, vor Schuleintritt – auch wenn G3 < 5 Jahre zurückliegt |
| ✅ | Diphtherie, Tetanus, Pertussis, Polio | A2 | 5 Jahre nach A1, spätestens mit 14–15 Jahren |
| ⚠️ | Meningokokken ACWY (Jugendliche) | 1 Dosis | vollendetes 10. bis 13. Lebensjahr, unabhängig von früheren Impfungen; Nachholung bis 18 |
| ⚠️ | HPV | 2 Dosen, 6–12 Monate Abstand | ab 9 Jahren (bevorzugt 4. Schulstufe), kostenfrei bis zum 21. Geburtstag |

## Erwachsene

| | Impfung | Empfehlung |
|---|---|---|
| ✅ | Diphtherie, Tetanus, Pertussis | Tdap alle 10 Jahre bis 60, ab 60 alle 5 Jahre (Td ohne Pertussis zählt nicht) |
| ✅ | Poliomyelitis | nach den Schulauffrischungen nur bei spezieller Indikation |
| ✅ | FSME | Grundimmunisierung 0 / +1–3 Mon. / +5–12 Mon., 1. Auffrischung nach 3 Jahren, dann alle 5 Jahre, ab 60 alle 3 Jahre |
| ✅ | COVID-19 | jährlich im Spätsommer/Herbst, vorrangig ab 60 (allen ab 12 angeboten) |
| ⚠️ | Influenza | jährlich für alle ab 6 Monaten, Saison ab 1. Oktober |
| ⚠️ | Herpes zoster | ab 50: 2 Dosen im Abstand von 2–6 Monaten (ab 60 kostenfrei) |
| ⚠️ | Pneumokokken | ab 60: einmal Konjugatimpfstoff (kostenfrei) |
| ⚠️ | RSV | ab 60: einmal |
| ⚠️ | Hepatitis B | Grundimmunisierung bis zum vollendeten 60. Lebensjahr nachholen |

## Offene Punkte

- Hepatitis B: Gibt es eine Auffrischung im Schulalter (früher 7.–15. Lebensjahr)? Derzeit nicht abgebildet.
- Meningokokken B: Bis zu welchem Alter wird nachgeimpft?
- HPV: Gilt die allgemeine Empfehlung über den 21. Geburtstag hinaus (bis 30)?
- Pneumokokken ab 60: PCV20 oder PCV21, sequenzielles Schema?
- Hepatitis A: Allgemeine Empfehlung auch für Erwachsene?

## Bewusste Vereinfachungen

Siehe Feld `simplifications` in der Regeldatei (u. a. FSME-Schnellschema, Influenza-Erstimpfung bei Kindern mit 2 Dosen, Frühgeborene).
