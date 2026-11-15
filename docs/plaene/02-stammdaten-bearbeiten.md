# 02 · Stammdaten bearbeiten und löschen

## Ausgangslage

Parzellen, Zähler und Tarife lassen sich nur anlegen. Ein Tippfehler in der Zählernummer, ein
Pächterwechsel oder ein versehentlich angelegter Zähler lassen sich nur per `tinker` beheben.
Tarife kann man überschreiben, aber nicht löschen. Ein Zähler kann nur durch Tausch ausgebaut
werden, nicht ersatzlos, und ein versehentlicher Tausch lässt sich nicht zurücknehmen.

## Ziel

Der Vorstand kann alle Stammdaten über die Oberfläche pflegen, ohne dass dabei Ablesungen oder
Abrechnungen unbemerkt ihre Grundlage verlieren.

## Entscheidungen

- **Löschen nur ohne Historie** (Invariante 6): Parzelle nur ohne Zähler, Zähler nur ohne
  Ablesungen (auch korrigierte zählen). Die Fremdschlüssel sind bereits `restrictOnDelete`; die
  Oberfläche prüft vorher und erklärt, warum es nicht geht, statt einen Datenbankfehler zu zeigen.
- **Zähler bearbeiten heißt: Erfassungsfehler beheben.** Änderbar sind Zählernummer, Einbaudatum
  und Anfangsstand. Die Zählerart ist nach dem Anlegen fest (falsch angelegt → löschen und neu).
  Einbaudatum und Anfangsstand dürfen den vorhandenen Ablesungen nicht widersprechen:
  Einbaudatum ≤ erste wirksame Ablesung, Anfangsstand ≤ deren Stand. Die Prüfung liegt in einem
  Service (`MeterMaintenance`), nicht im Controller.
- **Ausbau ohne Ersatz** als eigene Aktion mit Datum und Endstand. Er nutzt dieselbe
  Endstand-Logik wie der Tausch; dafür wird sie aus `MeterExchange` in `MeterMaintenance`
  verschoben, damit es nur eine Stelle gibt, die einen Zähler ausbaut.
- **Ausbau zurücknehmen** setzt nur `removed_on` zurück. Die Endstand-Ablesung bleibt, denn sie
  ist eine echte Ablesung. Ein beim Tausch angelegter neuer Zähler bleibt ebenfalls bestehen und
  kann, solange er keine Ablesungen hat, gelöscht werden.
- **Löschbestätigung ohne JavaScript:** `<details>`-Element, das den eigentlichen Löschen-Button
  erst nach dem Aufklappen zeigt.
- **Tarife:** Bearbeiten über das vorhandene Formular (vorausgefüllt per `?edit=`), Löschen neu.
  Ein gelöschter Tarif fällt in der Abrechnung als „Tarif fehlt“ auf, nicht als 0 €.
- Verworfen: *Soft Deletes / Archivieren von Parzellen.* Für einen Verein mit festen Parzellen
  ist das zusätzliche Komplexität ohne Anlass; eine Parzelle verschwindet nicht.
- Verworfen: *Änderungsprotokoll für Stammdaten.* Wäre konsequent, sprengt aber den Rahmen;
  bleibt als bekannte Grenze stehen.

## Schritte

1. `MeterMaintenance`: Endstand-Logik aus `MeterExchange` übernehmen, dazu `retire()`,
   `reactivate()`, `update()`, `delete()` mit Regeln und Tests. `MeterExchange` delegiert.
2. Zähler: Bearbeiten, Löschen, Ausbauen, Ausbau zurücknehmen über HTTP + Views.
3. Parzellen: Bearbeiten und Löschen.
4. Tarife: vorausgefülltes Bearbeiten und Löschen.
5. README aktualisieren.

## Testfälle

Zähler W-1001, eingebaut 01.04.2023 mit Anfangsstand 0, erste Ablesung 17.10.2023: 22,800 m³.

| Aktion | Erwartung |
|--------|-----------|
| Anfangsstand → 2,500 | gespeichert; Verbrauch 2023 = 20,300 m³ (vorher 22,800) |
| Anfangsstand → 23,000 | abgelehnt (höher als erste Ablesung) |
| Einbaudatum → 01.11.2023 | abgelehnt (nach der ersten Ablesung) |
| Zählernummer → Nummer eines anderen Zählers | abgelehnt (bereits vergeben) |
| Zähler löschen | abgelehnt (hat Ablesungen) |
| neuer Zähler ohne Ablesungen löschen | gelöscht |
| Ausbauen am 30.09.2026 ohne Endstand | abgelehnt |
| Ausbauen am 30.09.2026 mit Endstand 130,000 | Ablesung gespeichert, Zähler ausgebaut, fehlt nicht mehr in „noch nicht abgelesen“ |
| Ausbau zurücknehmen | Zähler wieder aktiv, Endstand-Ablesung bleibt |
| Parzelle mit Zähler löschen | abgelehnt mit Erklärung |
| Parzelle ohne Zähler löschen | gelöscht |
| Parzelle umbenennen auf vorhandene Nummer | abgelehnt |
| Tarif 2026 Wasser löschen | Abrechnung 2026 zeigt „fehlt der Tarif“ |

## Nicht-Ziele

- Pächterhistorie (wer hatte die Parzelle in welchem Jahr).
- Änderungsprotokoll für Stammdaten.
- Zählerart nachträglich ändern.

## Ergebnis

Umgesetzt wie geplant. Abweichungen:

- Für den Ausbau ohne Ersatz gibt es eine eigene Quelle `ReadingSource::Removal` („Ausbau“),
  statt die Quelle „Zählertausch“ mitzubenutzen. Im Verlauf soll stehen, was wirklich passiert ist.
- `MeterExchangeRejected` wurde zu `MeterChangeRejected` verallgemeinert, weil dieselbe Art von
  Ablehnung nun auch bei Bearbeiten, Löschen und Ausbau vorkommt. Das Feld für das Datum heißt im
  Service neutral `date`; die Controller ordnen es ihrem Formularfeld zu.
- Neue Invariante 7 in `CLAUDE.md`: Nur `MeterMaintenance::retire()` setzt `removed_on`.
