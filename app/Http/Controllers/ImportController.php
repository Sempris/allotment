<?php

namespace App\Http\Controllers;

use App\Services\ReadingCsvImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class ImportController extends Controller
{
    public function create(): View
    {
        return view('import.create', ['results' => session('import_results')]);
    }

    public function store(Request $request, ReadingCsvImporter $importer): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:1024']], [], ['file' => 'CSV-Datei']);

        try {
            $results = $importer->import((string) file_get_contents($request->file('file')->getRealPath()), $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()->route('import.create')->with('import_results', $results);
    }
}
