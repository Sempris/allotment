@php use App\Support\Milli; @endphp
<x-layout :title="'Parzelle '.$plot->number">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Parzelle {{ $plot->number }}</h1>
            <p class="text-sm text-stone-500">{{ $plot->tenant_name }}@if ($plot->tenant_email) · {{ $plot->tenant_email }}@endif</p>
        </div>
        <a href="{{ route('meters.create', $plot) }}" class="btn">Zähler anlegen / tauschen</a>
    </div>

    @forelse ($meters as $meter)
        <section class="card mb-6">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-semibold">
                    {{ $meter->type->label() }} · {{ $meter->serial_number }}
                    @if ($meter->removed_on)<span class="ml-2 rounded bg-stone-200 px-2 py-0.5 text-xs font-normal">ausgebaut am {{ $meter->removed_on->format('d.m.Y') }}</span>@endif
                </h2>
                @unless ($meter->removed_on)
                    <a class="btn-secondary" href="{{ route('readings.create', $meter) }}">Ablesung erfassen</a>
                @endunless
            </div>
            <p class="mb-3 text-sm text-stone-500">Eingebaut am {{ $meter->installed_on->format('d.m.Y') }} mit Anfangsstand {{ Milli::format($meter->initial_value_milli) }} {{ $meter->type->unit() }}</p>

            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Datum</th><th>Stand</th><th>Quelle</th><th>Erfasst von</th><th>Hinweis</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($meter->readings as $reading)
                        <tr @class(['text-stone-400 line-through decoration-stone-300' => $reading->isSuperseded()])>
                            <td>{{ $reading->read_on->format('d.m.Y') }}</td>
                            <td class="whitespace-nowrap">{{ Milli::format($reading->value_milli) }} {{ $meter->type->unit() }}</td>
                            <td class="whitespace-nowrap">{{ $reading->source->label() }}</td>
                            <td class="whitespace-nowrap">{{ $reading->recorder?->name ?? '–' }}</td>
                            <td class="text-sm">
                                @if ($reading->isSuperseded())
                                    <span class="no-underline">korrigiert am {{ $reading->supersededBy->created_at->format('d.m.Y') }}</span>
                                @endif
                                @if ($reading->note)<div>{{ $reading->note }}</div>@endif
                                @if ($reading->flag_reason)
                                    <div @class(['text-amber-800' => $reading->needsReview()])>⚠ {{ $reading->flag_reason }}@if ($reading->flag_reviewed_at) (geprüft)@endif</div>
                                @endif
                            </td>
                            <td class="text-right">
                                @unless ($reading->isSuperseded())
                                    <a class="link" href="{{ route('readings.correct', $reading) }}">Korrigieren</a>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-stone-500">Noch keine Ablesungen.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <p class="text-sm text-stone-500">Für diese Parzelle ist noch kein Zähler angelegt.</p>
    @endforelse
</x-layout>
