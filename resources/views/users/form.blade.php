@php($isNew = ! $user->exists)
<x-layout :title="$isNew ? 'Zugang anlegen' : 'Zugang bearbeiten'">
    <h1 class="mb-6 text-2xl font-semibold">{{ $isNew ? 'Zugang anlegen' : 'Zugang von '.$user->name.' bearbeiten' }}</h1>
    @include('partials.errors')

    <div class="grid max-w-4xl gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ $isNew ? route('users.store') : route('users.update', $user) }}" class="card space-y-4">
            @csrf
            @unless ($isNew) @method('PUT') @endunless
            <label class="block text-sm">Name <input name="name" value="{{ old('name', $user->name) }}" required class="input"></label>
            <label class="block text-sm">E-Mail (Anmeldename) <input type="email" name="email" value="{{ old('email', $user->email) }}" required class="input"></label>
            <label class="block text-sm">Rolle
                <select name="role" class="input">
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}" @selected(old('role', $user->role?->value) === $role->value)>{{ $role->label() }} – {{ $role->description() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm">Parzelle (nur für Pächter/innen)
                <select name="plot_id" class="input">
                    <option value="">–</option>
                    @foreach ($plots as $plot)
                        <option value="{{ $plot->id }}" @selected((string) old('plot_id', $user->plot_id) === (string) $plot->id)>{{ $plot->number }} · {{ $plot->tenant_name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm">{{ $isNew ? 'Startpasswort' : 'Neues Passwort' }}
                <input type="password" name="password" autocomplete="new-password" @required($isNew) class="input">
                <span class="mt-1 block text-xs text-stone-500">Mindestens 10 Zeichen.@unless ($isNew) Leer lassen, um das Passwort nicht zu ändern.@endunless Der Benutzer kann es nach der Anmeldung selbst ändern.</span>
            </label>
            <input type="hidden" name="active" value="0">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="active" value="1" @checked(old('active', $user->active))> Zugang aktiv (gesperrte Zugänge können sich nicht anmelden)</label>
            <div class="flex items-center gap-4">
                <button class="btn">Speichern</button>
                <a class="link text-sm" href="{{ route('users.index') }}">Abbrechen</a>
            </div>
        </form>

        @unless ($isNew)
            <div class="card space-y-3 self-start">
                <h2 class="text-lg font-semibold">Löschen</h2>
                @if ($hasHistory)
                    <p class="text-sm text-stone-600">{{ $user->name }} hat Ablesungen erfasst oder geprüft und steht im Verlauf. Der Zugang kann deshalb nicht gelöscht, aber gesperrt werden.</p>
                @else
                    <p class="text-sm text-stone-600">Der Zugang hat keine Spuren im Verlauf und kann gelöscht werden.</p>
                    <details>
                        <summary class="link cursor-pointer text-sm text-red-800">Zugang löschen …</summary>
                        <form method="POST" action="{{ route('users.destroy', $user) }}" class="mt-3">
                            @csrf @method('DELETE')
                            <button class="btn-danger">Zugang von {{ $user->name }} endgültig löschen</button>
                        </form>
                    </details>
                @endif
            </div>
        @endunless
    </div>
</x-layout>
