# Pläne

Jede größere Aufgabe bekommt hier einen Plan, **bevor** Code entsteht (siehe `CLAUDE.md`,
Abschnitt „Arbeitsweise mit dem Agenten“). Der Plan ist die Vorgabe für den Agenten; die Commits
danach folgen seinen Schritten. Abweichungen stehen am Ende des jeweiligen Plans unter „Ergebnis“.

## Runde 2: Bekannte Grenzen abbauen

Ausgangspunkt sind die sechs Punkte aus dem README-Abschnitt „Bekannte Grenzen“ (Stand
`e978597`). Reihenfolge der Umsetzung, jeweils mit Begründung:

| Nr. | Plan | Grenze im README | Warum an dieser Stelle |
|-----|------|------------------|------------------------|
| 01 | [Zählertausch mit Endstand](01-zaehlertausch-endstand.md) | Endstand wird nur erinnert, nicht erzwungen | Kleinster Eingriff, rein fachlich; schafft den `MeterExchange`-Service, auf den 02 aufbaut |
| 02 | [Stammdaten bearbeiten und löschen](02-stammdaten-bearbeiten.md) | Keine Bearbeitung/Löschung über die Oberfläche | Braucht die Regeln aus 01 (Ausbaudatum, letzter Stand) |
| 03 | [Rollen und Benutzerverwaltung](03-rollen-und-benutzer.md) | Nur eine Rolle, Benutzer per Seeder | Erst wenn alle Schreibwege existieren, lässt sich sauber festlegen, wer was darf |
| 04 | [Abrechnung auf einen Stichtag](04-stichtagsabrechnung.md) | Jahresverbrauch nur als Differenz der Ablesungen | Ändert die Abrechnungszeile; muss vor der PDF-Rechnung feststehen |
| 05 | [PDF-Rechnung und E-Mail-Versand](05-pdf-rechnung-und-versand.md) | Nur CSV-Export | Nutzt Rollen (03) und die endgültige Abrechnungszeile (04) |
| 06 | [MySQL/PostgreSQL und Sperren](06-datenbanken-und-sperren.md) | Sperre wirkt nur mit MySQL/PostgreSQL, getestet nur SQLite | Zum Schluss, damit der gesamte neue Code gegen alle drei Datenbanken läuft |

Die Pläne 02–06 werden jeweils direkt vor ihrer Umsetzung geschrieben, nicht alle vorab: Was in
einem Schritt gelernt wird, soll in den nächsten Plan einfließen können.

## Aufbau eines Plans

- **Ausgangslage** – was heute passiert und warum das ein Problem ist
- **Ziel** – was danach gilt, aus Sicht des Vereins
- **Entscheidungen** – gewählte Lösung und verworfene Alternativen
- **Schritte** – entsprechen den Commits
- **Testfälle** – konkrete Beispiele mit Zahlen
- **Nicht-Ziele** – was bewusst draußen bleibt
- **Ergebnis** – nach der Umsetzung: was anders lief als geplant
