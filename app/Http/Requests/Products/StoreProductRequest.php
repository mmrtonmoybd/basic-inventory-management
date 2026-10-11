<?php

namespace App\Http\Requests\Products;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('products.manage');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:64', 'alpha_dash', 'unique:products,code'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'unit' => ['nullable', 'string', 'max:16'],
            'description' => ['nullable', 'string', 'max:2000'],
            'attribute_ids' => ['array', 'max:5'],
            'attribute_ids.*' => ['integer', 'distinct', 'exists:variant_attributes,id'],
            'variants' => ['array', 'max:500'],
            'variants.*.value_ids' => ['array'],
            'variants.*.value_ids.*' => ['integer'],
            'variants.*.sku' => ['nullable', 'string', 'max:64', 'alpha_dash'],
            'variants.*.low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}
