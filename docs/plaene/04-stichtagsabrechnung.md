# 04 · Abrechnung auf einen Stichtag

## Ausgangslage

Der Jahresverbrauch ist „letzte Ablesung im Jahr minus letzte Ablesung davor“. Das stimmt, solange
alle Zähler jedes Jahr am selben Tag abgelesen werden. Wird ein Zähler einmal spät abgelesen
(z. B. erst am 20.12.), bekommt dieses Jahr 14 Monate Verbrauch und das Folgejahr nur 10 – bei
jährlich wechselnden Preisen landet Verbrauch im falschen Tarif, und Parzellen sind untereinander
nicht vergleichbar.

## Ziel

Der Verein kann ein Abrechnungsjahr auf einen **Stichtag** abrechnen. Die Zählerstände werden
dafür rechnerisch auf den Stichtag umgerechnet; jede Parzelle zahlt für denselben Zeitraum.
Umgerechnete Werte sind überall als solche erkennbar (Invariante 5).

## Entscheidungen

**Stichtag je Abrechnungsjahr** (Tabelle `billing_periods`: Jahr → Datum). Kein Eintrag heißt:
Differenzmethode wie bisher. Vorteile gegenüber einer globalen Einstellung:

- Bereits abgerechnete Jahre ändern sich nicht, wenn der Verein umstellt.
- Die Einstellung steht dort, wo sie gebraucht wird: auf der Abrechnungsseite des Jahres.

**Ein Jahr endet an seiner „Grenze“, das nächste beginnt dort.** Für jedes Jahr gibt es einen
Grenzstand je Zähler: bei Differenzmethode die letzte Ablesung im Kalenderjahr, bei Stichtag der
umgerechnete Stand am Stichtag. Verbrauch(J) = Grenze(J) − letzte bestimmbare Grenze davor (sonst
Anfangsstand). Dadurch gibt es beim Wechsel der Methode weder Lücken noch doppelt abgerechneten
Verbrauch, und die Differenzmethode ist exakt der bisherige Sonderfall.

**Nur Interpolation, keine Hochrechnung.** Der Stand am Stichtag wird linear zwischen der
Ablesung davor und der Ablesung am/nach dem Stichtag bestimmt. Gibt es danach noch keine Ablesung,
zeigt die Abrechnung „Ablesung fehlt“.

- Verworfen: *Hochrechnen mit dem bisherigen Tagesverbrauch.* Ein hochgerechneter Stand ändert
  sich, sobald die nächste Ablesung kommt – eine bereits verschickte Abrechnung würde nachträglich
  eine andere Zahl zeigen. Ein interpolierter Stand hängt nur von seinen beiden Nachbarn ab und
  bleibt stabil.
- Folge für den Verein: Der Stichtag sollte am oder vor dem frühesten Ablesetag liegen
  (z. B. 30.09. bei Ablesung Mitte Oktober). Das steht als Hinweis am Formular.

**Ganzzahlig rechnen** (Invariante 3): `v0 + (v1 − v0) · Tage_bis_Stichtag / Tage_gesamt`,
kaufmännisch gerundet mit `intdiv`, in Tausendstel-Einheiten.

**Sonderfälle ohne Schätzung:**

- Ablesung genau am Stichtag → dieser Wert, nicht umgerechnet.
- Zähler vor dem Stichtag ausgebaut → sein Endstand (er läuft nicht weiter).
- Zähler nach dem Stichtag eingebaut → gehört erst ins nächste Abrechnungsjahr.
- Stichtag zwischen Einbau und erster Ablesung → Interpolation ab Einbau/Anfangsstand.

**Transparenz:** Die Abrechnungszeile trägt Anfangs- und Endstand mit Datum und Herkunft
(Ablesung, Anfangsstand, umgerechnet). Oberfläche und CSV zeigen sie; umgerechnete Werte bekommen
ein „≈“. Die CSV bekommt neue Spalten **am Ende**, damit bestehende Auswertungen weiter passen.

**Lineare Interpolation ist eine Näherung** (im Winter ist das Wasser abgestellt). Für wenige
Tage bis Wochen Verschiebung ist der Fehler klein; das README sagt das offen.

## Schritte

1. `billing_periods` + Modell; `MeterPeriod` (Start-/Endstand mit Herkunft); Interpolation im
   `ConsumptionCalculator` mit Tests. `forYear()` bleibt als Kurzform erhalten.
2. `BillingService`: Zeilen tragen den Zeitraum; Auswahl der Zähler anhand der Jahresgrenzen.
3. Oberfläche: Stichtag je Jahr setzen/entfernen (`manage-billing`), Stände und „≈“ in Tabelle
   und CSV.
4. README, `CLAUDE.md`.

## Testfälle

Zähler mit genau 0,100 m³ pro Tag: eingebaut 01.04.2024 bei 0.

| Ablesungen | Stichtage | Erwartung |
|------------|-----------|-----------|
| 17.10.2024: 20,000 · 17.10.2025: 56,500 · 17.10.2026: 93,000 | 30.09.2025, 30.09.2026 | Stand 30.09.2025 = 20,000 + 348 Tage × 0,1 = **54,800**; 30.09.2026 = **91,300**; Verbrauch 2026 = **36,500** |
| wie oben | nur 2026: 30.09.2026 | Verbrauch 2026 = 91,300 − 56,500 = **34,800** (schließt an die Ablesung 2025 an) |
| 17.10.2024: 20,000 · **20.12.2025**: 62,900 · 17.10.2026: 93,000 | keine | Differenz: 2025 = 42,900, 2026 = 30,100 (14 bzw. 10 Monate) |
| wie oben | 30.09.2025, 30.09.2026 | 2026 = 91,300 − 54,800 = **36,500** |
| 17.10.2026 letzte Ablesung | 31.10.2026 | „Ablesung fehlt“, keine Hochrechnung |
| Zähler ausgebaut 01.06.2026 mit Endstand 80,000 | 30.09.2026 | Endstand 80,000, nicht umgerechnet |
| Zähler eingebaut 15.10.2026 | 30.09.2026 | erscheint nicht in 2026 |
| 0,000 am 01.01. · 0,001 am 03.01. | 02.01. | 0,0005 → gerundet **0,001** |
| 36,500 m³ × 2,80 € + 12,00 € | | **114,20 €** |
| Stichtag 15.01.2027 für Jahr 2026 | | abgelehnt (liegt nicht im Jahr) |
| Pächter setzt Stichtag | | 403 |

## Nicht-Ziele

- Jahresabschluss mit festgeschriebenen Ständen (eine spätere Korrektur einer Ablesung ändert
  weiterhin die Abrechnung des betroffenen Jahres).
- Gewichtete Interpolation nach Saison (Gradtagszahlen o. Ä.).
