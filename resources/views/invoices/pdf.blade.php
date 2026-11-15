@php use App\Support\Milli; @endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Jahresabrechnung {{ $year }} · Parzelle {{ $plot->number }}</title>
    <style>
        @page { margin: 22mm 20mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #1c1917; line-height: 1.4; }
        h1 { font-size: 16pt; margin: 0 0 2mm; }
        .club { font-size: 9pt; color: #57534e; border-bottom: 1px solid #a8a29e; padding-bottom: 3mm; margin-bottom: 12mm; }
        .club strong { color: #166534; font-size: 11pt; }
        .address { margin-bottom: 12mm; }
        .meta { color: #57534e; margin-bottom: 8mm; }
        table { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
        .nowrap { white-space: nowrap; }
        th { text-align: left; font-size: 7.5pt; color: #57534e; border-bottom: 1px solid #a8a29e; padding: 2mm 1mm; }
        td { padding: 2mm 1mm; border-bottom: 1px solid #e7e5e4; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .small { font-size: 7.5pt; color: #57534e; }
        .total td { border-top: 1px solid #1c1917; border-bottom: none; font-weight: bold; font-size: 11pt; }
        .payment { margin-top: 10mm; }
        .note { margin-top: 6mm; font-size: 8pt; color: #57534e; }
    </style>
</head>
<body>
    <div class="club">
        <strong>{{ $club['name'] }}</strong><br>
        {{ $club['address'] }} · {{ $club['email'] }}
    </div>

    <div class="address">
        {{ $plot->tenant_name }}<br>
        Parzelle {{ $plot->number }}
    </div>

    <h1>Jahresabrechnung {{ $year }}</h1>
    <div class="meta">Wasser und Strom · Parzelle {{ $plot->number }} · erstellt am {{ $issuedOn->format('d.m.Y') }}</div>

    <table>
        <thead>
            <tr>
                <th>Zähler</th>
                <th>Stand von</th>
                <th>Stand bis</th>
                <th class="num">Verbrauch</th>
                <th class="num">Preis/Einheit</th>
                <th class="num">Grundgebühr</th>
                <th class="num">Betrag</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($lines as $line)
            @php($unit = $line->meter->type->unit())
            <tr>
                <td class="nowrap">{{ $line->meter->type->label() }}<br><span class="small">Nr. {{ $line->meter->serial_number }}</span></td>
                @foreach ([$line->period->start, $line->period->end] as $value)
                    <td class="nowrap">{{ $value->isInterpolated() ? '≈ ' : '' }}{{ Milli::format($value->milli) }} {{ $unit }}<br><span class="small">{{ $value->date->format('d.m.Y') }}<br>{{ $value->originLabel() }}</span></td>
                @endforeach
                <td class="num">{{ $line->isInterpolated() ? '≈ ' : '' }}{{ Milli::format($line->consumptionMilli) }} {{ $unit }}</td>
                <td class="num">{{ Milli::formatCents($line->tariff->price_per_unit_cents) }}</td>
                <td class="num">{{ Milli::formatCents($line->tariff->base_fee_cents) }}</td>
                <td class="num">{{ Milli::formatCents($line->costCents) }}</td>
            </tr>
        @endforeach
            <tr class="total">
                <td colspan="6">Zu zahlen</td>
                <td class="num">{{ Milli::formatCents($totalCents) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($hasInterpolatedValues)
        <p class="note">≈ Der Zählerstand wurde auf den Abrechnungsstichtag umgerechnet: linear zwischen der Ablesung davor und der Ablesung danach.</p>
    @endif

    <div class="payment">
        Bitte überweisen Sie <strong>{{ Milli::formatCents($totalCents) }}</strong> bis zum <strong>{{ $dueOn->format('d.m.Y') }}</strong> auf folgendes Konto:<br><br>
        {{ $club['bank']['holder'] }}<br>
        IBAN {{ $club['bank']['iban'] }} · {{ $club['bank']['name'] }}<br>
        Verwendungszweck: {{ $reference }}
    </div>

    <p class="note">Betrag je Zähler = Verbrauch × Preis je Einheit, kaufmännisch auf ganze Cent gerundet, zuzüglich Grundgebühr. Bei Fragen zu Ihren Zählerständen wenden Sie sich bitte an den Vorstand ({{ $club['email'] }}).</p>
</body>
</html>
