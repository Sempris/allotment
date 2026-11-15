<?php

namespace App\Http\Controllers;

use App\Http\Requests\BillingPeriodRequest;
use App\Models\BillingPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Chooses per billing year whether meter values are interpolated to a key date.
 */
class BillingPeriodController extends Controller
{
    public function store(BillingPeriodRequest $request): RedirectResponse
    {
        $year = $request->integer('year');

        BillingPeriod::updateOrCreate(['year' => $year], ['key_date' => $request->keyDate()]);

        return redirect()->route('billing.index', ['year' => $year])->with(
            'status', "Die Abrechnung {$year} wird auf den Stichtag {$request->keyDate()->format('d.m.Y')} umgerechnet.",
        );
    }

    public function destroy(Request $request): RedirectResponse
    {
        $year = $request->integer('year');

        BillingPeriod::query()->where('year', $year)->delete();

        return redirect()->route('billing.index', ['year' => $year])->with(
            'status', "Stichtag entfernt. Die Abrechnung {$year} endet wieder mit der letzten Ablesung im Jahr.",
        );
    }
}
