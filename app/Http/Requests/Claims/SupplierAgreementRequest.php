<?php

namespace App\Http\Requests\Claims;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SupplierAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recordAgreement', $this->route('claim'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'supplier_reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.line_id' => ['required', 'integer', 'distinct'],
            'lines.*.approved_quantity' => ['required', 'integer', 'min:0'],
            'lines.*.replacement_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
        ];
    }
}
