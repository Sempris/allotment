<?php

namespace App\Services;

use App\Exceptions\InvoiceNotAvailable;
use App\Models\Plot;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;

/**
 * Renders the yearly bill of one plot as a PDF.
 *
 * The PDF is always built from the current data, never stored.
 */
class InvoiceRenderer
{
    public function __construct(private readonly BillingService $billing) {}

    /**
     * Everything the bill shows. Also used by the tests to check the numbers
     * without parsing a PDF.
     *
     * @return array<string, mixed>
     *
     * @throws InvoiceNotAvailable
     */
    public function data(Plot $plot, int $year, ?Carbon $issuedOn = null): array
    {
        $bill = $this->billing->forPlot($plot, $year);

        if (! $bill['complete']) {
            throw new InvoiceNotAvailable($bill['lines'] === []
                ? "Parzelle {$plot->number} hat im Abrechnungsjahr {$year} keinen Zähler."
                : "Die Abrechnung {$year} für Parzelle {$plot->number} ist unvollständig: Es fehlt eine Ablesung oder ein Tarif.");
        }

        $issuedOn ??= today();

        return [
            'plot' => $plot,
            'year' => $year,
            'lines' => $bill['lines'],
            'totalCents' => $bill['total_cents'],
            'hasInterpolatedValues' => collect($bill['lines'])->contains(fn (BillingLine $l) => $l->isInterpolated()),
            'issuedOn' => $issuedOn,
            'dueOn' => $issuedOn->copy()->addDays(config('club.payment_days')),
            'reference' => "Abrechnung {$year} Parzelle {$plot->number}",
            'club' => config('club'),
        ];
    }

    /**
     * @throws InvoiceNotAvailable
     */
    public function pdf(Plot $plot, int $year): string
    {
        $options = new Options;
        $options->setDefaultFont('DejaVu Sans'); // has umlauts, € and ≈
        $options->setIsRemoteEnabled(false);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4');
        $dompdf->loadHtml(view('invoices.pdf', $this->data($plot, $year))->render(), 'UTF-8');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    public function filename(Plot $plot, int $year): string
    {
        return "jahresabrechnung-{$year}-parzelle-".preg_replace('/[^A-Za-z0-9]+/', '-', $plot->number).'.pdf';
    }
}
