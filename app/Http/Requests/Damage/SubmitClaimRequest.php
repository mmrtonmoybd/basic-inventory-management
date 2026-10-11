<?php

namespace App\Http\Requests\Damage;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitClaimRequest extends FormRequest
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
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('is_active', true)],
            'supplier_message' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.damage_case_line_id' => ['required', 'integer', 'distinct'],
            'lines.*.quantity' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
