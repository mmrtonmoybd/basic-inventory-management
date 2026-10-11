<?php

namespace App\Http\Controllers;

use App\Enums\ReturnStatus;
use App\Http\Requests\Returns\InspectReturnRequest;
use App\Http\Requests\Returns\StoreReturnRequest;
use App\Models\CustomerReturn;
use App\Models\DamageCase;
use App\Models\ReturnInspection;
use App\Models\ReturnLine;
use App\Models\Warehouse;
use App\Services\BusinessCalendar;
use App\Services\ReturnService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerReturnController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['status', 'search']);

        $returns = CustomerReturn::query()
            ->with('creator:id,name')
            ->withSum('lines as total_quantity', 'quantity')
            ->withSum('lines as good_quantity', 'good_quantity')
            ->withSum('lines as damaged_quantity', 'damaged_quantity')
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->where(fn (Builder $query) => $query
                ->where('return_no', 'like', "%{$term}%")
                ->orWhere('reference', 'like', "%{$term}%")
                ->orWhere('customer_name', 'like', "%{$term}%")))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (CustomerReturn $return): array => [
                'id' => $return->id,
                'return_no' => $return->return_no,
                'return_date' => $return->return_date->toDateString(),
                'customer_name' => $return->customer_name,
                'reference' => $return->reference,
                'status' => $return->status->value,
                'status_label' => $return->status->label(),
                'total_quantity' => (int) $return->total_quantity,
                'good_quantity' => (int) $return->good_quantity,
                'damaged_quantity' => (int) $return->damaged_quantity,
                'created_by' => $return->creator?->name,
            ]);

        return Inertia::render('returns/index', [
            'returns' => $returns,
            'filters' => $filters,
            'statuses' => collect(ReturnStatus::cases())->map(fn (ReturnStatus $status): array => ['value' => $status->value, 'label' => $status->label()]),
        ]);
    }

    public function create(BusinessCalendar $calendar): Response
    {
        return Inertia::render('returns/create', [
            'warehouses' => Warehouse::query()->where('is_active', true)->orderByDesc('is_default')->get(['id', 'name', 'is_default']),
            'today' => $calendar->today()->toDateString(),
        ]);
    }

    public function store(StoreReturnRequest $request, ReturnService $service): RedirectResponse
    {
        [$return, $created] = $service->create($request->validated(), $request->user());

        $created
            ? $this->toast("রিটার্ন {$return->return_no} লেখা হয়েছে। মালগুলো পরীক্ষাধীন স্টকে গেছে।")
            : $this->toast("রিটার্ন {$return->return_no} আগেই লেখা হয়েছে।", 'info');

        return to_route('returns.show', $return);
    }

    public function show(CustomerReturn $return): Response
    {
        $return->load(['lines.variant.product:id,name', 'creator:id,name', 'warehouse:id,name', 'inspections.inspector:id,name', 'inspections.lines.returnLine.variant:id,sku,name', 'inspections.movement:id,reference_no', 'movements:id,reference_no,type,source_type,source_id,business_date']);

        return Inertia::render('returns/show', [
            'customerReturn' => [
                'id' => $return->id,
                'return_no' => $return->return_no,
                'return_date' => $return->return_date->toDateString(),
                'customer_name' => $return->customer_name,
                'reference' => $return->reference,
                'reason' => $return->reason,
                'notes' => $return->notes,
                'status' => $return->status->value,
                'status_label' => $return->status->label(),
                'warehouse' => $return->warehouse->name,
                'created_by' => $return->creator?->name,
                'created_at' => $return->created_at?->toIso8601String(),
                'lines' => $return->lines->map(fn (ReturnLine $line): array => [
                    'id' => $line->id,
                    'product' => $line->variant->product->name,
                    'sku' => $line->variant->sku,
                    'variant' => $line->variant->name,
                    'quantity' => $line->quantity,
                    'good_quantity' => $line->good_quantity,
                    'damaged_quantity' => $line->damaged_quantity,
                    'pending_quantity' => $line->pendingQuantity(),
                    'reason' => $line->reason,
                ]),
                'inspections' => $return->inspections->map(fn (ReturnInspection $inspection): array => [
                    'id' => $inspection->id,
                    'inspected_by' => $inspection->inspector?->name,
                    'created_at' => $inspection->created_at?->toIso8601String(),
                    'notes' => $inspection->notes,
                    'movement' => $inspection->movement?->only(['id', 'reference_no']),
                    'lines' => $inspection->lines->map(fn ($line): array => [
                        'sku' => $line->returnLine->variant->sku,
                        'variant' => $line->returnLine->variant->name,
                        'good_quantity' => $line->good_quantity,
                        'damaged_quantity' => $line->damaged_quantity,
                    ]),
                ]),
                'movements' => $return->movements->map(fn ($movement): array => [
                    'id' => $movement->id,
                    'reference_no' => $movement->reference_no,
                    'type' => $movement->type->label(),
                    'business_date' => $movement->business_date->toDateString(),
                ]),
                'damage_cases' => DamageCase::query()->where('customer_return_id', $return->id)->get(['id', 'case_no', 'status'])
                    ->map(fn ($case): array => ['id' => $case->id, 'case_no' => $case->case_no, 'status' => $case->status->label()]),
            ],
        ]);
    }

    public function inspect(InspectReturnRequest $request, CustomerReturn $return, ReturnService $service): RedirectResponse
    {
        $service->inspect($return, $request->validated(), $request->user());

        $this->toast('পরীক্ষার ফলাফল সেভ হয়েছে।');

        return to_route('returns.show', $return);
    }
}
