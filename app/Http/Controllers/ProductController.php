<?php

namespace App\Http\Controllers;

use App\Services\Bangla;
use App\Enums\RecordStatus;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Http\Resources\VariantResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockLedgerEntry;
use App\Models\VariantAttribute;
use App\Services\ActivityLogger;
use App\Services\ProductCatalogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'category', 'status']);

        $products = Product::query()
            ->with('category:id,name')
            ->withCount(['variants', 'variants as active_variants_count' => fn (Builder $query) => $query->where('status', RecordStatus::Active)])
            ->addSelect([
                'available_total' => DB::table('stock_balances')
                    ->join('product_variants', 'product_variants.id', '=', 'stock_balances.product_variant_id')
                    ->whereColumn('product_variants.product_id', 'products.id')
                    ->selectRaw('COALESCE(SUM(stock_balances.available), 0)'),
                'physical_total' => DB::table('stock_balances')
                    ->join('product_variants', 'product_variants.id', '=', 'stock_balances.product_variant_id')
                    ->whereColumn('product_variants.product_id', 'products.id')
                    ->selectRaw('COALESCE(SUM(stock_balances.available + stock_balances.inspection + stock_balances.damaged), 0)'),
                'low_stock_count' => DB::table('product_variants')
                    ->leftJoin('stock_balances', 'stock_balances.product_variant_id', '=', 'product_variants.id')
                    ->whereColumn('product_variants.product_id', 'products.id')
                    ->where('product_variants.status', RecordStatus::Active->value)
                    ->where('product_variants.low_stock_threshold', '>', 0)
                    ->whereRaw('COALESCE(stock_balances.available, 0) <= product_variants.low_stock_threshold')
                    ->selectRaw('COUNT(*)'),
            ])
            ->search($filters['search'] ?? null)
            ->when($filters['category'] ?? null, fn (Builder $query, $category) => $query->where('category_id', $category))
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('products/index', [
            'products' => $products,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('products/create', [
            'attributes' => $this->attributeOptions(),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreProductRequest $request, ProductCatalogService $catalog): RedirectResponse
    {
        $product = $catalog->create($request->validated(), $request->user());

        $this->toast("{$product->name} তৈরি হয়েছে, ভ্যারিয়েন্ট ".Bangla::number($product->variants()->count()).'টি।');

        return to_route('products.show', $product);
    }

    public function show(Product $product): Response
    {
        $product->load(['category:id,name', 'attributes.values', 'creator:id,name']);

        $variants = $product->variants()
            ->with(['attributeValues', 'balances'])
            ->orderBy('status')
            ->orderBy('id')
            ->get();

        $recent = StockLedgerEntry::query()
            ->with(['movement:id,reference_no,type,created_by,business_date', 'movement.creator:id,name', 'variant:id,sku,name'])
            ->whereIn('product_variant_id', $variants->pluck('id'))
            ->latest('id')
            ->limit(25)
            ->get()
            ->map(fn (StockLedgerEntry $entry): array => [
                'id' => $entry->id,
                'movement_id' => $entry->stock_movement_id,
                'reference_no' => $entry->movement->reference_no,
                'type' => $entry->movement_type->label(),
                'sku' => $entry->variant->sku,
                'variant' => $entry->variant->name,
                'condition' => $entry->condition->label(),
                'change' => $entry->quantity_change,
                'balance_after' => $entry->balance_after,
                'date' => $entry->business_date->toDateString(),
                'user' => $entry->movement->creator?->name,
            ]);

        return Inertia::render('products/show', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'unit' => $product->unit,
                'description' => $product->description,
                'status' => $product->status->value,
                'category' => $product->category?->only(['id', 'name']),
                'category_id' => $product->category_id,
                'created_by' => $product->creator?->name,
                'created_at' => $product->created_at?->toIso8601String(),
            ],
            'attributes' => $product->attributes->map(fn (VariantAttribute $attribute): array => [
                'id' => $attribute->id,
                'name' => $attribute->name,
                'is_color' => $attribute->is_color,
                'values' => $attribute->values->map->only(['id', 'value', 'swatch'])->values(),
            ]),
            'variants' => VariantResource::collection($variants)->resolve(),
            'ledger' => $recent,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product, ActivityLogger $activity): RedirectResponse
    {
        $product->update([...$request->validated(), 'updated_by' => $request->user()->id]);

        $activity->log('product.updated', "পণ্য {$product->name} এর তথ্য বদলানো হয়েছে", $product, $product->getChanges());
        $this->toast('পণ্যের তথ্য সেভ হয়েছে।');

        return back();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function attributeOptions()
    {
        return VariantAttribute::query()->with('values')->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (VariantAttribute $attribute): array => [
                'id' => $attribute->id,
                'name' => $attribute->name,
                'is_color' => $attribute->is_color,
                'values' => $attribute->values->map->only(['id', 'value', 'code', 'swatch'])->values(),
            ]);
    }
}
