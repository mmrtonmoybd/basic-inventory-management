<?php

namespace App\Http\Controllers;

use App\Enums\AdjustmentStatus;
use App\Http\Requests\Stock\StoreAdjustmentRequest;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentLine;
use App\Models\Warehouse;
use App\Services\AdjustmentService;
use App\Services\BusinessCalendar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StockAdjustmentController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['status']);

        $adjustments = StockAdjustment::query()
            ->with(['requester:id,name', 'decider:id,name'])
            ->withCount('lines')
            ->withSum('lines as net_difference', 'difference')
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (StockAdjustment $adjustment): array => [
                'id' => $adjustment->id,
                'adjustment_no' => $adjustment->adjustment_no,
                'count_date' => $adjustment->count_date->toDateString(),
                'reason' => $adjustment->reason,
                'status' => $adjustment->status->value,
                'lines_count' => $adjustment->lines_count,
                'net_difference' => (int) $adjustment->net_difference,
                'requested_by' => $adjustment->requester?->name,
                'decided_by' => $adjustment->decider?->name,
            ]);

        return Inertia::render('adjustments/index', ['adjustments' => $adjustments, 'filters' => $filters]);
    }

    public function create(BusinessCalendar $calendar): Response
    {
        return Inertia::render('adjustments/create', [
            'warehouses' => Warehouse::query()->where('is_active', true)->orderByDesc('is_default')->get(['id', 'name', 'is_default']),
            'today' => $calendar->today()->toDateString(),
        ]);
    }

    public function store(StoreAdjustmentRequest $request, AdjustmentService $service): RedirectResponse
    {
        [$adjustment, $created] = $service->request($request->validated(), $request->user());

        $created
            ? $this->toast("স্টক সমন্বয় {$adjustment->adjustment_no} অনুমোদনের জন্য পাঠানো হয়েছে।")
            : $this->toast("স্টক সমন্বয় {$adjustment->adjustment_no} আগেই পাঠানো হয়েছে।", 'info');

        return to_route('adjustments.show', $adjustment);
    }

    public function show(Request $request, StockAdjustment $adjustment): Response
    {
        $adjustment->load(['lines.variant.product:id,name', 'requester:id,name', 'decider:id,name', 'approvalRecords.user:id,name', 'warehouse:id,name']);

        return Inertia::render('adjustments/show', [
            'adjustment' => [
                'id' => $adjustment->id,
                'adjustment_no' => $adjustment->adjustment_no,
                'count_date' => $adjustment->count_date->toDateString(),
                'reason' => $adjustment->reason,
                'notes' => $adjustment->notes,
                'status' => $adjustment->status->value,
                'decision_note' => $adjustment->decision_note,
                'stock_movement_id' => $adjustment->stock_movement_id,
                'warehouse' => $adjustment->warehouse->name,
                'requested_by' => $adjustment->requester?->name,
                'decided_by' => $adjustment->decider?->name,
                'decided_at' => $adjustment->decided_at?->toIso8601String(),
                'created_at' => $adjustment->created_at?->toIso8601String(),
                'lines' => $adjustment->lines->map(fn (StockAdjustmentLine $line): array => [
                    'id' => $line->id,
                    'product' => $line->variant->product->name,
                    'sku' => $line->variant->sku,
                    'variant' => $line->variant->name,
                    'condition' => $line->condition->label(),
                    'system_quantity' => $line->system_quantity,
                    'counted_quantity' => $line->counted_quantity,
                    'difference' => $line->difference,
                ]),
                'history' => $adjustment->approvalRecords->map(fn ($record): array => [
                    'id' => $record->id,
                    'action' => $record->action,
                    'comment' => $record->comment,
                    'user' => $record->user?->name,
                    'created_at' => $record->created_at?->toIso8601String(),
                ]),
            ],
            'canDecide' => $request->user()->can('decide', $adjustment),
            'isOwnRequest' => $adjustment->requested_by === $request->user()->id && $adjustment->status === AdjustmentStatus::Pending,
        ]);
    }

    public function approve(Request $request, StockAdjustment $adjustment, AdjustmentService $service): RedirectResponse
    {
        Gate::authorize('decide', $adjustment);
        $service->approve($adjustment, $request->user(), $request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null);
        $this->toast("স্টক সমন্বয় {$adjustment->adjustment_no} অনুমোদিত হয়ে খতিয়ানে যোগ হয়েছে।");

        return back();
    }

    public function reject(Request $request, StockAdjustment $adjustment, AdjustmentService $service): RedirectResponse
    {
        Gate::authorize('decide', $adjustment);
        $service->reject($adjustment, $request->user(), $request->validate(['note' => ['required', 'string', 'max:2000']])['note']);
        $this->toast("স্টক সমন্বয় {$adjustment->adjustment_no} বাতিল করা হয়েছে।", 'info');

        return back();
    }
}
