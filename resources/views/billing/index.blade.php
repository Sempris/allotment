@php use App\Support\Milli; @endphp
<x-layout :title="'Jahresabrechnung '.$year">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <h1 class="text-2xl font-semibold">Jahresabrechnung {{ $year }}</h1>
        <div class="flex items-center gap-3">
            <form method="GET" action="{{ route('billing.index') }}" class="flex items-center gap-2 text-sm">
                <label>Jahr <input type="number" name="year" value="{{ $year }}" min="2000" max="2100" class="input mt-0 w-24"></label>
                <button class="btn-secondary">Anzeigen</button>
            </form>
            <a class="btn" href="{{ route('billing.csv', ['year' => $year]) }}">CSV herunterladen</a>
        </div>
    </div>

    @include('partials.errors')

    @if ($missingTariffs !== [])
        <div class="mb-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Für {{ $year }} fehlt der Tarif für: {{ collect($missingTariffs)->map->label()->join(', ') }}.
            <a class="link" href="{{ route('tariffs.index') }}">Tarif anlegen</a>
        </div>
    @endif

    <section class="card mb-6 text-sm">
        <h2 class="mb-2 font-semibold">Abrechnungszeitraum</h2>
        @if ($keyDate)
            <p class="mb-3 max-w-3xl text-stone-600">
                Abgerechnet wird auf den <strong>Stichtag {{ $keyDate->format('d.m.Y') }}</strong>. Die Zählerstände werden linear zwischen der Ablesung davor und der Ablesung
                am oder nach dem Stichtag umgerechnet und sind mit ≈ gekennzeichnet. Zähler ohne Ablesung am oder nach dem Stichtag gelten als nicht abgelesen – es wird nichts hochgerechnet.
            </p>
        @else
            <p class="mb-3 max-w-3xl text-stone-600">
                Abgerechnet wird je Zähler bis zur <strong>letzten Ablesung im Jahr</strong>. Das passt, wenn alle Zähler um denselben Tag abgelesen werden.
                Liegen die Ablesetage weit auseinander, lässt sich das Jahr auf einen Stichtag umrechnen, damit alle Parzellen für denselben Zeitraum zahlen.
            </p>
        @endif
        @can('manage-billing')
            <div class="flex flex-wrap items-end gap-3">
                <form method="POST" action="{{ route('billing.key-date.store') }}" class="flex items-end gap-2">
                    @csrf
                    <input type="hidden" name="year" value="{{ $year }}">
                    <label>Stichtag
                        <input type="date" name="key_date" value="{{ old('key_date', $suggestedKeyDate) }}" min="{{ $year }}-01-01" max="{{ $year }}-12-31" required class="input">
                    </label>
                    <button class="btn-secondary">{{ $keyDate ? 'Stichtag ändern' : 'Auf Stichtag abrechnen' }}</button>
                </form>
                @if ($keyDate)
                    <form method="POST" action="{{ route('billing.key-date.destroy') }}">
                        @csrf @method('DELETE')
                        <input type="hidden" name="year" value="{{ $year }}">
                        <button class="link">Stichtag entfernen</button>
                    </form>
                @endif
            </div>
            <p class="mt-2 text-xs text-stone-500">Der Stichtag sollte am oder vor dem frühesten Ablesetag liegen, z. B. 30.09. bei Ablesung Mitte Oktober.</p>
        @endcan
    </section>

    <div class="overflow-x-auto rounded-lg border border-stone-200 bg-white">
        <table class="table">
            <thead><tr><th>Parzelle</th><th>Pächter/in</th><th>Zähler</th><th>Von</th><th>Bis</th><th class="text-right">Verbrauch</th><th class="text-right">Betrag</th></tr></thead>
            <tbody>
            @forelse ($rows as $row)
                @foreach ($row['lines'] as $i => $line)
                    <tr>
                        @if ($i === 0)
                            <td rowspan="{{ count($row['lines']) + 1 }}" class="align-top font-semibold">{{ $row['plot']->number }}</td>
                            <td rowspan="{{ count($row['lines']) + 1 }}" class="align-top">{{ $row['plot']->tenant_name }}</td>
                        @endif
                        <td>{{ $line->meter->type->label() }} {{ $line->meter->serial_number }}</td>
                        @if ($line->period)
                            @foreach ([$line->period->start, $line->period->end] as $value)
                                <td class="whitespace-nowrap text-stone-600" title="{{ $value->originLabel() }}">
                                    {{ $value->date->format('d.m.Y') }}: {{ $value->isInterpolated() ? '≈ ' : '' }}{{ Milli::format($value->milli) }}
                                </td>
                            @endforeach
                            <td class="whitespace-nowrap text-right">{{ $line->isInterpolated() ? '≈ ' : '' }}{{ Milli::format($line->consumptionMilli) }} {{ $line->meter->type->unit() }}</td>
                        @else
                            <td colspan="3" class="text-right text-amber-800">{{ $keyDate ? 'Ablesung am oder nach dem Stichtag fehlt' : 'Ablesung fehlt' }}</td>
                        @endif
                        <td class="whitespace-nowrap text-right">{{ $line->costCents === null ? '–' : Milli::formatCents($line->costCents) }}</td>
                    </tr>
                @endforeach
                @if ($row['lines'] === [])
                    <tr>
                        <td class="font-semibold">{{ $row['plot']->number }}</td>
                        <td>{{ $row['plot']->tenant_name }}</td>
                        <td colspan="5" class="text-stone-500">Kein Zähler in diesem Abrechnungsjahr.</td>
                    </tr>
                @else
                    <tr class="bg-stone-50">
                        <td colspan="4" class="text-sm text-stone-500">{{ $row['complete'] ? 'Summe' : 'Summe (unvollständig)' }}</td>
                        <td class="whitespace-nowrap text-right font-semibold">{{ Milli::formatCents($row['total_cents']) }}</td>
                    </tr>
                @endif
            @empty
                <tr><td colspan="7" class="text-stone-500">Noch keine Parzellen angelegt.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($keyDate)
        <p class="mt-3 text-xs text-stone-500">≈ auf den Stichtag umgerechnet. Die Umrechnung nimmt einen gleichmäßigen Verbrauch zwischen zwei Ablesungen an.</p>
    @endif
</x-layout>
