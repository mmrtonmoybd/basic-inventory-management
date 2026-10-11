<?php

namespace App\Http\Controllers;

use App\Services\Bangla;
use App\Enums\RecordStatus;
use App\Http\Requests\Products\StoreVariantsRequest;
use App\Http\Requests\Products\UpdateVariantRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ActivityLogger;
use App\Services\ProductCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductVariantController extends Controller
{
    public function store(StoreVariantsRequest $request, Product $product, ProductCatalogService $catalog): RedirectResponse
    {
        $created = $catalog->addVariants($product, $request->validated('variants'), $request->user());

        $this->toast(Bangla::number($created->count()).'টি নতুন ভ্যারিয়েন্ট যোগ হয়েছে।');

        return back();
    }

    public function update(UpdateVariantRequest $request, ProductVariant $variant, ActivityLogger $activity): RedirectResponse
    {
        $variant->update([...$request->validated(), 'sku' => strtoupper($request->validated('sku'))]);

        $activity->log('variant.updated', "ভ্যারিয়েন্ট {$variant->sku} এর তথ্য বদলানো হয়েছে", $variant, $variant->getChanges());
        $this->toast('ভ্যারিয়েন্ট সেভ হয়েছে।');

        return back();
    }

    public function archive(Request $request, ProductVariant $variant, ProductCatalogService $catalog): RedirectResponse
    {
        $catalog->setVariantStatus($variant, RecordStatus::Archived, $request->user());
        $this->toast("{$variant->sku} আর্কাইভ করা হয়েছে। এর স্টকের ইতিহাস রাখা আছে।");

        return back();
    }

    public function restore(Request $request, ProductVariant $variant, ProductCatalogService $catalog): RedirectResponse
    {
        $catalog->setVariantStatus($variant, RecordStatus::Active, $request->user());
        $this->toast("{$variant->sku} আবার চালু করা হয়েছে।");

        return back();
    }
}
