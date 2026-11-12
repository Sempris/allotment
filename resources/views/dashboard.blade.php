<x-layout title="Übersicht">
    <h1 class="mb-6 text-2xl font-semibold">Übersicht {{ $year }}</h1>

    <div class="mb-8 grid gap-4 sm:grid-cols-3">
        <div class="card"><div class="text-sm text-stone-500">Parzellen</div><div class="text-2xl font-semibold">{{ $plotCount }}</div></div>
        <div class="card"><div class="text-sm text-stone-500">Aktive Zähler</div><div class="text-2xl font-semibold">{{ $meterCount }}</div></div>
        <div class="card"><div class="text-sm text-stone-500">Noch nicht abgelesen ({{ $year }})</div><div class="text-2xl font-semibold">{{ $missing->count() }}</div></div>
    </div>

    <section class="mb-10">
        <h2 class="mb-3 text-lg font-semibold">Zur Prüfung markiert</h2>
        @if ($awaitingReview->isEmpty())
            <p class="text-sm text-stone-500">Keine auffälligen Ablesungen. 👍</p>
        @else
            <div class="overflow-x-auto rounded-lg border border-stone-200 bg-white">
                <table class="table">
                    <thead><tr><th>Parzelle</th><th>Zähler</th><th>Datum</th><th>Stand</th><th>Hinweis</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($awaitingReview as $reading)
                        <tr>
                            <td><a class="link" href="{{ route('plots.show', $reading->meter->plot) }}">{{ $reading->meter->plot->number }}</a></td>
                            <td>{{ $reading->meter->type->label() }} {{ $reading->meter->serial_number }}</td>
                            <td>{{ $reading->read_on->format('d.m.Y') }}</td>
                            <td class="whitespace-nowrap">{{ \App\Support\Milli::format($reading->value_milli) }} {{ $reading->meter->type->unit() }}</td>
                            <td class="text-amber-800">{{ $reading->flag_reason }}</td>
                            <td class="whitespace-nowrap text-right">
                                <a class="link mr-3" href="{{ route('readings.correct', $reading) }}">Korrigieren</a>
                                <form method="POST" action="{{ route('readings.review', $reading) }}" class="inline">@csrf<button class="link">Ist korrekt</button></form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section>
        <h2 class="mb-3 text-lg font-semibold">Ablesung {{ $year }} fehlt noch</h2>
        @if ($missing->isEmpty())
            <p class="text-sm text-stone-500">Alle aktiven Zähler sind für {{ $year }} abgelesen.</p>
        @else
            <ul class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($missing as $meter)
                    <li class="card flex items-center justify-between text-sm">
                        <span>Parzelle {{ $meter->plot->number }} · {{ $meter->type->label() }} {{ $meter->serial_number }}</span>
                        <a class="link" href="{{ route('readings.create', $meter) }}">Erfassen</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layout>
