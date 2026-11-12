<x-layout title="Parzellen">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Parzellen</h1>
        <a href="{{ route('plots.create') }}" class="btn">Parzelle anlegen</a>
    </div>

    <div class="overflow-x-auto rounded-lg border border-stone-200 bg-white">
        <table class="table">
            <thead><tr><th>Nr.</th><th>Pächter/in</th><th>Aktive Zähler</th><th>Letzte Ablesung</th></tr></thead>
            <tbody>
            @forelse ($plots as $plot)
                <tr>
                    <td><a class="link font-semibold" href="{{ route('plots.show', $plot) }}">{{ $plot->number }}</a></td>
                    <td>{{ $plot->tenant_name }}</td>
                    <td>
                        @foreach ($plot->meters->sortBy(fn ($m) => $m->type->sortOrder()) as $meter)
                            <span class="mr-2 inline-block rounded bg-stone-100 px-2 py-0.5 text-xs">{{ $meter->type->label() }} {{ $meter->serial_number }}</span>
                        @endforeach
                    </td>
                    <td class="text-sm">
                        @foreach ($plot->meters->sortBy(fn ($m) => $m->type->sortOrder()) as $meter)
                            @php($last = $meter->effectiveReadings->last())
                            <div>{{ $meter->type->label() }}: {{ $last ? $last->read_on->format('d.m.Y').' · '.\App\Support\Milli::format($last->value_milli).' '.$meter->type->unit() : '–' }}</div>
                        @endforeach
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-stone-500">Noch keine Parzellen angelegt.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-layout>
