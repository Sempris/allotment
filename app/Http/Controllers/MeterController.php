<?php

namespace App\Http\Controllers;

use App\Enums\MeterType;
use App\Exceptions\MeterExchangeRejected;
use App\Http\Requests\MeterRequest;
use App\Models\Meter;
use App\Models\Plot;
use App\Services\MeterExchange;
use App\Support\Milli;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class MeterController extends Controller
{
    public function create(Plot $plot): View
    {
        return view('meters.create', [
            'plot' => $plot,
            'types' => MeterType::cases(),
            'activeMeters' => $plot->meters()->active()->get(),
        ]);
    }

    public function store(MeterRequest $request, Plot $plot, MeterExchange $exchange): RedirectResponse
    {
        $data = $request->validated();
        $type = MeterType::from($data['type']);
        $installedOn = Carbon::createFromFormat('!Y-m-d', $data['installed_on']);
        $initialValueMilli = Milli::parse($data['initial_value']);

        if (empty($data['replaces_meter_id'])) {
            $meter = $plot->meters()->create([
                'type' => $type,
                'serial_number' => $data['serial_number'],
                'installed_on' => $installedOn,
                'initial_value_milli' => $initialValueMilli,
            ]);

            return redirect()->route('plots.show', $plot)->with('status', "Zähler {$meter->serial_number} angelegt.");
        }

        $old = Meter::query()->findOrFail($data['replaces_meter_id']);

        try {
            $meter = $exchange->exchange(
                $old, $installedOn, $request->finalValueMilli(), $type, $data['serial_number'], $initialValueMilli, $request->user(),
            );
        } catch (MeterExchangeRejected $e) {
            return back()->withErrors([$e->field => $e->reasons])->withInput();
        }

        return redirect()->route('plots.show', $plot)->with(
            'status', "Zähler {$old->serial_number} ausgebaut und durch {$meter->serial_number} ersetzt.",
        );
    }
}
