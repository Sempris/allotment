<x-layout :title="'Parzelle '.$plot->number.' bearbeiten'">
    <h1 class="mb-6 text-2xl font-semibold">Parzelle {{ $plot->number }} bearbeiten</h1>
    @include('partials.errors')

    <div class="grid max-w-4xl gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('plots.update', $plot) }}" class="card space-y-4">
            @csrf @method('PUT')
            <label class="block text-sm">Parzellennummer <input name="number" value="{{ old('number', $plot->number) }}" required class="input"></label>
            <label class="block text-sm">Pächter/in
                <input name="tenant_name" value="{{ old('tenant_name', $plot->tenant_name) }}" required class="input">
                <span class="mt-1 block text-xs text-stone-500">Bei einem Pächterwechsel hier den neuen Namen eintragen. Zähler und Ablesungen bleiben bei der Parzelle.</span>
            </label>
            <label class="block text-sm">E-Mail (optional) <input type="email" name="tenant_email" value="{{ old('tenant_email', $plot->tenant_email) }}" class="input"></label>
            <div class="flex items-center gap-4">
                <button class="btn">Speichern</button>
                <a class="link text-sm" href="{{ route('plots.show', $plot) }}">Abbrechen</a>
            </div>
        </form>

        <div class="card space-y-3 self-start">
            <h2 class="text-lg font-semibold">Löschen</h2>
            @if ($hasMeters)
                <p class="text-sm text-stone-600">Diese Parzelle hat Zähler und kann nicht gelöscht werden, weil sonst Ablesungen und Abrechnungen ihre Zuordnung verlören.</p>
            @else
                <p class="text-sm text-stone-600">Die Parzelle hat keine Zähler und kann gelöscht werden, z. B. wenn sie versehentlich angelegt wurde.</p>
                <details>
                    <summary class="link cursor-pointer text-sm text-red-800">Parzelle löschen …</summary>
                    <form method="POST" action="{{ route('plots.destroy', $plot) }}" class="mt-3">
                        @csrf @method('DELETE')
                        <button class="btn-danger">Parzelle {{ $plot->number }} endgültig löschen</button>
                    </form>
                </details>
            @endif
        </div>
    </div>
</x-layout>
