<x-layout title="Anmelden">
    <div class="mx-auto mt-16 max-w-sm rounded-lg border border-stone-200 bg-white p-6 shadow-sm">
        <h1 class="mb-1 text-xl font-semibold">🌱 Zählerstand</h1>
        <p class="mb-6 text-sm text-stone-500">Zählerablesung für den Kleingartenverein</p>

        @include('partials.errors')

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf
            <label class="block text-sm">E-Mail
                <input type="email" name="email" value="{{ old('email') }}" required autofocus class="input">
            </label>
            <label class="block text-sm">Passwort
                <input type="password" name="password" required class="input">
            </label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember"> Angemeldet bleiben</label>
            <button class="btn w-full">Anmelden</button>
        </form>
    </div>
</x-layout>
