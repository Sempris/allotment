<?php

namespace App\Http\Controllers;

use App\Models\Meter;
use App\Models\Plot;
use App\Models\Reading;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $year = (int) now()->year;

        $metersWithoutReadingThisYear = Meter::query()
            ->active()
            ->with('plot')
            ->whereDoesntHave('readings', fn ($q) => $q->effective()->whereYear('read_on', $year))
            ->get()
            ->sortBy(fn (Meter $m) => $m->plot->number, SORT_NATURAL);

        return view('dashboard', [
            'year' => $year,
            'plotCount' => Plot::count(),
            'meterCount' => Meter::query()->active()->count(),
            'awaitingReview' => Reading::query()->awaitingReview()->with('meter.plot')->latest('read_on')->get(),
            'missing' => $metersWithoutReadingThisYear,
        ]);
    }
}
