<?php

namespace App\Http\Requests\Stock;

use App\Enums\StockCondition;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('adjustments.create');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'count_date' => ['required', 'date', 'before_or_equal:tomorrow'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'string', 'max:40'],
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'lines.*.condition' => ['required', Rule::in([StockCondition::Available->value, StockCondition::Inspection->value, StockCondition::Damaged->value])],
            'lines.*.counted_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}
