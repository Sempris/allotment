<?php

namespace App\Services;

use App\Exceptions\InvoiceNotAvailable;
use App\Mail\InvoiceMail;
use App\Models\InvoiceDispatch;
use App\Models\Plot;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Issues yearly bills to tenants and keeps a log of it.
 *
 * A bill only goes out when it is complete and none of its readings is still
 * flagged for review: a possible typo should not reach the tenant as a bill.
 */
class InvoiceDispatcher
{
    public function __construct(
        private readonly BillingService $billing,
        private readonly InvoiceRenderer $renderer,
    ) {}

    /**
     * @throws InvoiceNotAvailable
     */
    public function sendByEmail(Plot $plot, int $year, ?User $by = null): InvoiceDispatch
    {
        $bill = $this->issuableBill($plot, $year);

        if (blank($plot->tenant_email)) {
            throw new InvoiceNotAvailable(
                "Für Parzelle {$plot->number} ist keine E-Mail-Adresse hinterlegt. Die Abrechnung kann ausgedruckt und als übergeben markiert werden.",
            );
        }

        try {
            Mail::to($plot->tenant_email)->send(new InvoiceMail(
                $plot, $year, $bill['total_cents'], $this->renderer->pdf($plot, $year), $this->renderer->filename($plot, $year),
            ));
        } catch (TransportExceptionInterface $e) {
            // Nothing is logged: the bill did not go out.
            throw new InvoiceNotAvailable("Der Versand an {$plot->tenant_email} (Parzelle {$plot->number}) ist fehlgeschlagen: {$e->getMessage()}");
        }

        return $this->log($plot, $year, $plot->tenant_email, $bill['total_cents'], $by);
    }

    /**
     * For tenants without e-mail address: the printed bill was handed over.
     *
     * @throws InvoiceNotAvailable
     */
    public function markHandedOver(Plot $plot, int $year, ?User $by = null): InvoiceDispatch
    {
        $bill = $this->issuableBill($plot, $year);

        return $this->log($plot, $year, null, $bill['total_cents'], $by);
    }

    /**
     * Send every bill of the year that is ready and has not been issued yet.
     *
     * @return array{sent: list<string>, no_email: list<string>, not_ready: list<string>, failed: list<string>} plot numbers
     */
    public function sendAllPending(int $year, ?User $by = null): array
    {
        $issued = InvoiceDispatch::query()->where('year', $year)->pluck('plot_id')->flip();
        $result = ['sent' => [], 'no_email' => [], 'not_ready' => [], 'failed' => []];

        foreach ($this->billing->forYear($year) as $bill) {
            $plot = $bill['plot'];

            if ($bill['lines'] === [] || $issued->has($plot->getKey())) {
                continue;
            }

            if (! $bill['complete'] || $bill['open_reviews'] > 0) {
                $result['not_ready'][] = $plot->number;
            } elseif (blank($plot->tenant_email)) {
                $result['no_email'][] = $plot->number;
            } else {
                try {
                    $this->sendByEmail($plot, $year, $by);
                    $result['sent'][] = $plot->number;
                } catch (InvoiceNotAvailable) {
                    $result['failed'][] = $plot->number;
                }
            }
        }

        return $result;
    }

    /**
     * @return array{plot: Plot, lines: list<BillingLine>, total_cents: int, complete: bool, open_reviews: int}
     *
     * @throws InvoiceNotAvailable
     */
    private function issuableBill(Plot $plot, int $year): array
    {
        $bill = $this->billing->forPlot($plot, $year);

        // Throws with the reason if the bill is incomplete.
        $this->renderer->data($plot, $year);

        if ($bill['open_reviews'] > 0) {
            throw new InvoiceNotAvailable(
                "Für Parzelle {$plot->number} ist noch eine Ablesung zur Prüfung markiert. Bitte zuerst bestätigen oder korrigieren.",
            );
        }

        return $bill;
    }

    private function log(Plot $plot, int $year, ?string $email, int $totalCents, ?User $by): InvoiceDispatch
    {
        return InvoiceDispatch::create([
            'plot_id' => $plot->getKey(),
            'year' => $year,
            'recipient_email' => $email,
            'total_cents' => $totalCents,
            'sent_by' => $by?->getKey(),
        ]);
    }
}
