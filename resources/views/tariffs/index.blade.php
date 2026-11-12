@php use App\Support\Milli; @endphp
<x-layout title="Tarife">
    <h1 class="mb-6 text-2xl font-semibold">Tarife</h1>
    @include('partials.errors')

    <form method="POST" action="{{ route('tariffs.store') }}" class="card mb-8 grid max-w-3xl gap-4 sm:grid-cols-5 sm:items-end">
        @csrf
        <label class="block text-sm">Jahr <input type="number" name="year" value="{{ old('year', now()->year) }}" class="input"></label>
        <label class="block text-sm">Zählerart
            <select name="meter_type" class="input">
                @foreach ($types as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach
            </select>
        </label>
        <label class="block text-sm">Preis je Einheit (€) <input name="price_per_unit" value="{{ old('price_per_unit') }}" placeholder="2,35" class="input"></label>
        <label class="block text-sm">Grundgebühr (€) <input name="base_fee" value="{{ old('base_fee', '0,00') }}" class="input"></label>
        <button class="btn">Speichern</button>
    </form>

    <div class="max-w-3xl overflow-x-auto rounded-lg border border-stone-200 bg-white">
        <table class="table">
            <thead><tr><th>Jahr</th><th>Zählerart</th><th class="text-right">Preis je Einheit</th><th class="text-right">Grundgebühr je Zähler</th></tr></thead>
            <tbody>
            @forelse ($tariffs as $tariff)
                <tr>
                    <td>{{ $tariff->year }}</td>
                    <td>{{ $tariff->meter_type->label() }}</td>
                    <td class="text-right">{{ Milli::formatCents($tariff->price_per_unit_cents) }} / {{ $tariff->meter_type->unit() }}</td>
                    <td class="text-right">{{ Milli::formatCents($tariff->base_fee_cents) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-stone-500">Noch keine Tarife.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-layout>
