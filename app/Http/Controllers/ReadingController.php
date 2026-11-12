<?php

namespace App\Http\Controllers;

use App\Enums\ReadingSource;
use App\Exceptions\ReadingRejected;
use App\Http\Requests\ReadingRequest;
use App\Models\Meter;
use App\Models\Reading;
use App\Services\ReadingRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReadingController extends Controller
{
    public function __construct(private readonly ReadingRecorder $recorder) {}

    public function create(Meter $meter): View
    {
        abort_if($meter->removed_on !== null, 404);

        return view('readings.create', [
            'meter' => $meter->load('plot'),
            'last' => $meter->effectiveReadings()->get()->last(),
        ]);
    }

    public function store(ReadingRequest $request, Meter $meter): RedirectResponse
    {
        try {
            $reading = $this->recorder->record(
                $meter, $request->readOn(), $request->valueMilli(), ReadingSource::Manual, $request->user(), $request->validated('note'),
            );
        } catch (ReadingRejected $e) {
            return back()->withErrors(['value' => $e->reasons])->withInput();
        }

        return redirect()->route('plots.show', $meter->plot_id)->with(
            'status',
            $reading->flag_reason ? 'Ablesung gespeichert und zur Prüfung markiert: '.$reading->flag_reason : 'Ablesung gespeichert.',
        );
    }

    public function editCorrection(Reading $reading): View
    {
        abort_if($reading->isSuperseded(), 404);

        return view('readings.correct', ['reading' => $reading->load('meter.plot')]);
    }

    public function storeCorrection(ReadingRequest $request, Reading $reading): RedirectResponse
    {
        try {
            $this->recorder->correct($reading, $request->readOn(), $request->valueMilli(), $request->validated('note'), $request->user());
        } catch (ReadingRejected $e) {
            return back()->withErrors(['value' => $e->reasons])->withInput();
        }

        return redirect()->route('plots.show', $reading->meter->plot_id)->with('status', 'Korrektur gespeichert. Die ursprüngliche Ablesung bleibt im Verlauf sichtbar.');
    }

    public function review(Request $request, Reading $reading): RedirectResponse
    {
        abort_unless($reading->needsReview(), 404);

        $reading->forceFill(['flag_reviewed_at' => now(), 'flag_reviewed_by' => $request->user()->getKey()])->save();

        return back()->with('status', 'Ablesung als geprüft markiert.');
    }
}
