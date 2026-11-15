<?php

namespace App\Http\Controllers;

use App\Models\BillingPeriod;
use App\Models\InvoiceDispatch;
use App\Services\BillingLine;
use App\Services\BillingService;
use App\Support\Milli;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillingController extends Controller
{
    public function __construct(private readonly BillingService $billing) {}

    public function index(Request $request): View
    {
        $year = $this->year($request);
        $keyDates = BillingPeriod::keyDates();

        return view('billing.index', [
            'year' => $year,
            'rows' => $this->billing->forYear($year),
            'missingTariffs' => $this->billing->missingTariffs($year),
            // Latest dispatch per plot: who already got this year's bill, and for which amount.
            'dispatches' => InvoiceDispatch::query()->where('year', $year)->orderBy('id')->get()->keyBy('plot_id'),
            'keyDate' => $keyDates[$year] ?? null,
            // Suggest the same day as last year when a key date is set for the first time.
            'suggestedKeyDate' => ($keyDates[$year] ?? ($keyDates[$year - 1] ?? null)?->copy()->addYear())?->toDateString(),
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $year = $this->year($request);
        $rows = $this->billing->forYear($year);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\u{FEFF}"); // so Excel opens umlauts correctly
            // New columns are only ever appended, so existing spreadsheets keep working.
            fputcsv($out, [
                'Parzelle', 'Pächter/in', 'Zählerart', 'Zählernummer', 'Verbrauch', 'Einheit', 'Betrag (EUR)',
                'Anfangsstand', 'Anfangsdatum', 'Endstand', 'Enddatum', 'Hinweis',
            ], ';', '"', '');

            foreach ($rows as $row) {
                /** @var BillingLine $line */
                foreach ($row['lines'] as $line) {
                    fputcsv($out, [
                        $row['plot']->number,
                        $row['plot']->tenant_name,
                        $line->meter->type->label(),
                        $line->meter->serial_number,
                        $line->consumptionMilli === null ? 'Ablesung fehlt' : Milli::format($line->consumptionMilli),
                        $line->meter->type->unit(),
                        $line->costCents === null ? '' : number_format($line->costCents / 100, 2, ',', ''),
                        $line->period ? Milli::format($line->period->start->milli) : '',
                        $line->period?->start->date->format('d.m.Y') ?? '',
                        $line->period ? Milli::format($line->period->end->milli) : '',
                        $line->period?->end->date->format('d.m.Y') ?? '',
                        $line->isInterpolated() ? 'auf Stichtag umgerechnet' : '',
                    ], ';', '"', '');
                }
            }

            fclose($out);
        }, "jahresabrechnung-{$year}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function year(Request $request): int
    {
        $year = (int) $request->query('year', (string) now()->year);

        return max(2000, min(2100, $year));
    }
}
