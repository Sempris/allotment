# 05 · PDF-Abrechnung und E-Mail-Versand

## Ausgangslage

Die Jahresabrechnung gibt es als Tabelle und als CSV. Daraus baut der Kassierer heute in Excel
oder Word je Parzelle ein Anschreiben. Wer seine Abrechnung schon bekommen hat, steht nirgends.

## Ziel

Je Parzelle und Jahr eine Abrechnung als PDF, die der Verein per E-Mail verschicken oder
ausgedruckt übergeben kann. Die Abrechnungsseite zeigt den Stand je Parzelle (offen, versendet,
übergeben). Pächter können ihre Abrechnung selbst herunterladen, sobald sie herausgegeben wurde.

## Entscheidungen

**PDF mit `dompdf/dompdf`** (reines PHP, eine neue Abhängigkeit). Die Abrechnung ist eine
Blade-Ansicht mit einfachem CSS; mehr braucht eine einseitige Tabelle nicht.

- Verworfen: `barryvdh/laravel-dompdf` – nur eine Fassade um dieselbe Bibliothek; ein eigener
  kleiner Service (`InvoiceRenderer`) reicht und ist leichter zu testen.
- Verworfen: Headless-Chrome (Browsershot) – zusätzliche Systemabhängigkeit auf dem Server eines
  Vereins.

**Die PDF zeigt, worauf der Betrag beruht:** je Zähler Anfangs- und Endstand mit Datum, Verbrauch,
Preis je Einheit, Grundgebühr, Betrag. Umgerechnete Stände tragen das ≈ aus Plan 04 mit Fußnote.
Dafür trägt die Abrechnungszeile zusätzlich ihren Tarif.

**Herausgegeben wird nur, was vollständig und geprüft ist.** Eine Abrechnung lässt sich nur
versenden oder als übergeben markieren, wenn

1. alle Zähler der Parzelle einen Verbrauch und einen Tarif haben und
2. keine Ablesung dieser Zähler noch „zur Prüfung markiert“ ist.

Punkt 2 ist der Grund, warum es die Prüfliste gibt: Ein möglicher Zahlendreher soll nicht als
Rechnung beim Pächter landen. (Demodaten: Parzelle 7 mit dem Rohrbruch.)

**Versandprotokoll** (`invoice_dispatches`): Parzelle, Jahr, Empfängeradresse (leer = ausgedruckt
übergeben), Betrag zum Zeitpunkt der Herausgabe, wer, wann. Daraus ergibt sich:

- Status je Parzelle auf der Abrechnungsseite.
- „Alle offenen versenden“ überspringt bereits herausgegebene Abrechnungen.
- **Warnung, wenn sich der Betrag seit der Herausgabe geändert hat** (z. B. durch eine spätere
  Korrektur). Das ersetzt keinen Jahresabschluss, macht die Abweichung aber sichtbar.

**Pächter sehen ihre Abrechnung erst nach der Herausgabe** (Eintrag im Versandprotokoll), nicht
schon, sobald zufällig alle Zahlen da sind.

**Versand synchron, Fehler sichtbar.** Bei 50 Parzellen einmal im Jahr braucht es keine Queue.
Schlägt der Versand fehl, wird nichts protokolliert und die Meldung nennt die Parzelle.
In der Demo schreibt `MAIL_MAILER=log` die E-Mails ins Log.

**Vereinsdaten** (Name, Anschrift, Bankverbindung, Zahlungsziel) kommen aus `config/club.php`
bzw. `.env`. Eine Pflegemaske dafür lohnt sich nicht; sie ändern sich alle paar Jahre.

**Keine Rechnung im steuerlichen Sinn:** Das Dokument heißt „Jahresabrechnung“ und trägt als
Zahlungsreferenz Jahr und Parzelle. Fortlaufende Rechnungsnummern und Umsatzsteuer sind für die
Umlage in einem Verein nicht erforderlich.

## Schritte

1. Abhängigkeit, `config/club.php`, `BillingService::forPlot()`, Tarif und offene Prüfungen in
   der Abrechnungszeile.
2. `InvoiceRenderer` + PDF-Ansicht + Download-Route (Recht `view-plot`).
3. Versandprotokoll, `InvoiceDispatcher` (E-Mail, „übergeben“), `InvoiceMail`.
4. Abrechnungsseite: Status, PDF, Senden, „Alle offenen versenden“, Warnung bei geändertem Betrag.
5. Pächteransicht: herausgegebene Abrechnungen auf der eigenen Parzelle.
6. README, `CLAUDE.md`.

## Testfälle

Parzelle 4, Familie Schulte, 2026: Wasser 36,500 m³ × 2,80 € + 12,00 € = 114,20 €;
Strom 152,7 kWh × 0,37 € + 18,00 € = 74,50 €; Summe **188,70 €**.

| Fall | Erwartung |
|------|-----------|
| PDF abrufen (Vorstand) | `application/pdf`, beginnt mit `%PDF`; Ansicht enthält 114,20 €, 74,50 €, 188,70 €, IBAN |
| Senden mit hinterlegter E-Mail | eine E-Mail an diese Adresse mit PDF-Anhang; Protokoll: 18 870 Cent |
| Senden ohne E-Mail | abgelehnt mit Hinweis; „übergeben“ ist möglich |
| Ablesung fehlt / Tarif fehlt | kein PDF (404 mit Erklärung), kein Versand |
| eine Ablesung noch zur Prüfung markiert | Versand abgelehnt; nach „Ist korrekt“ möglich |
| „Alle offenen versenden“ bei 3 Parzellen (1 versendet, 1 ohne E-Mail, 1 offen) | genau 1 E-Mail; Meldung nennt 1 versendet, 1 ohne E-Mail |
| Korrektur nach Versand ändert Betrag | Abrechnungsseite warnt mit altem Betrag |
| Pächter vor Herausgabe | PDF 403 |
| Pächter nach Herausgabe | PDF der eigenen Parzelle; fremde Parzelle 403 |
| Kassiererin | darf senden; Pächter nicht (403) |

## Nicht-Ziele

- Archivierte PDF-Dateien (die PDF wird bei jedem Abruf aus den aktuellen Daten erzeugt).
- Zahlungseingänge, Mahnungen, SEPA-Lastschrift.
- Fortlaufende Rechnungsnummern, Umsatzsteuer.

## Ergebnis

Umgesetzt wie geplant. Abweichungen und Beobachtungen:

- **Reihenfolge:** Die Pächteransicht (Schritt 5) wurde vor der Abrechnungsseite (Schritt 4)
  committet, weil die HTTP-Tests des Versands beides zusammen brauchen.
- **Layout der PDF** zweimal nachgeschärft, nachdem die erste Fassung gerendert und angesehen
  wurde: Die Tabelle lief rechts über den Seitenrand, „Stand von/bis“ brach unschön um.
- **Demodaten** hatten keine E-Mail-Adressen; „Alle offenen versenden“ hätte in der Demo nichts
  versendet. Sechs Parzellen haben jetzt eine Adresse, zwei nicht. Ein Test hält Demodaten und
  README synchron.
- `User::hasReadingHistory()` heißt jetzt `hasHistory()` und zählt versendete Abrechnungen mit:
  Auch die Kassiererin, die Abrechnungen versendet hat, wird gesperrt statt gelöscht.
- Eine Parzelle mit herausgegebenen Abrechnungen lässt sich nicht löschen (ergänzt Plan 02).
- Im Browser mit den Demodaten geprüft: drei E-Mails mit PDF-Anhang im Mail-Log, Parzellen 2 und 6
  als „ohne E-Mail-Adresse“, Parzellen 5, 7 und 8 als „nicht vollständig oder nicht geprüft“ gemeldet.
