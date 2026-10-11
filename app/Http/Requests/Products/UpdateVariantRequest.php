<?php

namespace App\Http\Requests\Products;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVariantRequest extends FormRequest
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
        $variant = $this->route('variant');

        return [
            'sku' => ['required', 'string', 'max:64', 'alpha_dash', Rule::unique('product_variants', 'sku')->ignore($variant)],
            'barcode' => ['nullable', 'string', 'max:64', Rule::unique('product_variants', 'barcode')->ignore($variant)],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}
