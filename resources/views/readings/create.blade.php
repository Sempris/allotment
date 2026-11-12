<x-layout title="Ablesung erfassen">
    <h1 class="mb-1 text-2xl font-semibold">Ablesung erfassen</h1>
    <p class="mb-6 text-sm text-stone-500">Parzelle {{ $meter->plot->number }} · {{ $meter->type->label() }} {{ $meter->serial_number }}
        · zuletzt: {{ $last ? $last->read_on->format('d.m.Y').' – '.\App\Support\Milli::format($last->value_milli).' '.$meter->type->unit() : 'noch keine Ablesung' }}</p>
    @include('partials.errors')
    <form method="POST" action="{{ route('readings.store', $meter) }}" class="card max-w-lg space-y-4">
        @csrf
        <label class="block text-sm">Ablesedatum <input type="date" name="read_on" value="{{ old('read_on', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required class="input"></label>
        <label class="block text-sm">Zählerstand ({{ $meter->type->unit() }}) <input name="value" value="{{ old('value') }}" inputmode="decimal" placeholder="z. B. 1234,567" required class="input"></label>
        <label class="block text-sm">Notiz (optional) <input name="note" value="{{ old('note') }}" class="input"></label>
        <button class="btn">Speichern</button>
    </form>
</x-layout>
