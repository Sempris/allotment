@php use App\Support\Milli; @endphp
<x-layout title="Tarife">
    <h1 class="mb-6 text-2xl font-semibold">Tarife</h1>
    @include('partials.errors')

    <form method="POST" action="{{ route('tariffs.store') }}" class="card mb-8 grid max-w-3xl gap-4 sm:grid-cols-5 sm:items-end">
        @csrf
        @if ($editing)
            <p class="text-sm text-stone-600 sm:col-span-5">Tarif {{ $editing->meter_type->label() }} {{ $editing->year }} bearbeiten · <a class="link" href="{{ route('tariffs.index') }}">Abbrechen</a></p>
        @endif
        <label class="block text-sm">Jahr <input type="number" name="year" value="{{ old('year', $editing?->year ?? now()->year) }}" class="input"></label>
        <label class="block text-sm">Zählerart
            <select name="meter_type" class="input">
                @foreach ($types as $type)<option value="{{ $type->value }}" @selected(old('meter_type', $editing?->meter_type->value) === $type->value)>{{ $type->label() }}</option>@endforeach
            </select>
        </label>
        <label class="block text-sm">Preis je Einheit (€) <input name="price_per_unit" value="{{ old('price_per_unit', $editing ? Milli::formatCentsPlain($editing->price_per_unit_cents) : '') }}" placeholder="2,35" class="input"></label>
        <label class="block text-sm">Grundgebühr (€) <input name="base_fee" value="{{ old('base_fee', $editing ? Milli::formatCentsPlain($editing->base_fee_cents) : '0,00') }}" class="input"></label>
        <button class="btn">Speichern</button>
        <p class="text-xs text-stone-500 sm:col-span-5">Gibt es für Jahr und Zählerart schon einen Tarif, wird er überschrieben.</p>
    </form>

    <div class="max-w-3xl overflow-x-auto rounded-lg border border-stone-200 bg-white">
        <table class="table">
            <thead><tr><th>Jahr</th><th>Zählerart</th><th class="text-right">Preis je Einheit</th><th class="text-right">Grundgebühr je Zähler</th><th></th></tr></thead>
            <tbody>
            @forelse ($tariffs as $tariff)
                <tr>
                    <td>{{ $tariff->year }}</td>
                    <td>{{ $tariff->meter_type->label() }}</td>
                    <td class="text-right">{{ Milli::formatCents($tariff->price_per_unit_cents) }} / {{ $tariff->meter_type->unit() }}</td>
                    <td class="text-right">{{ Milli::formatCents($tariff->base_fee_cents) }}</td>
                    <td class="whitespace-nowrap text-right">
                        <a class="link mr-3" href="{{ route('tariffs.index', ['edit' => $tariff->id]) }}">Bearbeiten</a>
                        <details class="inline-block text-left">
                            <summary class="link cursor-pointer text-red-800">Löschen …</summary>
                            <form method="POST" action="{{ route('tariffs.destroy', $tariff) }}" class="mt-2">
                                @csrf @method('DELETE')
                                <button class="btn-danger">Tarif löschen</button>
                            </form>
                        </details>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-stone-500">Noch keine Tarife.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-layout>
