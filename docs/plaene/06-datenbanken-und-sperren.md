# 06 · MySQL/PostgreSQL und Sperren

## Ausgangslage

Das README sagt: „Die Sperre mit `lockForUpdate()` wirkt erst mit MySQL oder PostgreSQL wirklich;
SQLite sperrt ohnehin die ganze Datenbank beim Schreiben.“ Beides war bisher behauptet, nicht
geprüft: Die Tests laufen nur auf SQLite im Speicher, und kein Test lässt zwei Eingaben
tatsächlich gleichzeitig laufen.

Vor dem Plan gemessen (lokales PostgreSQL 16, zwei echte Prozesse, Prüfung künstlich um 0,4 s
verlangsamt, damit sich die Eingaben sicher überschneiden):

| Datenbank | Ergebnis bei zwei widersprüchlichen parallelen Ablesungen |
|-----------|-----------------------------------------------------------|
| PostgreSQL mit `lockForUpdate()` | eine gespeichert, eine sauber abgelehnt ✓ |
| PostgreSQL ohne Sperre (testweise entfernt) | **beide gespeichert** – genau der Fehler, den die Sperre verhindern soll |
| SQLite (Datei) | eine gespeichert, die andere bricht mit **„database is locked“** ab (Fehlerseite statt Meldung) |

Die bestehende Testsuite läuft auf PostgreSQL unverändert grün. MySQL ist lokal nicht verfügbar.

## Ziel

- Auf allen drei Datenbanken gilt: Von zwei widersprüchlichen gleichzeitigen Eingaben wird genau
  eine gespeichert, die andere bekommt die normale fachliche Ablehnung.
- Das ist durch einen Test belegt, der auf allen drei Datenbanken in der CI läuft.
- Die gesamte Suite läuft in der CI gegen SQLite, MySQL und PostgreSQL.

## Entscheidungen

**Ein Test mit zwei echten Prozessen** (`ConcurrentReadingsTest`). Zwei Ablesungen, die jede für
sich gültig sind, aber nicht zusammen (01.06.: 150 m³ und 17.10.: 120 m³). Der Test nutzt echte
Commits statt der Test-Transaktion und räumt danach auf. Er muss ohne Sperre fehlschlagen –
sonst beweist er nichts (oben geprüft).

- Verworfen: *Sperre nur per Mock prüfen.* Würde zeigen, dass `lockForUpdate()` aufgerufen wird,
  nicht, dass es wirkt – auf SQLite wird es aufgerufen und ignoriert.

**SQLite: Schreibsperre am Anfang der Transaktion.** Geplant ist `transaction_mode = IMMEDIATE`
plus `busy_timeout` in `config/database.php`: Dann wartet die zweite Transaktion, statt nach dem
Lesen am Schreiben zu scheitern.

**CI-Matrix** `sqlite | mysql | pgsql` mit Service-Containern. `phpunit.xml` setzt die
Datenbank nur als Vorgabe; Umgebungsvariablen des Jobs haben Vorrang.

**SQLite bleibt die Vorgabe** für Demo und kleine Vereine; `.env.example` und README zeigen die
Umstellung auf MySQL/PostgreSQL.

## Schritte

1. Paralleltest + Korrektur für SQLite.
2. CI-Matrix für alle drei Datenbanken; beheben, was dort auffällt.
3. `.env.example`, README, `CLAUDE.md`.

## Testfälle

| Fall | Erwartung |
|------|-----------|
| A (01.06.2026: 150,000) startet 0,1 s vor B (17.10.2026: 120,000) | A gespeichert, B abgelehnt, 2 wirksame Ablesungen |
| B startet vor A | B gespeichert, A abgelehnt („größer als die spätere Ablesung“) |
| jeweils auf SQLite, MySQL, PostgreSQL | gleiches Ergebnis, keine Fehlermeldung der Datenbank |

## Nicht-Ziele

- Lasttests oder mehr als zwei gleichzeitige Schreiber.
- Betriebsthemen (Backups, Replikation).
