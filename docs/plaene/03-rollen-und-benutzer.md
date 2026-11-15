# 03 · Rollen und Benutzerverwaltung

## Ausgangslage

Jeder angemeldete Benutzer darf alles. Benutzer entstehen nur über den Seeder oder `tinker`.
Pächterinnen und Pächter haben keinen Zugang, obwohl „Wie hoch war mein Stand letztes Jahr?“
die häufigste Rückfrage an den Vorstand ist.

## Ziel

Drei Rollen mit klar getrennten Rechten, Benutzerverwaltung in der Oberfläche, und ein
Lesezugang für Pächter auf genau ihre Parzelle.

## Entscheidungen

**Rollen als Enum `Role` an `users.role`**, keine Rollen-/Rechte-Tabellen. Drei feste Rollen
reichen für einen Verein; ein konfigurierbares Rechtesystem wäre Infrastruktur ohne Anlass
(verworfen: `spatie/laravel-permission`).

**Rechte als benannte Gates**, nach dem benannt, was sie erlauben – nicht nach Rollen. Routen und
Views fragen nie „ist Vorstand?“, sondern „darf Ablesungen erfassen?“. So lässt sich die Zuordnung
an einer Stelle (`AppServiceProvider`) lesen und ändern:

| Gate | Vorstand | Kassierer/in | Pächter/in |
|------|:-------:|:------------:|:----------:|
| `view-club` – Übersicht, alle Parzellen, Abrechnung, Tarife ansehen | ✓ | ✓ | – |
| `view-plot` – eine Parzelle mit Zählern und Verlauf ansehen | ✓ | ✓ | nur die eigene |
| `record-readings` – erfassen, korrigieren, prüfen, importieren | ✓ | – | – |
| `manage-master-data` – Parzellen und Zähler pflegen | ✓ | – | – |
| `manage-billing` – Tarife pflegen (später: Rechnungen versenden) | ✓ | ✓ | – |
| `manage-users` – Benutzer anlegen, ändern, sperren | ✓ | – | – |

Die Kassiererin liest alles und pflegt, was Geld betrifft; Zählerstände ändert sie nicht
(Vier-Augen-Gedanke: wer abrechnet, ändert nicht die Grundlage).

**Pächter gehören zu genau einer Parzelle** (`users.plot_id`). Mehrere Parzellen pro Person sind
selten; dafür genügen zwei Zugänge. Nach dem Login landen Pächter direkt auf ihrer Parzelle,
ohne Bearbeiten-Links.

**Durchsetzung in den Routen** (`can:`-Middleware je Gruppe), nicht nur durch ausgeblendete
Links. Views blenden zusätzlich aus, was der Benutzer nicht darf.

**Benutzer sperren statt löschen, sobald Historie daran hängt.** Wer Ablesungen erfasst oder
geprüft hat, steht im Verlauf („Erfasst von“). Solche Benutzer werden gesperrt (`active = false`:
kein Login, laufende Sitzung endet), nicht gelöscht – analog zu Invariante 6.

**Der Verein darf sich nicht aussperren:** Der letzte aktive Vorstand kann weder gelöscht noch
gesperrt noch herabgestuft werden; den eigenen Zugang kann man nicht sperren oder löschen.

**Passwörter:** Der Vorstand vergibt ein Startpasswort; jeder Benutzer kann sein Passwort selbst
ändern (mit Abfrage des bisherigen). Kein „Passwort vergessen“ per E-Mail – der Vorstand setzt
ein neues.

## Schritte

1. Migration (`role`, `plot_id`, `active`), Enum `Role`, Gates; Tests der Rechte-Matrix.
2. Routen absichern, Pächter-Startseite, Views nach Rechten; HTTP-Tests je Rolle.
3. Benutzerverwaltung (Liste, Anlegen, Bearbeiten, Sperren, Löschen) mit Schutzregeln.
4. Eigenes Passwort ändern.
5. Demodaten (Kassiererin, Pächter), README, `CLAUDE.md`.

## Testfälle

| Wer | Aktion | Erwartung |
|-----|--------|-----------|
| Kassiererin | Ablesung speichern | 403 |
| Kassiererin | Tarif speichern, Abrechnung ansehen | erlaubt |
| Kassiererin | Benutzerliste | 403 |
| Pächter Parzelle 3 | `/` | Weiterleitung auf Parzelle 3 |
| Pächter Parzelle 3 | Parzelle 4, Parzellenliste, Abrechnung | 403 |
| Pächter Parzelle 3 | eigene Parzelle | sichtbar, ohne „Korrigieren“/„Ablesung erfassen“ |
| Vorstand | legt Pächter ohne Parzelle an | abgelehnt |
| Vorstand | löscht Benutzer, der Ablesungen erfasst hat | abgelehnt, Hinweis auf Sperren |
| Vorstand | sperrt den letzten aktiven Vorstand / sich selbst | abgelehnt |
| gesperrter Benutzer | Login | abgelehnt; bestehende Sitzung wird beendet |
| Benutzer | Passwort ändern mit falschem bisherigem Passwort | abgelehnt |

## Nicht-Ziele

- Selbstregistrierung, E-Mail-Verifizierung, „Passwort vergessen“.
- Mehrere Parzellen pro Pächterzugang.
- Zwei-Faktor-Anmeldung.

## Ergebnis

Umgesetzt wie geplant. Anmerkungen:

- Die Schutzregeln der Benutzerverwaltung liegen in einem Service (`UserAdministration`) statt
  im Form Request, weil „letzter aktiver Vorstand“ eine Abfrage unter Sperre braucht und für
  Ändern und Löschen gleichermaßen gilt.
- Ein gesperrter Vorstand zählt nicht als „weiterer Vorstand“; das stand so nicht im Plan und
  hat einen eigenen Test bekommen.
- Zusätzlich entstanden: eigene 403-Seite auf Deutsch (Pächter ohne zugeordnete Parzelle bekommen
  eine Erklärung statt „Forbidden“).
- Offen geblieben und ins README übernommen: kein „Passwort vergessen“ per E-Mail.
