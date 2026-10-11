<?php

namespace App\Http\Controllers;

use App\Enums\RecordStatus;
use App\Http\Resources\VariantResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantAttribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON endpoints backing the searchable product / variant pickers used by every stock form.
 */
class LookupController extends Controller
{
    public function products(Request $request): JsonResponse
    {
        $products = Product::query()
            ->where('status', RecordStatus::Active)
            ->search($request->string('search')->trim()->toString())
            ->withCount(['variants' => fn (Builder $query) => $query->where('status', RecordStatus::Active)])
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'code']);

        return response()->json($products);
    }

    public function variants(Request $request, Product $product): JsonResponse
    {
        $warehouseId = $request->integer('warehouse_id') ?: null;
        $includeArchived = $request->boolean('include_archived');

        $product->load('attributes.values');

        $variants = $product->variants()
            ->with([
                'attributeValues',
                'balances' => fn ($query) => $query->when($warehouseId, fn ($query) => $query->where('warehouse_id', $warehouseId)),
            ])
            ->when(! $includeArchived, fn (Builder $query) => $query->where('status', RecordStatus::Active))
            ->orderBy('id')
            ->get();

        return response()->json([
            'product' => $product->only(['id', 'name', 'code']),
            'attributes' => $product->attributes->map(fn (VariantAttribute $attribute): array => [
                'id' => $attribute->id,
                'name' => $attribute->name,
                'is_color' => $attribute->is_color,
                'values' => $attribute->values->map->only(['id', 'value', 'swatch'])->values(),
            ]),
            'variants' => VariantResource::collection($variants)->resolve(),
        ]);
    }

    public function allVariants(Request $request): JsonResponse
    {
        $variants = ProductVariant::query()
            ->with('product:id,name')
            ->where('status', RecordStatus::Active)
            ->where(function (Builder $query) use ($request): void {
                $term = $request->string('search')->trim()->toString();
                $query->where('sku', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")
                    ->orWhereHas('product', fn (Builder $product) => $product->where('name', 'like', "%{$term}%"));
            })
            ->orderBy('sku')
            ->limit(20)
            ->get();

        return response()->json(VariantResource::collection($variants)->resolve());
    }
}
