<?php

namespace App\Http\Controllers;

use App\Enums\MeterType;
use App\Http\Requests\TariffRequest;
use App\Models\Tariff;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TariffController extends Controller
{
    public function index(): View
    {
        return view('tariffs.index', [
            'tariffs' => Tariff::query()->orderByDesc('year')->orderBy('meter_type')->get(),
            'types' => MeterType::cases(),
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

        return back()->with('status', 'Tarif gespeichert.');
    }
}
