<?php

namespace App\Services\Reports;

use App\Services\Bangla;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExporter
{
    public const PDF_ROW_LIMIT = 2000;

    /**
     * @param  array{key: string, title: string, columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>, totals: array<string, int>|null, filters: array<string, mixed>}  $report
     */
    public function export(array $report, string $format): Response
    {
        $filename = sprintf('%s_%s_%s', $report['key'], $report['filters']['from'], $report['filters']['to']);

        return match ($format) {
            'csv' => $this->csv($report, "{$filename}.csv"),
            'xlsx' => $this->xlsx($report, "{$filename}.xlsx"),
            'pdf' => $this->pdf($report, "{$filename}.pdf"),
            default => abort(404),
        };
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<list<string|int|null>>
     */
    private function matrix(array $report): array
    {
        $header = array_map(fn (array $column): string => $column['label'], $report['columns']);
        $rows = array_map(
            fn (array $row): array => array_map(fn (array $column) => $this->safeCell($row[$column['key']] ?? null), $report['columns']),
            $report['rows'],
        );

        if ($report['totals']) {
            $rows[] = array_map(
                fn (array $column, int $index) => $index === 0 ? 'মোট' : ($report['totals'][$column['key']] ?? null),
                $report['columns'],
                array_keys($report['columns']),
            );
        }

        return [$header, ...$rows];
    }

    /**
     * Neutralises spreadsheet formulas in user-entered text (CSV / formula injection).
     */
    private function safeCell(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function csv(array $report, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            foreach ($this->matrix($report) as $row) {
                fputcsv($handle, $row, escape: '');
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function xlsx(array $report, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($report): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $bold = (new Style)->withFontBold(true);

            foreach ($this->matrix($report) as $index => $row) {
                $writer->addRow(Row::fromValues($row, $index === 0 ? $bold : null));
            }

            $writer->close();
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function pdf(array $report, string $filename): Response
    {
        abort_if(count($report['rows']) > self::PDF_ROW_LIMIT, 422, 'এই রিপোর্টে '.Bangla::number(self::PDF_ROW_LIMIT).' লাইনের বেশি আছে। CSV বা এক্সেলে ডাউনলোড করুন, অথবা তারিখের সীমা ছোট করুন।');

        $defaults = (new ConfigVariables)->getDefaults();
        $fonts = (new FontVariables)->getDefaults();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => count($report['columns']) > 7 ? 'A4-L' : 'A4',
            'tempDir' => storage_path('framework/cache/mpdf'),
            'fontDir' => [...$defaults['fontDir'], resource_path('fonts')],
            'fontdata' => $fonts['fontdata'] + [
                'hindsiliguri' => [
                    'R' => 'HindSiliguri-Regular.ttf',
                    'B' => 'HindSiliguri-Bold.ttf',
                    'useOTL' => 0xFF,
                ],
            ],
            'default_font' => 'hindsiliguri',
            'autoScriptToLang' => true,
            'autoLangToFont' => false,
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 12,
            'margin_bottom' => 12,
        ]);

        $mpdf->SetTitle($report['title']);
        $mpdf->WriteHTML(view('reports.pdf', [
            'report' => $report,
            'generatedAt' => now(config('inventory.business_timezone')),
        ])->render());

        return response($mpdf->Output($filename, Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
