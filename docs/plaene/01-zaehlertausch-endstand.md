# 01 · Zählertausch mit Endstand

## Ausgangslage

Beim Zählertausch (`MeterController::store` mit `replaces_meter_id`) wird der alte Zähler zum
Einbaudatum des neuen ausgebaut. Den Endstand des alten Zählers muss der Vorstand vorher selbst
als Ablesung erfassen; das Formular erinnert nur mit einem Hinweistext daran.

Wird das vergessen, fehlt der Verbrauch zwischen letzter Ablesung und Ausbau in der Abrechnung –
und zwar unbemerkt, weil der alte Zähler danach keine Ablesung mehr annimmt (er ist nicht mehr
eingebaut). Außerdem prüft heute niemand, ob

- das Tauschdatum vor der letzten Ablesung des alten Zählers liegt (dann lägen Ablesungen nach
  dem Ausbau),
- der neue Zähler dieselbe Art hat wie der ersetzte (Wasser ersetzt Strom).

## Ziel

Ein Zählertausch ist nur mit Endstand möglich. Danach gilt für jeden ausgebauten Zähler: Es gibt
eine wirksame Ablesung am Ausbautag, und keine danach.

## Entscheidungen

- **Eigener Service `MeterExchange`** statt Logik im Controller. Tausch = Endstand speichern +
  alten Zähler ausbauen + neuen anlegen, in **einer** Transaktion. Schlägt ein Teil fehl, bleibt
  alles beim Alten.
- **Endstand läuft über den `ReadingRecorder`** (Invariante 1). Damit gelten dieselben harten
  Regeln (nicht kleiner als der vorherige Stand) und dieselbe Verbrauchswarnung. Ein auffälliger
  Endstand wird gespeichert und landet in der Prüfliste, er blockiert den Tausch nicht.
- **Neue Quelle `ReadingSource::Exchange`** („Zählertausch“), damit im Verlauf erkennbar ist,
  woher der Endstand stammt.
- **Gibt es am Tauschtag schon eine Ablesung**, ist sie der Endstand. Das Feld darf dann leer
  bleiben; ein abweichender Wert wird abgelehnt (bitte die vorhandene Ablesung korrigieren),
  statt eine zweite Ablesung am selben Tag zu erzeugen.
- **Formular bleibt ein Formular.** Das Feld „Endstand des alten Zählers“ steht unter der Auswahl
  des ersetzten Zählers. Kein JavaScript zum Ein-/Ausblenden.
- Verworfen: *Endstand automatisch aus der letzten Ablesung übernehmen.* Das wäre eine stille
  Annahme (Invariante 5) und genau der Fehler, den der Punkt beheben soll.

## Schritte

1. `ReadingSource::Exchange` und Service `MeterExchange` mit Regeln und Tests auf Service-Ebene.
2. `MeterRequest`/`MeterController` nutzen den Service; Formularfeld „Endstand“; HTTP-Tests.
   Der bestehende Test `test_a_meter_exchange_retires_the_old_meter` bekommt einen Endstand,
   weil sein bisheriger Ablauf (Tausch ohne Endstand) nun absichtlich abgelehnt wird.
3. README: Punkt aus „Bekannte Grenzen“ entfernen, Funktion beschreiben.

## Testfälle

Alter Wasserzähler, letzte Ablesung 17.10.2025 mit 120,000 m³. Tausch am 01.06.2026.

| Eingabe | Erwartung |
|---------|-----------|
| Endstand 131,400 | Ablesung 131,400 am 01.06.2026 (Quelle Zählertausch), alter Zähler ausgebaut, neuer angelegt. Verbrauch 2026 alter Zähler = 11,400 m³ |
| Neuer Zähler am 17.10.2026 bei 14,250 (Anfangsstand 0) | Abrechnung 2026 hat zwei Wasserzeilen: 11,400 + 14,250 m³ |
| kein Endstand | abgelehnt, Fehler am Feld Endstand; kein neuer Zähler, alter bleibt aktiv |
| Endstand 119,000 | abgelehnt (kleiner als vorheriger Stand); nichts gespeichert |
| Ablesung 131,400 existiert schon am 01.06.2026, Feld leer | Tausch klappt, keine zweite Ablesung |
| wie oben, aber Feld 131,500 | abgelehnt (abweichender Stand am selben Tag) |
| Tauschdatum 01.10.2025 (vor der letzten Ablesung) | abgelehnt |
| Stromzähler soll Wasserzähler ersetzen | abgelehnt |

## Nicht-Ziele

- Tausch rückgängig machen (kommt mit Plan 02 über das Bearbeiten des Ausbaudatums).
- Ausbau ohne Ersatz (Parzelle wird stillgelegt) – ebenfalls Plan 02.

## Ergebnis

Umgesetzt wie geplant, mit zwei Ergänzungen, die erst beim Schreiben der Tests auffielen:

- Ein ausgefüllter Endstand **ohne** ausgewählten ersetzten Zähler wird abgelehnt. Vorher wäre
  der Wert stillschweigend verworfen worden.
- Die Ablehnung trägt das betroffene Formularfeld (`MeterExchangeRejected::$field`), damit die
  Meldung am richtigen Feld steht und nicht pauschal über dem Formular.

Nebenbei: `TestCase` ruft `withoutVite()` auf. Die Tests liefen bisher nur nach `npm run build`
grün; das fiel auf, weil die Suite hier vor dem ersten Frontend-Build lief.
