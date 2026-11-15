<?php

namespace App\Http\Controllers;

use App\Exceptions\InvoiceNotAvailable;
use App\Models\Plot;
use App\Services\InvoiceRenderer;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    public function show(int $year, Plot $plot, InvoiceRenderer $renderer): Response
    {
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
}
