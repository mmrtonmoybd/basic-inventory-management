<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantAttribute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SplFileObject;

/**
 * Bulk-creates products, variants and opening stock from CSV.
 *
 * Expected header: product_code, product_name, category, attributes, sku, low_stock_threshold, opening_quantity
 * where `attributes` is "Size:L|Color:Red" (blank for products without variants).
 * The whole file is validated first; nothing is written unless every row is valid.
 */
class CsvImportService
{
    public const MAX_ROWS = 5000;

    public const HEADER = ['product_code', 'product_name', 'category', 'attributes', 'sku', 'low_stock_threshold', 'opening_quantity'];

    public function __construct(
        private ProductCatalogService $catalog,
        private StockEntryService $stock,
        private ActivityLogger $activity,
    ) {}

    /**
     * @return array{valid: bool, errors: list<array{row: int, message: string}>, summary: array{rows: int, new_products: int, new_variants: int, opening_units: int}, committed: bool}
     */
    public function import(string $path, int $warehouseId, User $user, bool $commit): array
    {
        [$rows, $errors] = $this->parse($path);

        if ($errors === []) {
            $errors = $this->validateRows($rows);
        }

        $grouped = $rows->groupBy('product_code');
        $existingCodes = Product::query()->whereIn('code', $grouped->keys())->pluck('code')->all();

        $summary = [
            'rows' => $rows->count(),
            'new_products' => $grouped->keys()->diff($existingCodes)->count(),
            'new_variants' => $rows->count(),
            'opening_units' => (int) $rows->sum('opening_quantity'),
        ];

        if ($errors !== [] || ! $commit) {
            return ['valid' => $errors === [], 'errors' => $errors, 'summary' => $summary, 'committed' => false];
        }

        DB::transaction(function () use ($grouped, $warehouseId, $user): void {
            $openingLines = [];

            foreach ($grouped as $code => $productRows) {
                $first = $productRows->first();
                $attributeIds = collect($first['values'])->pluck('variant_attribute_id')->all();
                $variants = $productRows->map(fn (array $row): array => [
                    'value_ids' => collect($row['values'])->pluck('id')->all(),
                    'sku' => $row['sku'] ?: null,
                    'low_stock_threshold' => $row['low_stock_threshold'],
                ])->values()->all();

                $product = Product::query()->where('code', $code)->first();

                if ($product === null) {
                    $product = $this->catalog->create([
                        'name' => $first['product_name'],
                        'code' => $code,
                        'category_id' => $this->categoryId($first['category']),
                        'attribute_ids' => $attributeIds,
                        'variants' => $variants,
                    ], $user);
                    $created = $product->variants()->get();
                } else {
                    $created = $this->catalog->addVariants($product, $variants, $user);
                }

                foreach ($productRows->values() as $index => $row) {
                    $variant = $attributeIds === [] ? $created->first() : $created->firstWhere('combination_key', ProductVariant::combinationKeyFor(collect($row['values'])->pluck('id')->all()));

                    if ($row['opening_quantity'] > 0 && $variant) {
                        $openingLines[] = ['variant_id' => $variant->id, 'quantity' => $row['opening_quantity']];
                    }
                }
            }

            if ($openingLines !== []) {
                $this->stock->post(MovementType::Opening, [
                    'warehouse_id' => $warehouseId,
                    'notes' => 'CSV import opening stock',
                    'lines' => $openingLines,
                ], $user);
            }

            $this->activity->log('import.completed', 'Imported '.count($grouped).' product(s) from CSV', user: $user);
        });

        return ['valid' => true, 'errors' => [], 'summary' => $summary, 'committed' => true];
    }

    /**
     * @return array{0: Collection<int, array<string, mixed>>, 1: list<array{row: int, message: string}>}
     */
    private function parse(string $path): array
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD);

        $header = null;
        $rows = collect();
        $errors = [];
        $attributes = VariantAttribute::query()->with('values')->get()->keyBy(fn (VariantAttribute $attribute): string => mb_strtolower($attribute->name));

        foreach ($file as $index => $line) {
            if ($line === [null] || $line === false) {
                continue;
            }

            if ($header === null) {
                $header = array_map(fn ($column): string => Str::snake(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $column))), $line);
                $missing = array_diff(self::HEADER, $header);

                if ($missing !== []) {
                    return [collect(), [['row' => 1, 'message' => 'এই কলামগুলো নেই: '.implode(', ', $missing)]]];
                }

                continue;
            }

            if ($rows->count() >= self::MAX_ROWS) {
                return [collect(), [['row' => $index + 1, 'message' => 'একটি ফাইলে সর্বোচ্চ '.Bangla::number(self::MAX_ROWS).' লাইন দেওয়া যাবে। ফাইলটি ভাগ করে ছোট ছোট করে আমদানি করুন।']]];
            }

            $rowNumber = $index + 1;
            $data = array_combine($header, array_pad(array_slice($line, 0, count($header)), count($header), null));
            $values = [];

            foreach (array_filter(explode('|', (string) $data['attributes'])) as $pair) {
                [$name, $value] = array_pad(array_map('trim', explode(':', $pair, 2)), 2, null);
                $attribute = $attributes->get(mb_strtolower((string) $name));
                $match = $attribute?->values->first(fn ($candidate): bool => mb_strtolower($candidate->value) === mb_strtolower((string) $value));

                if ($match === null) {
                    $errors[] = ['row' => $rowNumber, 'message' => "অজানা মান \"{$pair}\"। আগে সেটআপ > বৈশিষ্ট্য পাতায় এটি যোগ করুন।"];
                } else {
                    $values[] = $match;
                }
            }

            $rows->push([
                'row' => $rowNumber,
                'product_code' => Str::upper(trim((string) $data['product_code'])),
                'product_name' => trim((string) $data['product_name']),
                'category' => trim((string) $data['category']),
                'sku' => Str::upper(trim((string) $data['sku'])),
                'values' => collect($values)->sortBy('variant_attribute_id')->values()->all(),
                'low_stock_threshold' => (int) $data['low_stock_threshold'],
                'opening_quantity' => (int) $data['opening_quantity'],
                'raw_quantity' => trim((string) $data['opening_quantity']),
            ]);
        }

        if ($rows->isEmpty() && $errors === []) {
            $errors[] = ['row' => 1, 'message' => 'ফাইলে কোনো ডেটা নেই।'];
        }

        return [$rows, $errors];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array{row: int, message: string}>
     */
    private function validateRows(Collection $rows): array
    {
        $errors = [];
        $seenSkus = [];
        $seenCombos = [];

        foreach ($rows as $row) {
            $n = $row['row'];

            if ($row['product_code'] === '' || ! preg_match('/^[A-Z0-9_-]+$/', $row['product_code'])) {
                $errors[] = ['row' => $n, 'message' => 'product_code is required and may only contain letters, numbers, dashes and underscores.'];
            }

            if ($row['product_name'] === '' && ! Product::query()->where('code', $row['product_code'])->exists()) {
                $errors[] = ['row' => $n, 'message' => 'product_name is required for new products.'];
            }

            if ($row['raw_quantity'] !== '' && ! ctype_digit($row['raw_quantity'])) {
                $errors[] = ['row' => $n, 'message' => 'opening_quantity must be a whole number ≥ 0.'];
            }

            if ($row['low_stock_threshold'] < 0) {
                $errors[] = ['row' => $n, 'message' => 'low_stock_threshold cannot be negative.'];
            }

            if ($row['sku'] !== '') {
                if (in_array($row['sku'], $seenSkus, true) || ProductVariant::query()->where('sku', $row['sku'])->exists()) {
                    $errors[] = ['row' => $n, 'message' => "SKU {$row['sku']} is already in use."];
                }

                $seenSkus[] = $row['sku'];
            }

            $key = $row['product_code'].'#'.ProductVariant::combinationKeyFor(collect($row['values'])->pluck('id')->all());

            if (in_array($key, $seenCombos, true)) {
                $errors[] = ['row' => $n, 'message' => 'ফাইলে একই কম্বিনেশন দুইবার আছে।'];
            }

            $seenCombos[] = $key;
        }

        foreach ($rows->groupBy('product_code') as $code => $productRows) {
            $signatures = $productRows->map(fn (array $row): string => collect($row['values'])->pluck('variant_attribute_id')->sort()->join(','))->unique();

            if ($signatures->count() > 1) {
                $errors[] = ['row' => $productRows->first()['row'], 'message' => "{$code} এর সব লাইনে একই বৈশিষ্ট্য থাকতে হবে।"];

                continue;
            }

            $product = Product::query()->where('code', $code)->first();

            if ($product) {
                $existing = $product->attributes()->pluck('variant_attributes.id')->sort()->join(',');

                if ($existing !== $signatures->first()) {
                    $errors[] = ['row' => $productRows->first()['row'], 'message' => "{$code} already exists with different attributes."];
                }

                if ($existing === '') {
                    $errors[] = ['row' => $productRows->first()['row'], 'message' => "{$code} already exists and has no variant attributes."];
                }
            } elseif ($signatures->first() === '' && $productRows->count() > 1) {
                $errors[] = ['row' => $productRows->first()['row'], 'message' => "{$code} has no attributes, so it can only have one row."];
            }
        }

        usort($errors, fn (array $a, array $b): int => $a['row'] <=> $b['row']);

        return $errors;
    }

    private function categoryId(string $name): ?int
    {
        if ($name === '') {
            return null;
        }

        return Category::query()->firstOrCreate(['name' => $name], ['slug' => Str::slug($name).'-'.Str::lower(Str::random(4))])->id;
    }
}
