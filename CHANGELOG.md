# Changelog

Alle nennenswerten Änderungen der App. Das Format folgt [Keep a Changelog](https://keepachangelog.com/de/1.1.0/), die Versionsnummern [Semantic Versioning](https://semver.org/lang/de/).

Neue Einträge kommen zunächst unter **Unreleased** und wandern mit der nächsten Version in einen eigenen Abschnitt – die Überschrift muss dann genau der Version in `appinfo/info.xml` entsprechen (`## 1.2.3 – JJJJ-MM-TT`), daraus entstehen auch die Release-Notizen auf GitHub.

## Unreleased

## 0.6.0 – 2026-11-04

### Neu
- **Druckansicht** für den Arztbesuch: Button „Drucken“ erzeugt ein A4-Blatt mit den offenen Impfungen samt Begründung (seit wann überfällig, welche Dosis nicht zählt), den aktuellen Impfungen, weiteren Impfungen, der Impfhistorie und Platz für Notizen – ohne Navigation und Scrollbalken. Lässt sich im Druckdialog auch als PDF speichern.

## 0.5.2 – 2026-10-28

### Behoben
- Eine 4. Säuglingsdosis (altes 3+1-Schema, z. B. Infanrix hexa mit knapp 2 Jahren) galt fälschlich als Polio-Auffrischung im Schulalter; Polio stand dadurch auf „Vollständig“. Auffrischungen haben jetzt ein Mindestalter (STIKO: Td/Pertussis ab 4 bzw. 8 Jahren, Polio ab 8; Österreich: ab 4 bzw. 9 Jahren), frühere Dosen zählen als zusätzliche Dosis.

### Geändert
- Nicht gezählte Dosen werden mit Datum angezeigt („Nicht gezählt, für diese Dosis zu früh gegeben: 10.02.2016“) statt nur als Anzahl.

## 0.5.1 – 2026-10-22

### Behoben
- Das „OK“-Badge in der Personenliste war doppelt so hoch wie die anderen.

## 0.5.0 – 2026-10-22

### Neu
- **Ampel in der Personenliste**: rot bei überfälligen oder nicht dokumentierten Impfungen, gelb bei fälligen oder demnächst anstehenden, grün wenn alles aktuell ist, grau ohne Geburtsdatum. Der Tooltip nennt die betroffenen Impfungen; die Ampel aktualisiert sich nach jeder Änderung.

### Geändert
- „Demnächst“ beginnt 60 Tage vor der Fälligkeit (bisher 90) – auch für die Benachrichtigungen.
- Die eigene Person heißt in der Liste „Name (Du)“ bzw. „Name (Sie)“.

### Behoben
- „Überfällig“ und „Fällig“ waren ab Nextcloud 32 schlecht lesbar (weiße Schrift auf hellem Hintergrund).

## 0.4.2 – 2026-10-22

### Geändert
- Die Spalte „Nächste“ zeigt lesbare Texte („2. Dosis ab …“, „Auffrischung ab …“, „Saison ab …“) statt Kürzeln wie G2 oder A1.

## 0.4.1 – 2026-10-22

### Neu
- STIKO: **FSME** wird geprüft, sobald mindestens eine FSME-Impfung eingetragen ist (Indikationsimpfung in Risikogebieten) – inkl. Auffrischung nach 3 Jahren, dann alle 5 Jahre, ab 50 alle 3 Jahre.

### Dokumentation
- README: Installation und Update für Snap und klassische Installationen.

## 0.4.0 – 2026-10-22

Erste veröffentlichte Version.

### Neu
- Impfungen pro Nextcloud-Konto erfassen: Datum, Impfstoff, optional Praxis, Chargennummer und Notiz; Impfstoff-Suche auch nach Hersteller oder Krankheit.
- **Impfstatus** gegen ein pro Person wählbares Impfschema: überfällig, fällig, demnächst, aktuell, vollständig, nicht dokumentiert – mit Datum der nächsten Dosis.
- Impfschemata **STIKO (Deutschland)**, Standardimpfungen aller Altersgruppen, und **Impfplan Österreich 2026/27**.
- **Weitere Impfungen** ohne Standardempfehlung (z. B. Reiseimpfungen) mit „vor X Jahren“.
- Dosen, die laut Empfehlung nicht zählen (z. B. MenACWY vor dem 12. Geburtstag), werden erkannt und angezeigt.
- **Betreuer**: Eltern pflegen die Daten ihrer Kinder (`occ vaccinations:guardian:add|list|remove`); Administratoren sehen alle Personen.
- **Benachrichtigungen** in Nextcloud bei demnächst anstehenden, fälligen und überfälligen Impfungen (täglicher Hintergrundjob, `occ vaccinations:notify`).
- Impfstoffe, Krankheiten und Impfschemata als offene JSON-Daten im Repository, per Pull Request erweiterbar und in der CI geprüft.
- Oberfläche auf Deutsch (Du und Sie) und Englisch.
