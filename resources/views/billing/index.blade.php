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

    @if ($missingTariffs !== [])
        <div class="mb-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Für {{ $year }} fehlt der Tarif für: {{ collect($missingTariffs)->map->label()->join(', ') }}.
            <a class="link" href="{{ route('tariffs.index') }}">Tarif anlegen</a>
        </div>
    @endif

    <div class="overflow-x-auto rounded-lg border border-stone-200 bg-white">
        <table class="table">
            <thead><tr><th>Parzelle</th><th>Pächter/in</th><th>Zähler</th><th class="text-right">Verbrauch</th><th class="text-right">Betrag</th></tr></thead>
            <tbody>
            @forelse ($rows as $row)
                @foreach ($row['lines'] as $i => $line)
                    <tr>
                        @if ($i === 0)
                            <td rowspan="{{ count($row['lines']) + 1 }}" class="align-top font-semibold">{{ $row['plot']->number }}</td>
                            <td rowspan="{{ count($row['lines']) + 1 }}" class="align-top">{{ $row['plot']->tenant_name }}</td>
                        @endif
                        <td>{{ $line->meter->type->label() }} {{ $line->meter->serial_number }}</td>
                        <td class="whitespace-nowrap text-right">{!! $line->consumptionMilli === null ? '<span class="text-amber-800">Ablesung fehlt</span>' : e(Milli::format($line->consumptionMilli).' '.$line->meter->type->unit()) !!}</td>
                        <td class="whitespace-nowrap text-right">{{ $line->costCents === null ? '–' : Milli::formatCents($line->costCents) }}</td>
                    </tr>
                @endforeach
                <tr class="bg-stone-50">
                    <td colspan="2" class="text-sm text-stone-500">{{ $row['complete'] ? 'Summe' : 'Summe (unvollständig)' }}</td>
                    <td class="whitespace-nowrap text-right font-semibold">{{ Milli::formatCents($row['total_cents']) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-stone-500">Keine Zähler in diesem Jahr.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-layout>
