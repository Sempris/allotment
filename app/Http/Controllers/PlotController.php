<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlotRequest;
use App\Models\Plot;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PlotController extends Controller
{
    public function index(): View
    {
        $plots = Plot::query()
            ->with(['meters' => fn ($q) => $q->active()->with('effectiveReadings')])
            ->get()
            ->sortBy('number', SORT_NATURAL);

        return view('plots.index', ['plots' => $plots]);
    }

    public function create(): View
    {
        return view('plots.create');
    }

    public function store(PlotRequest $request): RedirectResponse
    {
        $plot = Plot::create($request->validated());

        return redirect()->route('plots.show', $plot)->with('status', "Parzelle {$plot->number} angelegt.");
    }

    public function show(Plot $plot): View
    {
        $plot->load(['meters.readings' => fn ($q) => $q->with(['recorder', 'supersededBy'])->orderByDesc('read_on')->orderByDesc('id')]);

        // Active meters first, then water before electricity.
        $meters = $plot->meters->sortBy(fn ($m) => [$m->removed_on !== null, $m->type->sortOrder(), $m->id])->values();

        return view('plots.show', [
            'plot' => $plot,
            'meters' => $meters,
            // Bills the club has issued for this plot, newest dispatch per year.
            'issuedBills' => $plot->invoiceDispatches()->orderByDesc('id')->get()->unique('year')->sortByDesc('year')->values(),
        ]);
    }

    public function edit(Plot $plot): View
    {
        return view('plots.edit', ['plot' => $plot, 'hasMeters' => $plot->meters()->exists()]);
    }

    public function update(PlotRequest $request, Plot $plot): RedirectResponse
    {
        $plot->update($request->validated());

        return redirect()->route('plots.show', $plot)->with('status', 'Parzelle gespeichert.');
    }

    public function destroy(Plot $plot): RedirectResponse
    {
        // Plots with meters keep their history; the foreign key would refuse the delete anyway.
        if ($plot->meters()->exists() || $plot->invoiceDispatches()->exists()) {
            return back()->withErrors(['plot' => 'Die Parzelle hat Zähler oder herausgegebene Abrechnungen und kann nicht gelöscht werden. Bei einem Pächterwechsel bitte nur den Namen ändern.']);
        }

        $plot->delete();

        return redirect()->route('plots.index')->with('status', "Parzelle {$plot->number} gelöscht.");
    }
}
