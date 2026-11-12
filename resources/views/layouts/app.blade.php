<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Zählerstand' }} · Kleingartenverein</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-stone-50 text-stone-800 antialiased">
    @auth
        <header class="border-b border-stone-200 bg-white">
            <nav class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3 text-sm">
                <a href="{{ route('dashboard') }}" class="mr-4 font-semibold text-green-800">🌱 Zählerstand</a>
                @foreach ([
                    'dashboard' => 'Übersicht',
                    'plots.index' => 'Parzellen',
                    'import.create' => 'CSV-Import',
                    'billing.index' => 'Jahresabrechnung',
                    'tariffs.index' => 'Tarife',
                ] as $route => $label)
                    <a href="{{ route($route) }}" @class(['hover:text-green-800', 'font-semibold text-green-800' => request()->routeIs(Str::before($route, '.').'*')])>{{ $label }}</a>
                @endforeach
                <form method="POST" action="{{ route('logout') }}" class="ml-auto">
                    @csrf
                    <button class="text-stone-500 hover:text-stone-800">Abmelden ({{ auth()->user()->name }})</button>
                </form>
            </nav>
        </header>
    @endauth

    <main class="mx-auto max-w-6xl px-4 py-8">
        @if (session('status'))
            <div class="mb-6 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900" role="status">{{ session('status') }}</div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
