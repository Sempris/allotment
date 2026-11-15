Guten Tag {{ $plot->tenant_name }},

im Anhang finden Sie die Jahresabrechnung {{ $year }} für Wasser und Strom Ihrer Parzelle {{ $plot->number }}.

Zu zahlen: {!! \App\Support\Milli::formatCents($totalCents) !!}

Die Abrechnung zeigt je Zähler den Anfangs- und Endstand, den Verbrauch und den Preis.
Zahlungsziel und Bankverbindung stehen ebenfalls im Anhang.

Bei Fragen zu Ihren Zählerständen antworten Sie einfach auf diese E-Mail.

Freundliche Grüße
{{ $club['name'] }}
