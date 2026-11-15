<x-layout title="Kein Zugriff">
    <div class="card mx-auto mt-10 max-w-lg">
        <h1 class="mb-2 text-xl font-semibold">Kein Zugriff</h1>
        <p class="text-sm text-stone-600">
            {{ $exception->getMessage() && $exception->getMessage() !== 'This action is unauthorized.' ? $exception->getMessage() : 'Für diese Seite fehlt Ihrem Zugang die Berechtigung.' }}
        </p>
        <p class="mt-4 text-sm"><a class="link" href="{{ route('dashboard') }}">Zur Startseite</a></p>
    </div>
</x-layout>
