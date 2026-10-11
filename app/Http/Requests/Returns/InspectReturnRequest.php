<?php

namespace App\Http\Requests\Returns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class InspectReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('returns.inspect');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:2000'],
            'damage_reason' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.return_line_id' => ['required', 'integer', 'distinct'],
            'lines.*.good_quantity' => ['nullable', 'integer', 'min:0'],
            'lines.*.damaged_quantity' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
