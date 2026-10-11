<?php

namespace App\Http\Requests\Damage;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDamageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('damage.create');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'damage_date' => ['required', 'date', 'before_or_equal:tomorrow'],
            'reason' => ['required', 'string', 'max:255'],
            'internal_note' => ['nullable', 'string', 'max:2000'],
            'supplier_message' => ['nullable', 'string', 'max:2000'],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('is_active', true)],
            'request_exchange' => ['boolean'],
            'idempotency_key' => ['required', 'string', 'max:40'],
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.variant_id' => ['required', 'integer', 'distinct', 'exists:product_variants,id'],
            'lines.*.quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'images' => ['array', 'max:6'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
