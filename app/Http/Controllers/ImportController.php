<?php

namespace App\Http\Controllers;

use App\Services\Bangla;
use App\Models\Warehouse;
use App\Services\CsvImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('import/index', [
            'warehouses' => Warehouse::query()->where('is_active', true)->orderByDesc('is_default')->get(['id', 'name', 'is_default']),
            'header' => CsvImportService::HEADER,
        ]);
    }

    public function store(Request $request, CsvImportService $importer): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'commit' => ['boolean'],
        ]);

        $result = $importer->import($request->file('file')->getRealPath(), (int) $data['warehouse_id'], $request->user(), $request->boolean('commit'));

        Inertia::flash('importResult', $result);

        if ($result['committed']) {
            $this->toast(Bangla::number($result['summary']['new_variants']).'টি ভ্যারিয়েন্ট আর '.Bangla::number($result['summary']['opening_units']).' পিস শুরুর মজুদ আমদানি হয়েছে।');
        }

        return back();
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, CsvImportService::HEADER, escape: '');
            fputcsv($handle, ['POLO2', 'গ্রীষ্মের পোলো', 'টপস', 'সাইজ:L|রং:লাল', '', '10', '50'], escape: '');
            fputcsv($handle, ['POLO2', 'গ্রীষ্মের পোলো', 'টপস', 'সাইজ:XL|রং:কালো', 'POLO2-XL-BLK', '10', '30'], escape: '');
            fputcsv($handle, ['MUG01', 'লোগোসহ মগ', 'এক্সেসরিজ', '', '', '5', '24'], escape: '');
            fclose($handle);
        }, 'stockpilot-import-template.csv', ['Content-Type' => 'text/csv']);
    }
}
