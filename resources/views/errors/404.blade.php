<x-layout title="Nicht gefunden">
    <div class="card mx-auto mt-10 max-w-lg">
        <h1 class="mb-2 text-xl font-semibold">Nicht gefunden</h1>
        <p class="text-sm text-stone-600">{{ $exception->getMessage() ?: 'Diese Seite gibt es nicht (mehr).' }}</p>
        <p class="mt-4 text-sm"><a class="link" href="{{ route('dashboard') }}">Zur Startseite</a></p>
    </div>
</x-layout>
