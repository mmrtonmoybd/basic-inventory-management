<?php

namespace App\Http\Requests\Returns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('returns.create');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'return_date' => ['required', 'date', 'before_or_equal:tomorrow'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'string', 'max:40'],
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.variant_id' => ['required', 'integer', 'distinct', 'exists:product_variants,id'],
            'lines.*.quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'lines.*.reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
