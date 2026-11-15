<?php

namespace App\Http\Controllers;

use App\Enums\MeterType;
use App\Http\Requests\TariffRequest;
use App\Models\Tariff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TariffController extends Controller
{
    public function index(Request $request): View
    {
        return view('tariffs.index', [
            'tariffs' => Tariff::query()->orderByDesc('year')->orderBy('meter_type')->get(),
            'types' => MeterType::cases(),
            // "Bearbeiten" pre-fills the form; saving overwrites the tariff of that year and type.
            'editing' => $request->filled('edit') ? Tariff::query()->find($request->integer('edit')) : null,
        ]);
    }

    public function store(TariffRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Tariff::updateOrCreate(
            ['year' => $data['year'], 'meter_type' => $data['meter_type']],
            [
                'price_per_unit_cents' => TariffRequest::toCents($data['price_per_unit']),
                'base_fee_cents' => TariffRequest::toCents($data['base_fee']),
            ],
        );

        return redirect()->route('tariffs.index')->with('status', 'Tarif gespeichert.');
    }

    public function destroy(Tariff $tariff): RedirectResponse
    {
        $tariff->delete();

        return redirect()->route('tariffs.index')->with(
            'status', "Tarif {$tariff->meter_type->label()} {$tariff->year} gelöscht. Die Abrechnung {$tariff->year} zeigt ihn jetzt als fehlend an.",
        );
    }
}
