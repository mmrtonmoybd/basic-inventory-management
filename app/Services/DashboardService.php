<?php

namespace App\Services;

use App\Enums\AdjustmentStatus;
use App\Enums\ClaimStatus;
use App\Enums\MovementType;
use App\Enums\RecordStatus;
use App\Enums\StockCondition;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Models\StockBalance;
use App\Models\StockLedgerEntry;
use App\Models\StockMovement;
use App\Models\SupplierExchangeClaim;
use App\Models\VariantAttribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(private BusinessCalendar $calendar) {}

    /**
     * @return array<string, int>
     */
    public function metrics(): array
    {
        $today = $this->calendar->today()->toDateString();
        $monthStart = $this->calendar->today()->startOfMonth()->toDateString();

        $balances = StockBalance::query()
            ->selectRaw('COALESCE(SUM(available),0) as available, COALESCE(SUM(inspection),0) as inspection, COALESCE(SUM(damaged),0) as damaged, COALESCE(SUM(with_supplier),0) as with_supplier')
            ->first();

        $todayByType = StockMovement::query()
            ->where('business_date', $today)
            ->whereIn('type', [MovementType::StockIn, MovementType::Opening, MovementType::StockOut])
            ->selectRaw('type, SUM(total_quantity) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return [
            'total_products' => Product::query()->where('status', RecordStatus::Active)->count(),
            'total_variants' => ProductVariant::query()->where('status', RecordStatus::Active)->count(),
            'available' => (int) $balances->available,
            'inspection' => (int) $balances->inspection,
            'damaged' => (int) $balances->damaged,
            'with_supplier' => (int) $balances->with_supplier,
            'physical' => (int) $balances->available + (int) $balances->inspection + (int) $balances->damaged,
            'today_in' => (int) ($todayByType[MovementType::StockIn->value] ?? 0) + (int) ($todayByType[MovementType::Opening->value] ?? 0),
            'today_out' => (int) ($todayByType[MovementType::StockOut->value] ?? 0),
            'returns_month' => (int) StockMovement::query()->where('type', MovementType::ReturnReceipt)->where('business_date', '>=', $monthStart)->sum('total_quantity'),
            'returns_today' => (int) StockMovement::query()->where('type', MovementType::ReturnReceipt)->where('business_date', $today)->sum('total_quantity'),
            'pending_claims' => SupplierExchangeClaim::query()->whereIn('status', ClaimStatus::awaitingApproval())->count(),
            'pending_adjustments' => StockAdjustment::query()->where('status', AdjustmentStatus::Pending)->count(),
            'low_stock' => $this->lowStockQuery()->count(),
        ];
    }

    /**
     * @return Builder<ProductVariant>
     */
    public function lowStockQuery(): Builder
    {
        return ProductVariant::query()
            ->where('product_variants.status', RecordStatus::Active)
            ->where('low_stock_threshold', '>', 0)
            ->whereRaw('(SELECT COALESCE(SUM(available),0) FROM stock_balances WHERE stock_balances.product_variant_id = product_variants.id) <= low_stock_threshold');
    }

    /**
     * @return list<array{id: int, product_id: int, product: string, sku: string, variant: string, available: int, threshold: int}>
     */
    public function lowStockList(int $limit = 8): array
    {
        return $this->lowStockQuery()
            ->with('product:id,name')
            ->addSelect(['product_variants.*', 'available_total' => StockBalance::query()->selectRaw('COALESCE(SUM(available),0)')->whereColumn('product_variant_id', 'product_variants.id')])
            ->orderByRaw('available_total - low_stock_threshold')
            ->limit($limit)
            ->get()
            ->map(fn (ProductVariant $variant): array => [
                'id' => $variant->id,
                'product_id' => $variant->product_id,
                'product' => $variant->product->name,
                'sku' => $variant->sku,
                'variant' => $variant->name,
                'available' => (int) $variant->available_total,
                'threshold' => $variant->low_stock_threshold,
            ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function charts(): array
    {
        $to = $this->calendar->today();
        $from = $to->subDays(29);

        $daily = StockMovement::query()
            ->whereBetween('business_date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('type', [MovementType::StockIn, MovementType::Opening, MovementType::StockOut, MovementType::ReturnReceipt, MovementType::Damage])
            ->selectRaw('business_date, type, SUM(total_quantity) as total')
            ->groupBy('business_date', 'type')
            ->get()
            ->groupBy(fn ($row): string => $row->business_date->toDateString());

        $inspectionDamage = StockLedgerEntry::query()
            ->where('movement_type', MovementType::ReturnInspection)
            ->where('condition', StockCondition::Damaged)
            ->whereBetween('business_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('business_date, SUM(quantity_change) as total')
            ->groupBy('business_date')
            ->get()
            ->mapWithKeys(fn ($row): array => [$row->business_date->toDateString() => (int) $row->total]);

        $series = [];

        for ($date = $from; $date->lessThanOrEqualTo($to); $date = $date->addDay()) {
            $rows = ($daily->get($date->toDateString()) ?? collect())->mapWithKeys(fn ($row): array => [$row->type->value => (int) $row->total]);
            $series[] = [
                'date' => $date->toDateString(),
                'in' => $rows->get(MovementType::StockIn->value, 0) + $rows->get(MovementType::Opening->value, 0),
                'out' => $rows->get(MovementType::StockOut->value, 0),
                'returns' => $rows->get(MovementType::ReturnReceipt->value, 0),
                'damage' => $rows->get(MovementType::Damage->value, 0) + $inspectionDamage->get($date->toDateString(), 0),
            ];
        }

        return [
            'daily' => $series,
            'by_product' => $this->stockByProduct(),
            'by_attribute' => $this->stockByAttribute(),
            'claims_by_status' => SupplierExchangeClaim::query()
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->get()
                ->map(fn ($row): array => ['status' => $row->status->label(), 'key' => $row->status->value, 'total' => (int) $row->total])
                ->all(),
            'top_moving' => $this->topMoving($from->toDateString(), $to->toDateString()),
        ];
    }

    /**
     * @return list<array{product: string, available: int, inspection: int, damaged: int, with_supplier: int}>
     */
    private function stockByProduct(): array
    {
        return DB::table('stock_balances')
            ->join('product_variants', 'product_variants.id', '=', 'stock_balances.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->selectRaw('products.name as product, SUM(available) as available, SUM(inspection) as inspection, SUM(damaged) as damaged, SUM(with_supplier) as with_supplier')
            ->groupBy('products.id', 'products.name')
            ->orderByRaw('SUM(available) DESC')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'product' => $row->product,
                'available' => (int) $row->available,
                'inspection' => (int) $row->inspection,
                'damaged' => (int) $row->damaged,
                'with_supplier' => (int) $row->with_supplier,
            ])->all();
    }

    /**
     * Available stock per value of each attribute (Size, Color, …).
     *
     * @return list<array{attribute: string, is_color: bool, values: list<array{value: string, swatch: string|null, available: int}>}>
     */
    private function stockByAttribute(): array
    {
        return VariantAttribute::query()->orderBy('sort_order')->get()->map(function (VariantAttribute $attribute): array {
            $values = DB::table('product_variant_attribute_values as pvav')
                ->join('variant_attribute_values as vav', 'vav.id', '=', 'pvav.variant_attribute_value_id')
                ->join('stock_balances as sb', 'sb.product_variant_id', '=', 'pvav.product_variant_id')
                ->where('pvav.variant_attribute_id', $attribute->id)
                ->selectRaw('vav.value, vav.swatch, vav.sort_order, SUM(sb.available) as available')
                ->groupBy('vav.id', 'vav.value', 'vav.swatch', 'vav.sort_order')
                ->orderBy('vav.sort_order')
                ->get()
                ->map(fn ($row): array => ['value' => $row->value, 'swatch' => $row->swatch, 'available' => (int) $row->available])
                ->all();

            return ['attribute' => $attribute->name, 'is_color' => $attribute->is_color, 'values' => $values];
        })->filter(fn (array $group): bool => $group['values'] !== [])->values()->all();
    }

    /**
     * @return list<array{sku: string, label: string, product_id: int, quantity: int}>
     */
    private function topMoving(string $from, string $to): array
    {
        return StockLedgerEntry::query()
            ->join('product_variants', 'product_variants.id', '=', 'stock_ledger_entries.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('movement_type', MovementType::StockOut)
            ->whereBetween('business_date', [$from, $to])
            ->selectRaw('product_variants.sku, product_variants.name as variant, products.name as product, products.id as product_id, SUM(-quantity_change) as quantity')
            ->groupBy('product_variants.id', 'product_variants.sku', 'product_variants.name', 'products.name', 'products.id')
            ->orderByDesc('quantity')
            ->limit(8)
            ->get()
            ->map(fn ($row): array => [
                'sku' => $row->sku,
                'label' => "{$row->product} · {$row->variant}",
                'product_id' => (int) $row->product_id,
                'quantity' => (int) $row->quantity,
            ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentMovements(int $limit = 8): array
    {
        return StockMovement::query()
            ->with('creator:id,name')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (StockMovement $movement): array => [
                'id' => $movement->id,
                'reference_no' => $movement->reference_no,
                'type' => $movement->type->value,
                'type_label' => $movement->type->label(),
                'total_quantity' => $movement->total_quantity,
                'business_date' => $movement->business_date->toDateString(),
                'created_by' => $movement->creator?->name,
                'created_at' => $movement->created_at?->toIso8601String(),
            ])->all();
    }
}
