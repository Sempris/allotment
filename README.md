# 🌱 Zählerstand – Zählerablesung für Kleingartenvereine

Eine kleine Laravel-Anwendung, mit der der Vorstand eines Kleingartenvereins die jährlichen
Wasser- und Stromzählerstände aller Parzellen erfasst, auf Plausibilität prüft und daraus die
Jahresabrechnung erstellt.

Demo-Bewerbung für InnoGE · Wadim Skworzow · [Portfolio](https://sempris.github.io/portfolio-website)

## Die Idee

In vielen Kleingartenvereinen läuft die Zählerablesung so: Einmal im Jahr gehen zwei
Vorstandsmitglieder mit einem Zettel von Parzelle zu Parzelle, tippen die Werte später in Excel ab
und rechnen Wasser und Strom pro Parzelle ab. Dabei passieren immer dieselben Fehler:

- **Zahlendreher und Tippfehler** (83 statt 38 m³) fallen erst auf, wenn sich jemand über die Rechnung beschwert.
- **Zählertausch** bringt die Rechnung durcheinander, weil der neue Zähler wieder bei 0 beginnt.
- **Korrekturen** überschreiben den alten Wert, und später weiß niemand mehr, was ursprünglich notiert war.

Die App setzt genau dort an: Sie lehnt unmögliche Werte sofort ab, markiert auffällige Werte zur
Prüfung, bildet Zählertausch sauber ab und behält bei Korrekturen den ursprünglichen Wert im Verlauf.

## Funktionen

- **Parzellen und Zähler** verwalten, inklusive **Zählertausch**: Der Endstand des alten Zählers ist
  Pflicht und wird als Ablesung gespeichert, damit der Verbrauch bis zum Ausbau abgerechnet wird.
- **Stammdaten pflegen:** Parzellen (z. B. Pächterwechsel), Zähler und Tarife bearbeiten; Zähler
  auch ohne Ersatz ausbauen und einen Ausbau zurücknehmen. Gelöscht werden kann nur, woran keine
  Historie hängt (Parzelle ohne Zähler, Zähler ohne Ablesungen).
- **Ablesungen erfassen**, einzeln im Formular oder als **CSV-Import** für den ganzen Ablesetag.
- **Plausibilitätsprüfung** für jeden Wert, egal über welchen Weg er kommt:
  - *abgelehnt:* Stand kleiner als der vorherige, größer als ein späterer, Datum in der Zukunft,
    Zähler an dem Tag nicht eingebaut, zweite Ablesung am selben Tag;
  - *zur Prüfung markiert:* Verbrauch mehr als dreimal so hoch wie der bisherige Durchschnitt des
    Zählers (oder, ohne ausreichende Historie, über einem großzügigen Höchstwert pro Parzelle).
- **Prüfliste** auf der Übersicht: auffällige Werte bestätigen („Ist korrekt“) oder korrigieren.
- **Korrekturen ohne Datenverlust:** Die ursprüngliche Ablesung bleibt durchgestrichen im Verlauf,
  die Korrektur braucht eine Begründung.
- **Rollen und Benutzerverwaltung:** Vorstand, Kassierer/in und Pächter/in mit Lesezugang auf die
  eigene Parzelle. Zugänge legt der Vorstand in der Oberfläche an; wer im Verlauf der Ablesungen
  steht, wird gesperrt statt gelöscht, und der letzte Vorstand kann sich nicht aussperren.
- **Jahresabrechnung** pro Parzelle mit Tarifen je Jahr (Preis je Einheit + Grundgebühr) und **CSV-Export** für Excel.
- **Abrechnung auf einen Stichtag:** Liegen die Ablesetage weit auseinander, lässt sich ein Jahr auf
  einen Stichtag umrechnen. Die Stände werden zwischen den Ablesungen interpoliert und sind überall
  mit ≈ gekennzeichnet; jede Zeile zeigt Anfangs- und Endstand mit Datum.
- Übersicht, welche Zähler im laufenden Jahr **noch nicht abgelesen** sind.

## Schnellstart

Voraussetzungen: PHP 8.2+, Composer, Node.js 20+, SQLite.

```bash
git clone <repo-url> zaehlerstand && cd zaehlerstand
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

Dann <http://localhost:8000> öffnen und anmelden. Alle Demo-Zugänge haben das Passwort `gartenzwerg`:

| E-Mail | Rolle | darf |
|--------|-------|------|
| `vorstand@example.org` | Vorstand | alles: Stammdaten, Ablesungen, Abrechnung, Benutzer |
| `kasse@example.org` | Kassierer/in | alles sehen, Tarife und Abrechnung pflegen, keine Zählerstände ändern |
| `paechter@example.org` | Pächter/in (Parzelle 3) | nur die eigene Parzelle sehen |

Die Demodaten enthalten acht Parzellen mit Ablesungen von 2023 bis 2026. Zwei Parzellen sind
für 2026 noch nicht abgelesen, und Parzelle 7 hat 2026 einen auffällig hohen Wasserverbrauch
(ein Rohrbruch), der in der Prüfliste erscheint.

Tests ausführen:

```bash
php artisan test   # oder: vendor/bin/phpunit
```

Unter `docs/beispiel-ablesung.csv` liegt eine Beispieldatei für den CSV-Import der beiden noch fehlenden Parzellen. Eine Zeile darin ist absichtlich falsch (Stand kleiner als im Vorjahr) und wird mit Begründung abgelehnt.

## Architekturentscheidungen

**Eine Stelle, an der Ablesungen geschrieben werden.** Formular, CSV-Import, Korrektur und sogar der
Seeder laufen über den `ReadingRecorder`. Dadurch gelten überall dieselben Regeln, und ein Import
kann keine Abkürzung an der Prüfung vorbei nehmen.

**Prüflogik getrennt vom Speichern.** Der `PlausibilityChecker` entscheidet nur, ob ein Wert stimmen
kann, und speichert nichts. Er unterscheidet harte Fehler (ein mechanischer Zähler läuft nicht
rückwärts → ablehnen) von weichen Warnungen (hoher Verbrauch kann ein Tippfehler oder ein echter
Rohrbruch sein → speichern, aber markieren). Die Entscheidung bleibt beim Vorstand.

**Keine Gleitkommazahlen.** Zählerstände werden als ganze Zahlen in Tausendstel gespeichert
(1 m³ = 1000), Preise in Cent. Gerundet wird genau einmal, beim Betrag, kaufmännisch auf ganze
Cent. Die Klasse `App\Support\Milli` übernimmt Einlesen (auch deutsches Format wie `1.234,567`) und Ausgabe.

**Jahre schließen lückenlos aneinander an.** Jedes Abrechnungsjahr endet je Zähler an einem
Grenzstand, das nächste beginnt dort: ohne Stichtag ist das die letzte Ablesung im Jahr, mit
Stichtag der interpolierte Stand an diesem Tag. So wird beim Umstellen kein Verbrauch doppelt oder
gar nicht abgerechnet. Interpoliert wird ganzzahlig; hochgerechnet wird nie, weil sich ein
hochgerechneter Wert – und damit eine verschickte Abrechnung – mit der nächsten Ablesung ändern würde.

**Ablesungen werden nie überschrieben.** Eine Korrektur legt eine neue Ablesung an und verweist die
alte darauf (`superseded_by_id`). Berechnungen nutzen nur die „wirksamen“ Ablesungen
(`effectiveReadings`). So bleibt nachvollziehbar, was geändert wurde, von wem und warum.

**Sperre gegen parallele Eingaben.** Beim Speichern wird der Zähler mit `lockForUpdate()` gesperrt,
damit zwei gleichzeitige Eingaben (z. B. Import und Formular) nicht beide die Prüfung „nicht kleiner
als der vorherige Stand“ gegen denselben alten Stand bestehen.

**CSV-Import zeilenweise.** Jede Zeile wird einzeln geprüft und gespeichert. Eine fehlerhafte Zeile
blockiert nicht den ganzen Ablesetag; das Ergebnis zeigt pro Zeile, was importiert, markiert oder
abgelehnt wurde und warum. Nur eine falsche Kopfzeile lehnt die ganze Datei ab.

**Rechte heißen nach dem, was sie erlauben.** Routen und Views fragen nie „ist Vorstand?“, sondern
`record-readings`, `manage-billing`, `view-plot` … Die Zuordnung Rolle → Recht steht an genau einer
Stelle (`AppServiceProvider`) und ist als Matrix getestet. Die Kassiererin rechnet ab, ändert aber
keine Zählerstände.

**Bewusst einfach gehalten:** serverseitig gerendertes Blade mit Tailwind CSS, kein
JavaScript-Framework. Für ein Werkzeug, das ein Vorstand einmal im Jahr intensiv nutzt, bringt eine
SPA keinen Mehrwert, aber zusätzlichen Wartungsaufwand.

Aufbau im Überblick:

```
app/
├── Enums/              MeterType (Wasser/Strom, Einheit, Höchstwert), ReadingSource, Role
├── Services/
│   ├── ReadingRecorder.php        einziger Schreibweg für Ablesungen (+ Korrektur)
│   ├── PlausibilityChecker.php    harte Regeln und Verbrauchswarnung
│   ├── MeterMaintenance.php       Zähler ändern, ausbauen, löschen – geprüft gegen die Ablesungen
│   ├── MeterExchange.php          Zählertausch: Endstand + Ausbau + neuer Zähler in einer Transaktion
│   ├── ConsumptionCalculator.php  Jahresverbrauch je Zähler, optional interpoliert auf einen Stichtag
│   ├── BillingService.php         Jahresabrechnung je Parzelle
│   ├── ReadingCsvImporter.php     zeilenweiser CSV-Import mit Ergebnisbericht
│   └── UserAdministration.php     Zugänge anlegen, sperren, löschen – ohne den Verein auszusperren
├── Support/Milli.php   Zählerstände und Geld ohne Floats
└── Http/               schlanke Controller, Validierung in Form Requests
```

## Qualitätssicherung

- **Automatisierte Tests** (PHPUnit, `php artisan test`): Einlesen von Zahlen, alle Plausibilitätsregeln,
  Erfassen, Ablehnen und Korrigieren über HTTP, Zählertausch, Prüfliste, CSV-Import mit gemischten
  Zeilen, Verbrauchs- und Kostenberechnung bis auf den Cent, Stichtagsumrechnung mit von Hand
  nachrechenbaren Beispielen (Zähler mit genau 0,1 m³ pro Tag), fehlende Ablesungen und Tarife,
  CSV-Export und Zugriffsschutz.
- **Fachliche Testfälle statt Zufallsdaten:** Die Tests rechnen konkrete Beispiele nach
  (z. B. 38,255 m³ × 2,80 € = 107,11 €), damit ein Fehler in der Rundung sofort auffällt.
- **Sicherheit:** alle Seiten nur nach Anmeldung, Rechte je Rolle in den Routen durchgesetzt
  (nicht nur ausgeblendete Links) und als Matrix getestet, Login mit Rate-Limit (5 Versuche pro Minute),
  CSRF-Schutz in allen Formularen, Validierung jeder Eingabe in Form Requests, Upload auf
  CSV/TXT bis 1 MB begrenzt, Ausgabe in Blade escaped.
- **Manuell geprüft:** alle Seiten mit den Demodaten im Browser durchgegangen, inklusive Import der Beispieldatei.

## Eingesetzte KI-Werkzeuge und Vorgehen

Die Anwendung ist mit **Claude (Anthropic) als Coding-Agent** entstanden. Mein Vorgehen:

1. **Fachlich vorgeben, nicht nur „bau mir eine App“:** Zuerst habe ich die Domäne und die
   Regeln festgelegt (harte Fehler vs. Warnungen, Korrektur ohne Überschreiben, ganzzahlige
   Beträge, ein Schreibweg für alle Eingaben). Viele davon kenne ich aus meiner Arbeit an einer
   ESG-Plattform, wo jede Kennzahl für die Prüfung nachvollziehbar sein muss.
2. **In kleinen Schritten umsetzen lassen:** Datenmodell → Services → HTTP-Schicht → Oberfläche → Tests.
3. **Ergebnisse kritisch prüfen:** Jeder Schritt wurde gelesen und gegen die Regeln geprüft,
   bevor der nächste kam. Die Tests rechnen Beispiele mit festen Zahlen nach, statt nur zu prüfen,
   dass „etwas“ gespeichert wurde.
4. **Bewusst entscheiden, was nicht gebaut wird:** siehe „Bekannte Grenzen“.

## Bekannte Grenzen

- **Feste Rollen, ein Pächterzugang je Parzelle:** Die drei Rollen sind im Code festgelegt, nicht
  konfigurierbar. Es gibt kein „Passwort vergessen“ per E-Mail (der Vorstand setzt ein neues
  Startpasswort) und keine Zwei-Faktor-Anmeldung.
- **Stichtagsabrechnung ist eine Näherung:** Die Umrechnung auf einen Stichtag nimmt gleichmäßigen
  Verbrauch zwischen zwei Ablesungen an (im Winter ist das Wasser abgestellt). Für wenige Wochen
  Verschiebung ist der Fehler klein. Es wird nie über die letzte Ablesung hinaus hochgerechnet.
- **Kein Jahresabschluss:** Abrechnungen werden nicht festgeschrieben. Wird eine Ablesung später
  korrigiert, ändert sich die Abrechnung des betroffenen Jahres.
- **Kein Änderungsprotokoll für Stammdaten:** Ablesungen behalten ihre Historie, geänderte
  Stammdaten (Pächtername, Anfangsstand eines Zählers) nicht. Ältere Abrechnungen zeigen deshalb
  den aktuellen Pächternamen.
- **Keine Rechnungen als PDF** und kein Versand per E-Mail, nur der CSV-Export.
- **SQLite** reicht für einen Verein. Die Sperre mit `lockForUpdate()` wirkt erst mit MySQL oder
  PostgreSQL wirklich; SQLite sperrt ohnehin die ganze Datenbank beim Schreiben.

## Mögliche nächste Schritte

- PDF-Rechnung pro Parzelle und Versand an die hinterlegte E-Mail-Adresse
- Ablesung per Smartphone direkt an der Parzelle (die Formulare sind bereits responsiv)
- Foto des Zählers zur Ablesung speichern, als Beleg bei Rückfragen
