<?php

namespace App\Http\Controllers;

use App\Models\CustomerReturn;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\SupplierExchangeClaim;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Global command-palette search across products, SKUs and document numbers.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $term = $request->string('q')->trim()->toString();

        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $user = $request->user();
        $results = collect();

        if (! $user->can('products.view')) {
            return response()->json([]);
        }

        Product::query()->where(fn ($query) => $query->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%"))
            ->limit(6)->get(['id', 'name', 'code'])
            ->each(fn (Product $product) => $results->push(['group' => 'Products', 'title' => $product->name, 'subtitle' => $product->code, 'url' => route('products.show', $product)]));

        ProductVariant::query()->with('product:id,name')->where(fn ($query) => $query->where('sku', 'like', "%{$term}%")->orWhere('barcode', $term))
            ->limit(8)->get()
            ->each(fn (ProductVariant $variant) => $results->push(['group' => 'SKUs', 'title' => $variant->sku, 'subtitle' => "{$variant->product->name} · {$variant->name}", 'url' => route('products.show', $variant->product_id)]));

        if ($user->can('movements.view')) {
            $this->documents($term, $results);
        }

        if ($user->can('claims.view')) {
            SupplierExchangeClaim::query()->with('supplier:id,name')->where('claim_no', 'like', "%{$term}%")
                ->latest('id')->limit(3)->get()
                ->each(fn (SupplierExchangeClaim $claim) => $results->push(['group' => 'Claims', 'title' => $claim->claim_no, 'subtitle' => $claim->supplier->name, 'url' => route('claims.show', $claim)]));
        }

        return response()->json($results->values());
    }

    /**
     * @param  Collection<int, array<string, string|null>>  $results
     */
    private function documents(string $term, Collection $results): void
    {
        StockMovement::query()->where('reference_no', 'like', "%{$term}%")->orWhere('external_reference', 'like', "%{$term}%")
            ->latest('id')->limit(5)->get(['id', 'reference_no', 'type', 'external_reference'])
            ->each(fn (StockMovement $movement) => $results->push(['group' => 'Movements', 'title' => $movement->reference_no, 'subtitle' => $movement->type->label().($movement->external_reference ? " · {$movement->external_reference}" : ''), 'url' => route('movements.show', $movement)]));

        CustomerReturn::query()->where('return_no', 'like', "%{$term}%")->orWhere('reference', 'like', "%{$term}%")
            ->latest('id')->limit(3)->get(['id', 'return_no', 'customer_name'])
            ->each(fn (CustomerReturn $return) => $results->push(['group' => 'Returns', 'title' => $return->return_no, 'subtitle' => $return->customer_name, 'url' => route('returns.show', $return)]));
    }
}
