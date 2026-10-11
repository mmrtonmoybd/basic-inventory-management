<?php

namespace App\Services\Reports;

use App\Enums\MovementType;
use App\Models\ActivityLog;
use App\Models\DamageCaseLine;
use App\Models\ProductVariant;
use App\Models\ReturnLine;
use App\Models\StockLedgerEntry;
use App\Models\SupplierExchangeLine;
use App\Models\VariantAttribute;
use App\Services\BusinessCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Builds every inventory report from the immutable stock ledger.
 *
 * Opening and closing balances are derived by summing ledger entries, so reports always agree with
 * the posting engine. Internal condition transfers (inspection, damage) net to zero on physical stock.
 */
class ReportService
{
    public function __construct(private BusinessCalendar $calendar) {}

    /**
     * @return list<array{key: string, title: string, description: string, filters: list<string>}>
     */
    public function catalog(): array
    {
        return [
            ['key' => 'daily-in-out', 'title' => 'দৈনিক স্টক ইন / আউট', 'description' => 'প্রতিদিনের শুরুর মজুদ, যা এসেছে, যা গেছে, সমন্বয় আর দিনশেষের মজুদ।', 'filters' => ['date', 'product', 'warehouse']],
            ['key' => 'product-stock', 'title' => 'পণ্য অনুযায়ী স্টক', 'description' => 'প্রতিটি পণ্যের শুরুর মজুদ, লেনদেন আর শেষের মজুদ, অবস্থা অনুযায়ী ভাগ করে।', 'filters' => ['date', 'product', 'warehouse']],
            ['key' => 'size-stock', 'title' => 'সাইজ অনুযায়ী স্টক', 'description' => 'সব পণ্যের স্টক সাইজ অনুযায়ী একসাথে।', 'filters' => ['date', 'product', 'warehouse']],
            ['key' => 'color-stock', 'title' => 'রং অনুযায়ী স্টক', 'description' => 'সব পণ্যের স্টক রং অনুযায়ী একসাথে।', 'filters' => ['date', 'product', 'warehouse']],
            ['key' => 'variant-stock', 'title' => 'প্রতিটি ভ্যারিয়েন্টের স্টক', 'description' => 'প্রতিটি SKU এর শুরুর মজুদ, লেনদেন আর শেষের মজুদ।', 'filters' => ['date', 'product', 'warehouse']],
            ['key' => 'returns', 'title' => 'রিটার্ন রিপোর্ট', 'description' => 'কাস্টমার রিটার্ন আর পরীক্ষার ফলাফল, ভ্যারিয়েন্ট অনুযায়ী।', 'filters' => ['date', 'product', 'warehouse']],
            ['key' => 'damage', 'title' => 'ক্ষতিগ্রস্ত মালের রিপোর্ট', 'description' => 'ক্ষতিগ্রস্ত মাল কোথায় পাওয়া গেছে আর কতটা দাবি করা হয়েছে।', 'filters' => ['date', 'product', 'supplier', 'warehouse']],
            ['key' => 'supplier-exchange', 'title' => 'সরবরাহকারী বদলি রিপোর্ট', 'description' => 'প্রতিটি দাবিতে কত পিস চাওয়া, অনুমোদিত, পাঠানো, পাওয়া আর বাকি।', 'filters' => ['date', 'product', 'supplier', 'warehouse']],
            ['key' => 'movement-history', 'title' => 'স্টক লেনদেনের ইতিহাস', 'description' => 'খতিয়ানের প্রতিটি এন্ট্রি, প্রতিবারের পরের ব্যালান্সসহ।', 'filters' => ['date', 'product', 'warehouse', 'type']],
            ['key' => 'user-activity', 'title' => 'ব্যবহারকারীর কাজের রিপোর্ট', 'description' => 'কে কখন কী করেছেন।', 'filters' => ['date', 'user']],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{key: string, title: string, description: string, columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>, totals: array<string, int>|null, filters: array<string, mixed>}
     */
    public function run(string $key, array $input): array
    {
        $definition = collect($this->catalog())->firstWhere('key', $key) ?? throw new InvalidArgumentException("অজানা রিপোর্ট {$key}।");
        $filters = $this->normalizeFilters($input);

        [$columns, $rows] = match ($key) {
            'daily-in-out' => $this->dailyInOut($filters),
            'product-stock' => $this->stockSummary($filters, 'product'),
            'size-stock' => $this->stockSummary($filters, 'attribute:size'),
            'color-stock' => $this->stockSummary($filters, 'attribute:color'),
            'variant-stock' => $this->stockSummary($filters, 'variant'),
            'returns' => $this->returns($filters),
            'damage' => $this->damage($filters),
            'supplier-exchange' => $this->supplierExchange($filters),
            'movement-history' => $this->movementHistory($filters),
            'user-activity' => $this->userActivity($filters),
        };

        $totals = collect($columns)->where('type', 'number')->where('total', '!==', false)->mapWithKeys(fn (array $column): array => [
            $column['key'] => collect($rows)->sum($column['key']),
        ])->all();

        return [
            ...$definition,
            'columns' => array_map(fn (array $column): array => array_intersect_key($column, array_flip(['key', 'label', 'type'])), $columns),
            'rows' => array_values($rows),
            'totals' => $totals === [] || in_array($key, ['daily-in-out', 'movement-history'], true) ? $this->specialTotals($key, $rows, $totals) : $totals,
            'filters' => [...$filters, 'from' => $filters['from']->toDateString(), 'to' => $filters['to']->toDateString()],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{from: CarbonImmutable, to: CarbonImmutable, product_id: int|null, supplier_id: int|null, warehouse_id: int|null, user_id: int|null, type: string|null}
     */
    public function normalizeFilters(array $input): array
    {
        $to = filled($input['to'] ?? null) ? $this->calendar->parse($input['to']) : $this->calendar->today();
        $from = filled($input['from'] ?? null) ? $this->calendar->parse($input['from']) : $to->startOfMonth();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return [
            'from' => $from,
            'to' => $to,
            'product_id' => filled($input['product_id'] ?? null) ? (int) $input['product_id'] : null,
            'supplier_id' => filled($input['supplier_id'] ?? null) ? (int) $input['supplier_id'] : null,
            'warehouse_id' => filled($input['warehouse_id'] ?? null) ? (int) $input['warehouse_id'] : null,
            'user_id' => filled($input['user_id'] ?? null) ? (int) $input['user_id'] : null,
            'type' => filled($input['type'] ?? null) ? (string) $input['type'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<StockLedgerEntry>
     */
    private function ledger(array $filters): Builder
    {
        return StockLedgerEntry::query()
            ->when($filters['warehouse_id'], fn (Builder $query, int $id) => $query->where('stock_ledger_entries.warehouse_id', $id))
            ->when($filters['product_id'], fn (Builder $query, int $id) => $query->whereIn(
                'stock_ledger_entries.product_variant_id',
                ProductVariant::query()->select('id')->where('product_id', $id),
            ));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function dailyInOut(array $filters): array
    {
        $physical = StockFlow::physicalConditions();

        $opening = (int) $this->ledger($filters)
            ->whereIn('condition', $physical)
            ->where('business_date', '<', $filters['from']->toDateString())
            ->sum('quantity_change');

        $daily = $this->ledger($filters)
            ->whereIn('condition', $physical)
            ->whereBetween('business_date', [$filters['from']->toDateString(), $filters['to']->toDateString()])
            ->selectRaw('business_date, movement_type, SUM(quantity_change) as total')
            ->groupBy('business_date', 'movement_type')
            ->get()
            ->groupBy(fn ($row): string => $row->business_date->toDateString());

        $rows = [];
        $running = $opening;

        for ($date = $filters['from']; $date->lessThanOrEqualTo($filters['to']); $date = $date->addDay()) {
            $byType = ($daily->get($date->toDateString()) ?? collect())->mapWithKeys(fn ($row): array => [$row->movement_type->value => (int) $row->total]);
            $row = ['date' => $date->toDateString(), 'opening' => $running];

            foreach (StockFlow::inboundColumns() as $column => $types) {
                $row[$column] = (int) collect($types)->sum(fn (string $type): int => $byType->get($type, 0));
            }

            foreach (StockFlow::outboundColumns() as $column => $types) {
                $row[$column] = abs((int) collect($types)->sum(fn (string $type): int => $byType->get($type, 0)));
            }

            $row['adjustments'] = (int) $byType->get(MovementType::Adjustment->value, 0);
            $row['closing'] = $running + (int) $byType->sum();
            $running = $row['closing'];
            $rows[] = $row;
        }

        return [[
            ['key' => 'date', 'label' => 'তারিখ', 'type' => 'date'],
            ['key' => 'opening', 'label' => 'শুরুর মজুদ', 'type' => 'number', 'total' => false],
            ['key' => 'opening_stock', 'label' => 'শুরুর এন্ট্রি', 'type' => 'number'],
            ['key' => 'stock_in', 'label' => 'স্টক ইন', 'type' => 'number'],
            ['key' => 'returns', 'label' => 'রিটার্ন', 'type' => 'number'],
            ['key' => 'replacements', 'label' => 'বদলি মাল', 'type' => 'number'],
            ['key' => 'stock_out', 'label' => 'স্টক আউট', 'type' => 'number'],
            ['key' => 'to_supplier', 'label' => 'সরবরাহকারীকে', 'type' => 'number'],
            ['key' => 'adjustments', 'label' => 'সমন্বয়', 'type' => 'number'],
            ['key' => 'closing', 'label' => 'শেষের মজুদ', 'type' => 'number', 'total' => false],
        ], $rows];
    }

    /**
     * Opening → movements → closing per group, all computed from ledger entries.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function stockSummary(array $filters, string $groupBy): array
    {
        $fromDate = $filters['from']->toDateString();
        $toDate = $filters['to']->toDateString();

        $openingRows = $this->ledger($filters)
            ->where('business_date', '<', $fromDate)
            ->selectRaw('product_variant_id, `condition`, SUM(quantity_change) as total')
            ->groupBy('product_variant_id', 'condition')
            ->get();

        $periodRows = $this->ledger($filters)
            ->whereBetween('business_date', [$fromDate, $toDate])
            ->selectRaw('product_variant_id, movement_type, `condition`, SUM(quantity_change) as total')
            ->groupBy('product_variant_id', 'movement_type', 'condition')
            ->get();

        $variantIds = $openingRows->pluck('product_variant_id')->merge($periodRows->pluck('product_variant_id'))->unique();
        $variants = ProductVariant::query()->with(['product:id,name,code', 'attributeValues.attribute:id,slug'])->whereIn('id', $variantIds)->get()->keyBy('id');

        $physical = StockFlow::physicalConditions();
        $perVariant = [];

        foreach ($variantIds as $variantId) {
            $row = array_fill_keys(['opening', 'opening_stock', 'stock_in', 'returns', 'replacements', 'stock_out', 'to_supplier', 'adjustments', 'closing', 'available', 'inspection', 'damaged', 'with_supplier'], 0);

            foreach ($openingRows->where('product_variant_id', $variantId) as $entry) {
                $row[$entry->condition->value] += (int) $entry->total;

                if (in_array($entry->condition->value, $physical, true)) {
                    $row['opening'] += (int) $entry->total;
                }
            }

            foreach ($periodRows->where('product_variant_id', $variantId) as $entry) {
                $total = (int) $entry->total;
                $row[$entry->condition->value] += $total;

                if (! in_array($entry->condition->value, $physical, true)) {
                    continue;
                }

                foreach (StockFlow::inboundColumns() as $column => $types) {
                    if (in_array($entry->movement_type->value, $types, true)) {
                        $row[$column] += $total;
                    }
                }

                foreach (StockFlow::outboundColumns() as $column => $types) {
                    if (in_array($entry->movement_type->value, $types, true)) {
                        $row[$column] -= $total;
                    }
                }

                if ($entry->movement_type === MovementType::Adjustment) {
                    $row['adjustments'] += $total;
                }
            }

            $row['closing'] = $row['available'] + $row['inspection'] + $row['damaged'];
            $perVariant[$variantId] = $row;
        }

        $attributeSlug = str_starts_with($groupBy, 'attribute:') ? substr($groupBy, 10) : null;
        $attributeName = $attributeSlug ? (VariantAttribute::query()->where('slug', $attributeSlug)->value('name') ?? ucfirst($attributeSlug)) : null;

        $grouped = collect($perVariant)->groupBy(function (array $row, int $variantId) use ($variants, $groupBy, $attributeSlug): ?string {
            $variant = $variants[$variantId];

            return match (true) {
                $groupBy === 'product' => (string) $variant->product_id,
                $groupBy === 'variant' => (string) $variant->id,
                default => $variant->attributeValues->first(fn ($value) => $value->attribute->slug === $attributeSlug)?->value ?? '__none__',
            };
        }, preserveKeys: true);

        $numberKeys = ['opening', 'opening_stock', 'stock_in', 'returns', 'replacements', 'stock_out', 'to_supplier', 'adjustments', 'closing', 'available', 'inspection', 'damaged', 'with_supplier'];

        $rows = $grouped
            ->reject(fn (Collection $group, string $key): bool => $key === '__none__')
            ->map(function (Collection $group, string $key) use ($variants, $groupBy, $numberKeys): array {
                $first = $variants[$group->keys()->first()];
                $labels = match (true) {
                    $groupBy === 'product' => ['product' => $first->product->name, 'code' => $first->product->code, 'variants' => $group->count()],
                    $groupBy === 'variant' => ['product' => $first->product->name, 'sku' => $first->sku, 'variant' => $first->name, 'threshold' => $first->low_stock_threshold],
                    default => ['value' => $key, 'variants' => $group->count()],
                };

                return [...$labels, ...collect($numberKeys)->mapWithKeys(fn (string $number): array => [$number => (int) $group->sum($number)])->all()];
            })
            ->sortBy(fn (array $row): string => ($row['product'] ?? $row['value'] ?? '').($row['sku'] ?? ''))
            ->values()
            ->all();

        $labelColumns = match (true) {
            $groupBy === 'product' => [['key' => 'product', 'label' => 'পণ্য', 'type' => 'text'], ['key' => 'code', 'label' => 'কোড', 'type' => 'text'], ['key' => 'variants', 'label' => 'ভ্যারিয়েন্ট', 'type' => 'number', 'total' => false]],
            $groupBy === 'variant' => [['key' => 'product', 'label' => 'পণ্য', 'type' => 'text'], ['key' => 'sku', 'label' => 'SKU', 'type' => 'text'], ['key' => 'variant', 'label' => 'ভ্যারিয়েন্ট', 'type' => 'text']],
            default => [['key' => 'value', 'label' => $attributeName, 'type' => 'text'], ['key' => 'variants', 'label' => 'ভ্যারিয়েন্ট', 'type' => 'number', 'total' => false]],
        };

        return [[
            ...$labelColumns,
            ['key' => 'opening', 'label' => 'শুরুর মজুদ', 'type' => 'number'],
            ['key' => 'opening_stock', 'label' => 'শুরুর এন্ট্রি', 'type' => 'number'],
            ['key' => 'stock_in', 'label' => 'ইন', 'type' => 'number'],
            ['key' => 'returns', 'label' => 'রিটার্ন', 'type' => 'number'],
            ['key' => 'replacements', 'label' => 'বদলি মাল', 'type' => 'number'],
            ['key' => 'stock_out', 'label' => 'আউট', 'type' => 'number'],
            ['key' => 'to_supplier', 'label' => 'সরবরাহকারীকে', 'type' => 'number'],
            ['key' => 'adjustments', 'label' => 'সমন্বয়', 'type' => 'number'],
            ['key' => 'closing', 'label' => 'শেষের মজুদ (গুদামে)', 'type' => 'number'],
            ['key' => 'available', 'label' => 'বিক্রয়যোগ্য', 'type' => 'number'],
            ['key' => 'inspection', 'label' => 'পরীক্ষাধীন', 'type' => 'number'],
            ['key' => 'damaged', 'label' => 'ক্ষতিগ্রস্ত', 'type' => 'number'],
            ['key' => 'with_supplier', 'label' => 'সরবরাহকারীর কাছে', 'type' => 'number'],
        ], $rows];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function returns(array $filters): array
    {
        $rows = ReturnLine::query()
            ->with(['customerReturn', 'variant.product:id,name'])
            ->whereHas('customerReturn', fn (Builder $query) => $query
                ->whereBetween('return_date', [$filters['from']->toDateString(), $filters['to']->toDateString()])
                ->when($filters['warehouse_id'], fn (Builder $query, int $id) => $query->where('warehouse_id', $id)))
            ->when($filters['product_id'], fn (Builder $query, int $id) => $query->whereHas('variant', fn (Builder $variant) => $variant->where('product_id', $id)))
            ->get()
            ->sortBy(fn (ReturnLine $line): string => $line->customerReturn->return_date->toDateString().$line->customerReturn->return_no)
            ->map(fn (ReturnLine $line): array => [
                'date' => $line->customerReturn->return_date->toDateString(),
                'return_no' => $line->customerReturn->return_no,
                'customer' => $line->customerReturn->customer_name,
                'product' => $line->variant->product->name,
                'sku' => $line->variant->sku,
                'variant' => $line->variant->name,
                'reason' => $line->reason ?? $line->customerReturn->reason,
                'quantity' => $line->quantity,
                'good' => $line->good_quantity,
                'damaged' => $line->damaged_quantity,
                'pending' => $line->pendingQuantity(),
            ])->values()->all();

        return [[
            ['key' => 'date', 'label' => 'তারিখ', 'type' => 'date'],
            ['key' => 'return_no', 'label' => 'রিটার্ন নং', 'type' => 'text'],
            ['key' => 'customer', 'label' => 'কাস্টমার', 'type' => 'text'],
            ['key' => 'product', 'label' => 'পণ্য', 'type' => 'text'],
            ['key' => 'sku', 'label' => 'SKU', 'type' => 'text'],
            ['key' => 'variant', 'label' => 'ভ্যারিয়েন্ট', 'type' => 'text'],
            ['key' => 'reason', 'label' => 'কারণ', 'type' => 'text'],
            ['key' => 'quantity', 'label' => 'ফেরত', 'type' => 'number'],
            ['key' => 'good', 'label' => 'ভালো', 'type' => 'number'],
            ['key' => 'damaged', 'label' => 'ক্ষতিগ্রস্ত', 'type' => 'number'],
            ['key' => 'pending', 'label' => 'পরীক্ষা বাকি', 'type' => 'number'],
        ], $rows];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function damage(array $filters): array
    {
        $rows = DamageCaseLine::query()
            ->with(['damageCase.supplier:id,name', 'variant.product:id,name'])
            ->whereHas('damageCase', fn (Builder $query) => $query
                ->whereBetween('damage_date', [$filters['from']->toDateString(), $filters['to']->toDateString()])
                ->when($filters['warehouse_id'], fn (Builder $query, int $id) => $query->where('warehouse_id', $id))
                ->when($filters['supplier_id'], fn (Builder $query, int $id) => $query->where('supplier_id', $id)))
            ->when($filters['product_id'], fn (Builder $query, int $id) => $query->whereHas('variant', fn (Builder $variant) => $variant->where('product_id', $id)))
            ->get()
            ->sortBy(fn (DamageCaseLine $line): string => $line->damageCase->damage_date->toDateString().$line->damageCase->case_no)
            ->map(fn (DamageCaseLine $line): array => [
                'date' => $line->damageCase->damage_date->toDateString(),
                'case_no' => $line->damageCase->case_no,
                'source' => $line->damageCase->source === 'customer_return' ? 'কাস্টমার রিটার্ন' : 'গুদাম',
                'product' => $line->variant->product->name,
                'sku' => $line->variant->sku,
                'variant' => $line->variant->name,
                'reason' => $line->damageCase->reason,
                'supplier' => $line->damageCase->supplier?->name,
                'status' => $line->damageCase->status->label(),
                'quantity' => $line->quantity,
                'claimed' => $line->claimed_quantity,
                'unclaimed' => $line->unclaimedQuantity(),
            ])->values()->all();

        return [[
            ['key' => 'date', 'label' => 'তারিখ', 'type' => 'date'],
            ['key' => 'case_no', 'label' => 'কেস নং', 'type' => 'text'],
            ['key' => 'source', 'label' => 'কোথায় পাওয়া', 'type' => 'text'],
            ['key' => 'product', 'label' => 'পণ্য', 'type' => 'text'],
            ['key' => 'sku', 'label' => 'SKU', 'type' => 'text'],
            ['key' => 'variant', 'label' => 'ভ্যারিয়েন্ট', 'type' => 'text'],
            ['key' => 'reason', 'label' => 'কারণ', 'type' => 'text'],
            ['key' => 'supplier', 'label' => 'সরবরাহকারী', 'type' => 'text'],
            ['key' => 'status', 'label' => 'অবস্থা', 'type' => 'text'],
            ['key' => 'quantity', 'label' => 'ক্ষতিগ্রস্ত', 'type' => 'number'],
            ['key' => 'claimed', 'label' => 'দাবি', 'type' => 'number'],
            ['key' => 'unclaimed', 'label' => 'দাবি হয়নি', 'type' => 'number'],
        ], $rows];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function supplierExchange(array $filters): array
    {
        $rows = SupplierExchangeLine::query()
            ->with(['claim.supplier:id,name', 'variant.product:id,name', 'replacementVariant:id,sku'])
            ->whereHas('claim', fn (Builder $query) => $query
                ->where('created_at', '>=', $filters['from']->startOfDay()->utc())
                ->where('created_at', '<', $filters['to']->addDay()->startOfDay()->utc())
                ->when($filters['warehouse_id'], fn (Builder $query, int $id) => $query->where('warehouse_id', $id))
                ->when($filters['supplier_id'], fn (Builder $query, int $id) => $query->where('supplier_id', $id)))
            ->when($filters['product_id'], fn (Builder $query, int $id) => $query->whereHas('variant', fn (Builder $variant) => $variant->where('product_id', $id)))
            ->orderBy('supplier_exchange_claim_id')
            ->get()
            ->map(fn (SupplierExchangeLine $line): array => [
                'claim_no' => $line->claim->claim_no,
                'supplier' => $line->claim->supplier->name,
                'status' => $line->claim->status->label(),
                'product' => $line->variant->product->name,
                'sku' => $line->variant->sku,
                'replacement' => $line->replacementVariant?->sku,
                'claimed' => $line->claimed_quantity,
                'approved' => $line->approved_quantity,
                'dispatched' => $line->dispatched_quantity,
                'received' => $line->received_quantity,
                'written_off' => $line->written_off_quantity,
                'outstanding' => $line->outstandingQuantity(),
            ])->all();

        return [[
            ['key' => 'claim_no', 'label' => 'দাবি নং', 'type' => 'text'],
            ['key' => 'supplier', 'label' => 'সরবরাহকারী', 'type' => 'text'],
            ['key' => 'status', 'label' => 'অবস্থা', 'type' => 'text'],
            ['key' => 'product', 'label' => 'পণ্য', 'type' => 'text'],
            ['key' => 'sku', 'label' => 'SKU', 'type' => 'text'],
            ['key' => 'replacement', 'label' => 'বদলি SKU', 'type' => 'text'],
            ['key' => 'claimed', 'label' => 'দাবি', 'type' => 'number'],
            ['key' => 'approved', 'label' => 'অনুমোদিত', 'type' => 'number'],
            ['key' => 'dispatched', 'label' => 'পাঠানো', 'type' => 'number'],
            ['key' => 'received', 'label' => 'পাওয়া', 'type' => 'number'],
            ['key' => 'written_off', 'label' => 'বাদ দেওয়া', 'type' => 'number'],
            ['key' => 'outstanding', 'label' => 'বাকি', 'type' => 'number'],
        ], $rows];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function movementHistory(array $filters): array
    {
        $rows = $this->ledger($filters)
            ->with(['movement:id,reference_no,created_by,external_reference', 'movement.creator:id,name', 'variant:id,product_id,sku,name', 'variant.product:id,name'])
            ->whereBetween('business_date', [$filters['from']->toDateString(), $filters['to']->toDateString()])
            ->when($filters['type'], fn (Builder $query, string $type) => $query->where('movement_type', $type))
            ->orderBy('id')
            ->limit(5000)
            ->get()
            ->map(fn (StockLedgerEntry $entry): array => [
                'date' => $entry->business_date->toDateString(),
                'reference' => $entry->movement->reference_no,
                'type' => $entry->movement_type->label(),
                'product' => $entry->variant->product->name,
                'sku' => $entry->variant->sku,
                'variant' => $entry->variant->name,
                'condition' => $entry->condition->label(),
                'change' => $entry->quantity_change,
                'balance_after' => $entry->balance_after,
                'user' => $entry->movement->creator?->name,
            ])->all();

        return [[
            ['key' => 'date', 'label' => 'তারিখ', 'type' => 'date'],
            ['key' => 'reference', 'label' => 'রেফারেন্স', 'type' => 'text'],
            ['key' => 'type', 'label' => 'ধরন', 'type' => 'text'],
            ['key' => 'product', 'label' => 'পণ্য', 'type' => 'text'],
            ['key' => 'sku', 'label' => 'SKU', 'type' => 'text'],
            ['key' => 'variant', 'label' => 'ভ্যারিয়েন্ট', 'type' => 'text'],
            ['key' => 'condition', 'label' => 'অবস্থা', 'type' => 'text'],
            ['key' => 'change', 'label' => 'পরিবর্তন', 'type' => 'number', 'total' => false],
            ['key' => 'balance_after', 'label' => 'পরের ব্যালান্স', 'type' => 'number', 'total' => false],
            ['key' => 'user', 'label' => 'ব্যবহারকারী', 'type' => 'text'],
        ], $rows];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function userActivity(array $filters): array
    {
        $timezone = $this->calendar->timezone();

        $rows = ActivityLog::query()
            ->with('user:id,name')
            ->where('created_at', '>=', $filters['from']->startOfDay()->utc())
            ->where('created_at', '<', $filters['to']->addDay()->startOfDay()->utc())
            ->when($filters['user_id'], fn (Builder $query, int $id) => $query->where('user_id', $id))
            ->orderByDesc('id')
            ->limit(5000)
            ->get()
            ->map(fn (ActivityLog $log): array => [
                'time' => $log->created_at?->setTimezone($timezone)->format('Y-m-d H:i'),
                'user' => $log->user?->name ?? 'সিস্টেম',
                'action' => $log->action,
                'description' => $log->description,
                'ip' => $log->ip_address,
            ])->all();

        return [[
            ['key' => 'time', 'label' => 'সময় (ঢাকা)', 'type' => 'text'],
            ['key' => 'user', 'label' => 'ব্যবহারকারী', 'type' => 'text'],
            ['key' => 'action', 'label' => 'কাজ', 'type' => 'text'],
            ['key' => 'description', 'label' => 'বিবরণ', 'type' => 'text'],
            ['key' => 'ip', 'label' => 'আইপি', 'type' => 'text'],
        ], $rows];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, int>  $totals
     * @return array<string, int>|null
     */
    private function specialTotals(string $key, array $rows, array $totals): ?array
    {
        if ($key === 'daily-in-out' && $rows !== []) {
            return [...$totals, 'opening' => $rows[0]['opening'], 'closing' => end($rows)['closing']];
        }

        if ($key === 'movement-history') {
            return ['change' => (int) collect($rows)->sum('change')];
        }

        return $totals === [] ? null : $totals;
    }
}
