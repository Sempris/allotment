<?php

namespace App\Http\Controllers;

use App\Enums\MeterType;
use App\Http\Requests\MeterRequest;
use App\Models\Meter;
use App\Models\Plot;
use App\Support\Milli;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

    public function store(MeterRequest $request, Plot $plot): RedirectResponse
    {
        $data = $request->validated();
        $installedOn = Carbon::createFromFormat('!Y-m-d', $data['installed_on']);

        $meter = DB::transaction(function () use ($data, $plot, $installedOn) {
            if (! empty($data['replaces_meter_id'])) {
                // Meter exchange: the old meter is taken out on the day the new one goes in.
                $old = Meter::query()->lockForUpdate()->findOrFail($data['replaces_meter_id']);
                $old->update(['removed_on' => $installedOn]);
            }

            return $plot->meters()->create([
                'type' => $data['type'],
                'serial_number' => $data['serial_number'],
                'installed_on' => $installedOn,
                'initial_value_milli' => Milli::parse($data['initial_value']),
            ]);
        });

        return redirect()->route('plots.show', $plot)->with('status', "Zähler {$meter->serial_number} angelegt.");
    }
}
