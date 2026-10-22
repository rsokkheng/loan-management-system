<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RestructureLoanRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'term' => ['required', 'integer', 'min:1', 'max:1000'],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:500'],
            'first_repayment_date' => ['required', 'date', 'after:today'],
            'waive_penalty' => ['boolean'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['waive_penalty' => $this->boolean('waive_penalty')]);
    }
}
