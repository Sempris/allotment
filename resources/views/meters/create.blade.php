<x-layout :title="'Zähler für Parzelle '.$plot->number">
    <h1 class="mb-6 text-2xl font-semibold">Zähler für Parzelle {{ $plot->number }}</h1>
    @include('partials.errors')
    <form method="POST" action="{{ route('meters.store', $plot) }}" class="card max-w-lg space-y-4">
        @csrf
        <label class="block text-sm">Zählerart
            <select name="type" class="input">
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ $type->label() }} ({{ $type->unit() }})</option>
                @endforeach
            </select>
        </label>
        <label class="block text-sm">Zählernummer <input name="serial_number" value="{{ old('serial_number') }}" required class="input"></label>
        <label class="block text-sm">Einbaudatum <input type="date" name="installed_on" value="{{ old('installed_on', today()->toDateString()) }}" required class="input"></label>
        <label class="block text-sm">Anfangsstand <input name="initial_value" value="{{ old('initial_value', '0') }}" inputmode="decimal" required class="input"></label>
        @if ($activeMeters->isNotEmpty())
            <label class="block text-sm">Ersetzt einen Zähler? (Zählertausch)
                <select name="replaces_meter_id" class="input">
                    <option value="">Nein, zusätzlicher Zähler</option>
                    @foreach ($activeMeters as $meter)
                        <option value="{{ $meter->id }}" @selected((string) old('replaces_meter_id') === (string) $meter->id)>{{ $meter->type->label() }} {{ $meter->serial_number }}</option>
                    @endforeach
                </select>
                <span class="mt-1 block text-xs text-stone-500">Bitte vorher den Endstand des alten Zählers als Ablesung erfassen.</span>
            </label>
        @endif
        <button class="btn">Speichern</button>
    </form>
</x-layout>
