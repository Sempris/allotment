@php use App\Support\Milli; @endphp
<x-layout :title="'Zähler '.$meter->serial_number">
    <h1 class="mb-1 text-2xl font-semibold">Zähler {{ $meter->serial_number }} bearbeiten</h1>
    <p class="mb-6 text-sm text-stone-500">
        <a class="link" href="{{ route('plots.show', $meter->plot) }}">Parzelle {{ $meter->plot->number }}</a> · {{ $meter->type->label() }}
        @if ($meter->removed_on) · ausgebaut am {{ $meter->removed_on->format('d.m.Y') }}@endif
    </p>
    @include('partials.errors')

    <div class="grid max-w-4xl gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('meters.update', $meter) }}" class="card space-y-4">
            @csrf @method('PUT')
            <h2 class="text-lg font-semibold">Stammdaten</h2>
            <label class="block text-sm">Zählernummer <input name="serial_number" value="{{ old('serial_number', $meter->serial_number) }}" required class="input"></label>
            <label class="block text-sm">Einbaudatum <input type="date" name="installed_on" value="{{ old('installed_on', $meter->installed_on->toDateString()) }}" max="{{ today()->toDateString() }}" required class="input"></label>
            <label class="block text-sm">Anfangsstand ({{ $meter->type->unit() }})
                <input name="initial_value" value="{{ old('initial_value', Milli::format($meter->initial_value_milli)) }}" inputmode="decimal" required class="input">
                <span class="mt-1 block text-xs text-stone-500">Einbaudatum und Anfangsstand dürfen den vorhandenen Ablesungen nicht widersprechen. Die Zählerart lässt sich nachträglich nicht ändern.</span>
            </label>
            <button class="btn">Speichern</button>
        </form>

        <div class="space-y-6">
            @if ($meter->removed_on)
                <form method="POST" action="{{ route('meters.reactivate', $meter) }}" class="card space-y-3">
                    @csrf
                    <h2 class="text-lg font-semibold">Ausbau zurücknehmen</h2>
                    <p class="text-sm text-stone-600">Der Zähler wird wieder aktiv. Die Ablesung vom Ausbautag bleibt im Verlauf.</p>
                    <button class="btn-secondary">Zähler wieder aktivieren</button>
                </form>
            @else
                <form method="POST" action="{{ route('meters.retire', $meter) }}" class="card space-y-4">
                    @csrf
                    <h2 class="text-lg font-semibold">Ausbauen ohne Ersatz</h2>
                    <p class="text-sm text-stone-600">
                        Für einen Zählertausch bitte <a class="link" href="{{ route('meters.create', $meter->plot) }}">einen neuen Zähler anlegen</a> und diesen als ersetzt auswählen.
                        @if ($last) Letzte Ablesung: {{ $last->read_on->format('d.m.Y') }} – {{ Milli::format($last->value_milli) }} {{ $meter->type->unit() }}.@endif
                    </p>
                    <label class="block text-sm">Ausbaudatum <input type="date" name="removed_on" value="{{ old('removed_on', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required class="input"></label>
                    <label class="block text-sm">Endstand ({{ $meter->type->unit() }})
                        <input name="final_value" value="{{ old('final_value') }}" inputmode="decimal" placeholder="z. B. 1234,567" class="input">
                        <span class="mt-1 block text-xs text-stone-500">Pflicht, außer der Zähler wurde am Ausbautag schon abgelesen.</span>
                    </label>
                    <button class="btn-secondary">Zähler ausbauen</button>
                </form>
            @endif

            <div class="card space-y-3">
                <h2 class="text-lg font-semibold">Löschen</h2>
                @if ($hasReadings)
                    <p class="text-sm text-stone-600">Dieser Zähler hat Ablesungen und kann nicht gelöscht werden, weil sonst die Grundlage früherer Abrechnungen verloren ginge. Wird er nicht mehr genutzt, bitte ausbauen.</p>
                @else
                    <p class="text-sm text-stone-600">Der Zähler hat noch keine Ablesungen und kann gelöscht werden, z. B. wenn er versehentlich angelegt wurde.</p>
                    <details>
                        <summary class="link cursor-pointer text-sm text-red-800">Zähler löschen …</summary>
                        <form method="POST" action="{{ route('meters.destroy', $meter) }}" class="mt-3">
                            @csrf @method('DELETE')
                            <button class="btn-danger">Zähler {{ $meter->serial_number }} endgültig löschen</button>
                        </form>
                    </details>
                @endif
            </div>
        </div>
    </div>
</x-layout>
