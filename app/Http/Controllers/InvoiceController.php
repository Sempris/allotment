<?php

namespace App\Http\Controllers;

use App\Exceptions\InvoiceNotAvailable;
use App\Models\Plot;
use App\Services\InvoiceDispatcher;
use App\Services\InvoiceRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    public function show(Request $request, int $year, Plot $plot, InvoiceRenderer $renderer): Response
    {
        // Tenants get their bill once the club has issued it, not as soon as all numbers happen to be there.
        if ($request->user()->cannot('view-club')) {
            abort_unless(
                $plot->invoiceDispatches()->where('year', $year)->exists(),
                403, "Die Abrechnung {$year} wurde noch nicht herausgegeben.",
            );
        }

        try {
            $pdf = $renderer->pdf($plot, $year);
        } catch (InvoiceNotAvailable $e) {
            abort(404, $e->getMessage());
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$renderer->filename($plot, $year).'"',
        ]);
    }

    public function send(Request $request, int $year, Plot $plot, InvoiceDispatcher $dispatcher): RedirectResponse
    {
        try {
            $dispatch = $dispatcher->sendByEmail($plot, $year, $request->user());
        } catch (InvoiceNotAvailable $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return back()->with('status', "Abrechnung {$year} für Parzelle {$plot->number} an {$dispatch->recipient_email} versendet.");
    }

    public function handOver(Request $request, int $year, Plot $plot, InvoiceDispatcher $dispatcher): RedirectResponse
    {
        try {
            $dispatcher->markHandedOver($plot, $year, $request->user());
        } catch (InvoiceNotAvailable $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return back()->with('status', "Abrechnung {$year} für Parzelle {$plot->number} als übergeben markiert.");
    }

    public function sendAll(Request $request, int $year, InvoiceDispatcher $dispatcher): RedirectResponse
    {
        $result = $dispatcher->sendAllPending($year, $request->user());

        $parts = [count($result['sent']).' versendet'];

        foreach ([
            'no_email' => 'ohne E-Mail-Adresse (bitte ausdrucken): Parzelle ',
            'not_ready' => 'noch nicht vollständig oder nicht geprüft: Parzelle ',
            'failed' => 'Versand fehlgeschlagen: Parzelle ',
        ] as $key => $label) {
            if ($result[$key] !== []) {
                $parts[] = $label.implode(', ', $result[$key]);
            }
        }

        return back()->with('status', 'Abrechnungen '.$year.': '.implode(' · ', $parts).'.');
    }
}
