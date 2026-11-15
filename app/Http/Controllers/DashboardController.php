<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Meter;
use App\Models\Plot;
use App\Models\Reading;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->user()->role === Role::Tenant) {
            return $this->tenantStartPage($request->user());
        }

        Gate::authorize('view-club');

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

    /** Tenants have no overview; their start page is their own plot. */
    private function tenantStartPage(User $tenant): RedirectResponse
    {
        abort_if($tenant->plot_id === null, 403, 'Ihrem Zugang ist noch keine Parzelle zugeordnet. Bitte an den Vorstand wenden.');

        return redirect()->route('plots.show', $tenant->plot_id);
    }
}
