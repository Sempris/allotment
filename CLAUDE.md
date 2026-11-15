# Agent-Kontext: Zählerstand

Diese Datei ist der feste Kontext für den Coding-Agenten (Claude Code). Sie beschreibt, was die
Anwendung fachlich garantieren muss, wie im Repository gearbeitet wird und was der Agent nicht
ohne Rückfrage ändern darf. Sie wird mit dem Code gepflegt: Ändert sich eine Regel, ändert sich
diese Datei im selben Commit.

## Worum es geht

Laravel-12-Anwendung für Kleingartenvereine: Wasser- und Stromzählerstände je Parzelle erfassen,
auf Plausibilität prüfen, Jahresabrechnung erstellen. Nutzer sind Ehrenamtliche, die das Werkzeug
einmal im Jahr intensiv verwenden. Deshalb: klare deutsche Fehlermeldungen, keine stillen
Annahmen, lieber ablehnen oder markieren als raten.

## Befehle

```bash
composer install && npm install        # Abhängigkeiten
php artisan migrate:fresh --seed       # Demodaten (vorstand@ / kasse@ / paechter@example.org, Passwort gartenzwerg)
php artisan test                       # gesamte Testsuite (SQLite im Speicher)
php artisan test --filter=BillingTest  # einzelne Testklasse
vendor/bin/pint --dirty                # Codestil für geänderte Dateien
npm run build                          # Tailwind/Vite-Build
```

## Fachliche Invarianten (nicht verhandelbar)

1. **Ein Schreibweg für Ablesungen.** Jede Ablesung entsteht über `App\Services\ReadingRecorder`
   (Formular, CSV-Import, Korrektur, Zählertausch, Seeder). Kein `Reading::create()` außerhalb
   dieses Service und der Tests-Factories.
2. **Ablesungen werden nie überschrieben oder gelöscht.** Korrekturen legen eine neue Ablesung an
   und setzen `superseded_by_id` am Original. Berechnungen nutzen nur `effectiveReadings`.
3. **Keine Gleitkommazahlen für Stände und Geld.** Zählerstände in Tausendstel (`*_milli`),
   Beträge in Cent (`*_cents`), beides `int`. Gerundet wird kaufmännisch und mit Ganzzahl-Arithmetik
   (`intdiv`), nie mit `round()` auf Floats. Ein-/Ausgabe über `App\Support\Milli`.
4. **Harte Fehler ablehnen, weiche Auffälligkeiten markieren.** Der `PlausibilityChecker`
   speichert nichts und entscheidet nur. Die Entscheidung über markierte Werte trifft ein Mensch.
5. **Nichts schätzen, ohne es zu kennzeichnen.** Fehlt eine Ablesung oder ein Tarif, zeigt die
   Abrechnung das an. Rechnerisch ermittelte Werte (z. B. Stichtagsumrechnung) sind in Oberfläche,
   CSV und PDF als solche erkennbar (≈). Zählerstände werden nur zwischen zwei Ablesungen
   interpoliert, nie über die letzte Ablesung hinaus hochgerechnet.
6. **Stammdaten mit Historie werden nicht gelöscht.** Löschen ist nur erlaubt, solange nichts
   daran hängt (Parzelle ohne Zähler, Zähler ohne Ablesungen).
7. **Ein ausgebauter Zähler hat einen Endstand.** `removed_on` wird nur von
   `MeterMaintenance::retire()` gesetzt; am Ausbautag gibt es eine wirksame Ablesung, danach keine.
8. **Rechte werden in den Routen durchgesetzt.** Jede Route liegt in einer `can:`-Gruppe mit einem
   benannten Gate aus `AppServiceProvider::defineAbilities()`. Code fragt nie nach der Rolle,
   sondern nach dem Recht. Eine neue Route ohne Gate ist ein Fehler; dazu gehört ein Test, der
   den Zugriff für eine nicht berechtigte Rolle mit 403 belegt.
9. **Wer im Verlauf steht, verschwindet nicht.** Benutzer mit erfassten oder geprüften Ablesungen
   werden gesperrt, nicht gelöscht. Es gibt immer mindestens einen aktiven Vorstand.
10. **Abrechnungsjahre schließen lückenlos an.** Verbrauch(J) = Grenzstand(J) − letzter bestimmbarer
    Grenzstand davor. Wer die Verbrauchsberechnung ändert, muss diese Kette erhalten (Tests in
    `KeyDateConsumptionTest`).

## Konventionen

- **Sprache:** Code, Bezeichner und Code-Kommentare auf Englisch. Oberfläche, Fehlermeldungen,
  Commit-Nachrichten, README und Pläne auf Deutsch.
- **Schichten:** Controller bleiben schlank. Validierung in Form Requests (`app/Http/Requests`),
  Fachlogik in Services (`app/Services`), Berechtigungen über Gates/Policies – nicht in Views.
- **Views:** serverseitiges Blade mit Tailwind-Klassen aus `resources/css/app.css` (`.btn`,
  `.card`, `.input`, `.table`, `.link`). Kein JavaScript-Framework, kein Inline-Skript.
- **Datenbank:** Schema-Änderungen nur über neue Migrationen; bestehende Migrationen nicht
  umschreiben. Abfragen müssen auf SQLite, MySQL und PostgreSQL laufen (keine DB-spezifischen
  Funktionen; Sortierung nach Parzellennummer in PHP mit `SORT_NATURAL`).
- **Tests:** Jede fachliche Regel bekommt einen Test mit konkreten, nachgerechneten Zahlen
  (z. B. 38,255 m³ × 2,80 € = 107,11 €), kein „irgendwas wurde gespeichert“. Feature-Tests gehen
  über HTTP, wenn die Regel den Benutzer betrifft. Zeit wird mit `Carbon::setTestNow()` fixiert.
- **Validierungstexte:** Neue Regeln brauchen einen Eintrag in `lang/de/validation.php`.

## Arbeitsweise mit dem Agenten

1. **Erst der Plan.** Für jede Aufgabe, die mehr als eine Datei betrifft, entsteht zuerst ein Plan
   unter `docs/plaene/NN-thema.md`: Ausgangslage, Ziel, Entscheidungen mit verworfenen
   Alternativen, Schritte, Testfälle, Nicht-Ziele. Der Plan wird **vor** dem Code committet.
2. **Kleine Commits entlang der Planschritte.** Ein Commit = ein Schritt, der für sich
   verständlich ist und die Tests grün lässt (Migration + Modell, dann Service, dann HTTP/Views,
   dann README). Tests kommen im selben Commit wie das Verhalten, das sie absichern.
3. **Vor jedem Commit:** `php artisan test` und `vendor/bin/pint --dirty`. Rote Tests werden
   nicht committet und nicht deaktiviert.
4. **Abweichungen festhalten.** Weicht die Umsetzung vom Plan ab, wird das im Plan unter
   „Ergebnis“ notiert, statt den Plan nachträglich passend zu schreiben.
5. **Doku zum Schluss:** README (Funktionen, Bekannte Grenzen) und diese Datei aktualisieren.

Commit-Nachrichten: deutsch, Imperativ-frei beschreibend, mit Bereich vorweg, z. B.
`Zählertausch: Endstand des alten Zählers wird erzwungen`.

## Was der Agent nicht ohne Rückfrage tut

- Invarianten aus dem Abschnitt oben aufweichen, auch nicht „nur für einen Sonderfall“.
- Bestehende Tests löschen, überspringen oder ihre erwarteten Zahlen ändern.
- Neue Composer- oder npm-Abhängigkeiten einführen, die nicht im Plan begründet sind.
- Zugangsdaten, `.env` oder echte personenbezogene Daten committen.
- Bestehende Migrationen ändern oder Git-Historie umschreiben.

## Orientierung im Code

```
app/Enums/        MeterType, ReadingSource, Role
app/Services/     ReadingRecorder (Schreibweg), PlausibilityChecker, ConsumptionCalculator,
                  BillingService, ReadingCsvImporter, MeterMaintenance (einzige Stelle, die
                  Zähler ändert/ausbaut/löscht), MeterExchange, UserAdministration
app/Providers/    AppServiceProvider: Zuordnung Rolle → Recht (Gates)
app/Support/      Milli (Einlesen/Ausgabe von Ständen und Beträgen)
app/Http/         Controller + Form Requests
docs/plaene/      Pläne je Aufgabe, in der Reihenfolge der Umsetzung
tests/Feature/    fachliche Tests über HTTP und Services
```
