<x-layout title="Parzelle anlegen">
    <h1 class="mb-6 text-2xl font-semibold">Parzelle anlegen</h1>
    @include('partials.errors')
    <form method="POST" action="{{ route('plots.store') }}" class="card max-w-lg space-y-4">
        @csrf
        <label class="block text-sm">Parzellennummer <input name="number" value="{{ old('number') }}" required class="input"></label>
        <label class="block text-sm">Pächter/in <input name="tenant_name" value="{{ old('tenant_name') }}" required class="input"></label>
        <label class="block text-sm">E-Mail (optional) <input type="email" name="tenant_email" value="{{ old('tenant_email') }}" class="input"></label>
        <button class="btn">Speichern</button>
    </form>
</x-layout>
