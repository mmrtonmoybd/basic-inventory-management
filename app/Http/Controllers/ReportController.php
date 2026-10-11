<?php

namespace App\Http\Controllers;

use App\Enums\MovementType;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ActivityLogger;
use App\Services\Reports\ReportExporter;
use App\Services\Reports\ReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function index(Request $request): Response
    {
        return Inertia::render('reports/index', ['reports' => $this->availableReports($request)]);
    }

    public function show(Request $request, string $report): Response
    {
        abort_unless(collect($this->availableReports($request))->contains('key', $report), 404);

        return Inertia::render('reports/show', [
            'report' => $this->reports->run($report, $this->validatedFilters($request)),
            'options' => [
                'products' => Product::query()->orderBy('name')->get(['id', 'name', 'code']),
                'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
                'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'name']),
                'users' => User::query()->orderBy('name')->get(['id', 'name']),
                'types' => collect(MovementType::cases())->map(fn (MovementType $type): array => ['value' => $type->value, 'label' => $type->label()]),
            ],
            'canExport' => $request->user()->can('reports.export'),
        ]);
    }

    public function export(Request $request, string $report, string $format, ReportExporter $exporter, ActivityLogger $activity): HttpResponse
    {
        abort_unless(collect($this->availableReports($request))->contains('key', $report), 404);
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);

        $data = $this->reports->run($report, $this->validatedFilters($request));
        $activity->log('report.exported', "{$data['title']} ".strtoupper($format).' হিসেবে ডাউনলোড করা হয়েছে', properties: ['from' => $data['filters']['from'], 'to' => $data['filters']['to']]);

        return $exporter->export($data, $format);
    }

    /**
     * The user-activity report exposes every user's actions and IP address, so it needs activity.view.
     *
     * @return list<array{key: string, title: string, description: string, filters: list<string>}>
     */
    private function availableReports(Request $request): array
    {
        return array_values(array_filter(
            $this->reports->catalog(),
            fn (array $report): bool => $report['key'] !== 'user-activity' || $request->user()->can('activity.view'),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'product_id' => ['nullable', 'integer'],
            'supplier_id' => ['nullable', 'integer'],
            'warehouse_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::enum(MovementType::class)],
        ]);
    }
}
