<?php

namespace App\Http\Controllers;

use App\Enums\MovementType;
use App\Models\CustomerReturn;
use App\Models\DamageCase;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\StockMovementLine;
use App\Models\SupplierExchangeClaim;
use App\Models\SupplierExchangeReceipt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockMovementController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['type', 'from', 'to', 'search']);

        $movements = StockMovement::query()
            ->with(['creator:id,name', 'supplier:id,name', 'warehouse:id,name'])
            ->withCount('lines')
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('business_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('business_date', '<=', $to))
            ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->where(function (Builder $query) use ($term): void {
                $query->where('reference_no', 'like', "%{$term}%")
                    ->orWhere('external_reference', 'like', "%{$term}%")
                    ->orWhereHas('lines.variant', fn (Builder $variant) => $variant->where('sku', 'like', "%{$term}%"));
            }))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (StockMovement $movement): array => $this->summary($movement));

        return Inertia::render('movements/index', [
            'movements' => $movements,
            'filters' => $filters,
            'types' => collect(MovementType::cases())->map(fn (MovementType $type): array => ['value' => $type->value, 'label' => $type->label()]),
        ]);
    }

    public function show(StockMovement $movement): Response
    {
        $movement->load(['creator:id,name', 'supplier:id,name', 'warehouse:id,name', 'lines.variant.product:id,name', 'ledgerEntries', 'source']);

        return Inertia::render('movements/show', [
            'movement' => [
                ...$this->summary($movement),
                'notes' => $movement->notes,
                'source' => $this->sourceLink($movement),
                'lines' => $movement->lines->map(fn (StockMovementLine $line): array => [
                    'id' => $line->id,
                    'product' => $line->variant->product->name,
                    'product_id' => $line->variant->product_id,
                    'sku' => $line->variant->sku,
                    'variant' => $line->variant->name,
                    'from' => $line->from_condition?->label(),
                    'to' => $line->to_condition?->label(),
                    'quantity' => $line->quantity,
                    'note' => $line->note,
                ]),
                'ledger' => $movement->ledgerEntries->map(fn ($entry): array => [
                    'id' => $entry->id,
                    'line_id' => $entry->stock_movement_line_id,
                    'condition' => $entry->condition->label(),
                    'change' => $entry->quantity_change,
                    'balance_after' => $entry->balance_after,
                ]),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(StockMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'reference_no' => $movement->reference_no,
            'type' => $movement->type->value,
            'type_label' => $movement->type->label(),
            'business_date' => $movement->business_date->toDateString(),
            'external_reference' => $movement->external_reference,
            'total_quantity' => $movement->total_quantity,
            'lines_count' => $movement->lines_count ?? $movement->lines->count(),
            'supplier' => $movement->supplier?->name,
            'warehouse' => $movement->warehouse?->name,
            'created_by' => $movement->creator?->name,
            'created_at' => $movement->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{label: string, url: string}|null
     */
    private function sourceLink(StockMovement $movement): ?array
    {
        return match (true) {
            $movement->source instanceof CustomerReturn => ['label' => "রিটার্ন {$movement->source->return_no}", 'url' => route('returns.show', $movement->source)],
            $movement->source instanceof DamageCase => ['label' => "ক্ষতির কেস {$movement->source->case_no}", 'url' => route('damage.show', $movement->source)],
            $movement->source instanceof SupplierExchangeClaim => ['label' => "বদলির দাবি {$movement->source->claim_no}", 'url' => route('claims.show', $movement->source)],
            $movement->source instanceof SupplierExchangeReceipt => ['label' => "রসিদ {$movement->source->receipt_no}", 'url' => route('claims.show', $movement->source->supplier_exchange_claim_id)],
            $movement->source instanceof StockAdjustment => ['label' => "স্টক সমন্বয় {$movement->source->adjustment_no}", 'url' => route('adjustments.show', $movement->source)],
            default => null,
        };
    }
}
