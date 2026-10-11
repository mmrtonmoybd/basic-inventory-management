<?php

namespace App\Http\Requests\Stock;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(match ($this->route('type')) {
            'in' => 'stock.in',
            'out' => 'stock.out',
            'opening' => 'stock.opening',
            default => 'none',
        });
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'business_date' => ['required', 'date', 'before_or_equal:tomorrow'],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('is_active', true)],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'string', 'max:40'],
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.variant_id' => ['required', 'integer', 'distinct', 'exists:product_variants,id'],
            'lines.*.quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'lines.*.quantity' => 'quantity',
            'lines.*.variant_id' => 'variant',
        ];
    }
}
