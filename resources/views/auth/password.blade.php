<x-layout title="Passwort ändern">
    <h1 class="mb-6 text-2xl font-semibold">Passwort ändern</h1>
    @include('partials.errors')
    <form method="POST" action="{{ route('password.update') }}" class="card max-w-lg space-y-4">
        @csrf @method('PUT')
        <label class="block text-sm">Bisheriges Passwort <input type="password" name="current_password" autocomplete="current-password" required class="input"></label>
        <label class="block text-sm">Neues Passwort
            <input type="password" name="password" autocomplete="new-password" required class="input">
            <span class="mt-1 block text-xs text-stone-500">Mindestens 10 Zeichen.</span>
        </label>
        <label class="block text-sm">Neues Passwort wiederholen <input type="password" name="password_confirmation" autocomplete="new-password" required class="input"></label>
        <button class="btn">Passwort ändern</button>
    </form>
</x-layout>
