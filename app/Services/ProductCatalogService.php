<?php

namespace App\Services;

use App\Enums\RecordStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantAttributeValue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates products and their explicitly selected variant combinations.
 *
 * Only the combinations the user ticks in the matrix are created — never a full cartesian product.
 */
class ProductCatalogService
{
    public function __construct(private ActivityLogger $activity) {}

    /**
     * @param  array{name: string, code: string, category_id?: int|null, unit?: string|null, description?: string|null, attribute_ids?: list<int>, variants?: list<array{value_ids?: list<int>, sku?: string|null, barcode?: string|null, low_stock_threshold?: int|null}>}  $data
     */
    public function create(array $data, User $user): Product
    {
        return DB::transaction(function () use ($data, $user): Product {
            $product = Product::query()->create([
                'name' => $data['name'],
                'code' => Str::upper($data['code']),
                'category_id' => $data['category_id'] ?? null,
                'unit' => $data['unit'] ?? 'pcs',
                'description' => $data['description'] ?? null,
                'status' => RecordStatus::Active,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $attributeIds = array_values(array_unique($data['attribute_ids'] ?? []));

            foreach ($attributeIds as $position => $attributeId) {
                $product->attributes()->attach($attributeId, ['position' => $position]);
            }

            $variants = $attributeIds === []
                ? [['value_ids' => [], 'sku' => $data['variants'][0]['sku'] ?? null, 'low_stock_threshold' => $data['variants'][0]['low_stock_threshold'] ?? 0]]
                : ($data['variants'] ?? []);

            if ($variants === []) {
                throw ValidationException::withMessages(['variants' => 'অন্তত একটি সাইজ/রঙের কম্বিনেশন বেছে নিন।']);
            }

            $this->createVariants($product, $variants);

            $this->activity->log('product.created', "নতুন পণ্য {$product->name} তৈরি হয়েছে, ভ্যারিয়েন্ট ".Bangla::number(count($variants)).'টি', $product, user: $user);

            return $product;
        });
    }

    /**
     * @param  list<array{value_ids?: list<int>, sku?: string|null, barcode?: string|null, low_stock_threshold?: int|null}>  $variants
     * @return Collection<int, ProductVariant>
     */
    public function addVariants(Product $product, array $variants, User $user): Collection
    {
        return DB::transaction(function () use ($product, $variants, $user): Collection {
            Product::query()->whereKey($product->id)->lockForUpdate()->first();

            if ($product->attributes()->count() === 0) {
                throw ValidationException::withMessages(['variants' => 'এই পণ্যের কোনো সাইজ/রঙ নেই, তাই এতে একটিই সাধারণ ভ্যারিয়েন্ট থাকে।']);
            }

            $created = $this->createVariants($product, $variants);

            $this->activity->log('product.variants_added', "{$product->name} পণ্যে ".Bangla::number($created->count()).'টি নতুন ভ্যারিয়েন্ট যোগ হয়েছে', $product, ['skus' => $created->pluck('sku')->all()], $user);

            return $created;
        });
    }

    public function setVariantStatus(ProductVariant $variant, RecordStatus $status, User $user): void
    {
        $variant->update([
            'status' => $status,
            'archived_at' => $status === RecordStatus::Archived ? now() : null,
        ]);

        $this->activity->log(
            $status === RecordStatus::Archived ? 'variant.archived' : 'variant.restored',
            "ভ্যারিয়েন্ট {$variant->sku} ".($status === RecordStatus::Archived ? 'আর্কাইভ করা হয়েছে' : 'আবার চালু করা হয়েছে'),
            $variant,
            user: $user,
        );
    }

    /**
     * @param  list<array{value_ids?: list<int>, sku?: string|null, barcode?: string|null, low_stock_threshold?: int|null}>  $variants
     * @return Collection<int, ProductVariant>
     */
    private function createVariants(Product $product, array $variants): Collection
    {
        $attributes = $product->attributes()->get();
        $attributeIds = $attributes->pluck('id')->all();

        $allValueIds = collect($variants)->pluck('value_ids')->flatten()->unique()->all();
        $values = VariantAttributeValue::query()->whereIn('id', $allValueIds)->get()->keyBy('id');

        $existingKeys = $product->variants()->pluck('combination_key')->all();
        $seenKeys = [];
        $reservedSkus = [];
        $created = collect();

        foreach ($variants as $index => $variant) {
            $valueIds = array_values(array_unique(array_map('intval', $variant['value_ids'] ?? [])));
            $selected = collect($valueIds)->map(fn (int $id) => $values->get($id));

            if ($selected->contains(null)) {
                throw ValidationException::withMessages(["variants.{$index}.value_ids" => 'অজানা একটি মান বেছে নেওয়া হয়েছে।']);
            }

            $selectedAttributeIds = $selected->pluck('variant_attribute_id')->sort()->values()->all();
            $expected = collect($attributeIds)->sort()->values()->all();

            if ($selectedAttributeIds !== $expected) {
                throw ValidationException::withMessages([
                    "variants.{$index}.value_ids" => 'প্রতিটি ভ্যারিয়েন্টে প্রতিটি বৈশিষ্ট্যের ('.$attributes->pluck('name')->join(', ').') ঠিক একটি করে মান থাকতে হবে।',
                ]);
            }

            $key = ProductVariant::combinationKeyFor($valueIds);

            if (in_array($key, $existingKeys, true) || in_array($key, $seenKeys, true)) {
                throw ValidationException::withMessages(["variants.{$index}.value_ids" => 'এই কম্বিনেশনটি এই পণ্যে আগেই আছে।']);
            }

            $seenKeys[] = $key;

            $ordered = $attributes->map(fn ($attribute) => $selected->firstWhere('variant_attribute_id', $attribute->id));

            $sku = filled($variant['sku'] ?? null)
                ? Str::upper(trim($variant['sku']))
                : $this->generateSku($product, $ordered, $reservedSkus);

            if (in_array($sku, $reservedSkus, true) || ProductVariant::query()->where('sku', $sku)->exists()) {
                throw ValidationException::withMessages(["variants.{$index}.sku" => "SKU {$sku} আগেই ব্যবহার করা হয়েছে।"]);
            }

            $reservedSkus[] = $sku;

            $model = $product->variants()->create([
                'sku' => $sku,
                'barcode' => $variant['barcode'] ?? null,
                'combination_key' => $key,
                'name' => $ordered->isEmpty() ? 'সাধারণ' : $ordered->pluck('value')->join(' / '),
                'low_stock_threshold' => (int) ($variant['low_stock_threshold'] ?? 0),
                'status' => RecordStatus::Active,
            ]);

            foreach ($ordered as $value) {
                $model->attributeValues()->attach($value->id, ['variant_attribute_id' => $value->variant_attribute_id]);
            }

            $created->push($model);
        }

        return $created;
    }

    /**
     * @param  Collection<int, VariantAttributeValue>  $values
     * @param  list<string>  $reserved
     */
    private function generateSku(Product $product, Collection $values, array $reserved): string
    {
        $parts = $values->map(fn (VariantAttributeValue $value): string => Str::upper($value->code ?: Str::slug($value->value, '')));
        $base = collect([$product->code])->merge($parts)->join('-');
        $sku = $base;
        $suffix = 2;

        while (in_array($sku, $reserved, true) || ProductVariant::query()->where('sku', $sku)->exists()) {
            $sku = "{$base}-{$suffix}";
            $suffix++;
        }

        return $sku;
    }
}
