<x-layout title="CSV-Import">
    <h1 class="mb-2 text-2xl font-semibold">Ablesungen per CSV importieren</h1>
    <p class="mb-6 max-w-2xl text-sm text-stone-600">
        Für den Ablesetag: Liste in Excel ausfüllen, als CSV (Semikolon) speichern und hochladen.
        Jede Zeile wird einzeln geprüft. Fehlerhafte Zeilen werden mit Grund angezeigt, alle anderen gespeichert.
    </p>

    @include('partials.errors')

    <form method="POST" action="{{ route('import.store') }}" enctype="multipart/form-data" class="card mb-8 max-w-2xl space-y-4">
        @csrf
        <pre class="rounded bg-stone-100 p-3 text-xs">parzelle;zaehlernummer;datum;stand
12;W-1012;15.10.2026;384,250
12;S-2012;15.10.2026;1520,7</pre>
        <input type="file" name="file" accept=".csv,text/csv,text/plain" required class="block text-sm">
        <button class="btn">Importieren</button>
    </form>

    @if ($results)
        @php($counts = collect($results)->countBy('status'))
        <h2 class="mb-3 text-lg font-semibold">Ergebnis</h2>
        <p class="mb-3 text-sm">{{ $counts['imported'] ?? 0 }} importiert · {{ $counts['flagged'] ?? 0 }} importiert, aber zur Prüfung markiert · {{ $counts['rejected'] ?? 0 }} abgelehnt</p>
        <div class="overflow-x-auto rounded-lg border border-stone-200 bg-white">
            <table class="table">
                <thead><tr><th>Zeile</th><th>Zähler</th><th>Ergebnis</th><th>Hinweis</th></tr></thead>
                <tbody>
                @foreach ($results as $row)
                    <tr>
                        <td>{{ $row['line'] }}</td>
                        <td>{{ $row['serial'] }}</td>
                        <td>
                            @switch($row['status'])
                                @case('imported') <span class="text-green-800">✓ importiert</span> @break
                                @case('flagged') <span class="text-amber-800">⚠ zur Prüfung</span> @break
                                @default <span class="text-red-800">✗ abgelehnt</span>
                            @endswitch
                        </td>
                        <td class="text-sm">{{ $row['message'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-layout>
