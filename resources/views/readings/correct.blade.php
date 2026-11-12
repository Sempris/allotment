<x-layout title="Ablesung korrigieren">
    <h1 class="mb-1 text-2xl font-semibold">Ablesung korrigieren</h1>
    <p class="mb-6 text-sm text-stone-500">Parzelle {{ $reading->meter->plot->number }} · {{ $reading->meter->type->label() }} {{ $reading->meter->serial_number }}
        · bisher: {{ $reading->read_on->format('d.m.Y') }} – {{ \App\Support\Milli::format($reading->value_milli) }} {{ $reading->meter->type->unit() }}</p>
    <p class="mb-6 max-w-lg text-sm">Die ursprüngliche Ablesung wird nicht gelöscht, sondern bleibt durchgestrichen im Verlauf. So ist später nachvollziehbar, was geändert wurde und warum.</p>
    @include('partials.errors')
    <form method="POST" action="{{ route('readings.correct.store', $reading) }}" class="card max-w-lg space-y-4">
        @csrf
        <label class="block text-sm">Ablesedatum <input type="date" name="read_on" value="{{ old('read_on', $reading->read_on->toDateString()) }}" max="{{ today()->toDateString() }}" required class="input"></label>
        <label class="block text-sm">Richtiger Zählerstand ({{ $reading->meter->type->unit() }}) <input name="value" value="{{ old('value', \App\Support\Milli::format($reading->value_milli)) }}" inputmode="decimal" required class="input"></label>
        <label class="block text-sm">Begründung <input name="note" value="{{ old('note') }}" placeholder="z. B. Zahlendreher auf dem Ablesezettel" required class="input"></label>
        <button class="btn">Korrektur speichern</button>
    </form>
</x-layout>
