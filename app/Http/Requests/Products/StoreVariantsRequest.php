<?php

namespace App\Http\Requests\Products;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreVariantsRequest extends FormRequest
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
            'variants' => ['required', 'array', 'min:1', 'max:500'],
            'variants.*.value_ids' => ['required', 'array', 'min:1'],
            'variants.*.value_ids.*' => ['integer'],
            'variants.*.sku' => ['nullable', 'string', 'max:64', 'alpha_dash'],
            'variants.*.low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}
