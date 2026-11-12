<?php

namespace App\Http\Controllers;

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

        return view('billing.index', [
            'year' => $year,
            'rows' => $this->billing->forYear($year),
            'missingTariffs' => $this->billing->missingTariffs($year),
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $year = $this->year($request);
        $rows = $this->billing->forYear($year);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\u{FEFF}"); // so Excel opens umlauts correctly
            fputcsv($out, ['Parzelle', 'Pächter/in', 'Zählerart', 'Zählernummer', 'Verbrauch', 'Einheit', 'Betrag (EUR)'], ';', '"', '');

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
