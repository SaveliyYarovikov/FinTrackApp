<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Imports\ImportCsvRequest;
use App\Services\CsvImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

class CsvImportController extends Controller
{
    public function create(): View
    {
        return view('imports.csv');
    }

    public function store(ImportCsvRequest $request, CsvImportService $service): View|RedirectResponse
    {
        /** @var UploadedFile|null $csvFile */
        $csvFile = $request->file('csv');

        if ($csvFile === null) {
            return back()->withErrors(['csv' => 'CSV file is required.']);
        }

        $result = $service->import(
            $request->user(),
            $csvFile,
            $request->boolean('skip_duplicates', true),
        );

        return view('imports.csv', [
            'result' => $result,
            'skipDuplicates' => $request->boolean('skip_duplicates', true),
        ]);
    }
}
