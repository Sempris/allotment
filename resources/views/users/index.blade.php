<x-layout title="Benutzer">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Benutzer</h1>
        <a href="{{ route('users.create') }}" class="btn">Zugang anlegen</a>
    </div>

    <div class="overflow-x-auto rounded-lg border border-stone-200 bg-white">
        <table class="table">
            <thead><tr><th>Name</th><th>E-Mail</th><th>Rolle</th><th>Parzelle</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @foreach ($users as $user)
                <tr @class(['text-stone-400' => ! $user->active])>
                    <td class="font-semibold">{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->role->label() }}</td>
                    <td>{{ $user->plot?->number ?? '–' }}</td>
                    <td>{{ $user->active ? 'aktiv' : 'gesperrt' }}</td>
                    <td class="text-right"><a class="link" href="{{ route('users.edit', $user) }}">Bearbeiten</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <dl class="mt-6 max-w-2xl space-y-1 text-sm text-stone-600">
        @foreach (\App\Enums\Role::cases() as $role)
            <div><dt class="inline font-medium">{{ $role->label() }}:</dt> <dd class="inline">{{ $role->description() }}</dd></div>
        @endforeach
    </dl>
</x-layout>
