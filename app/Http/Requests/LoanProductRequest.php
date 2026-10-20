<?php

namespace App\Http\Requests;

use App\Enums\InterestMethod;
use App\Enums\RepaymentFrequency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoanProductRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('loan_products')->ignore($this->route('loanProduct'))],
            'description' => ['nullable', 'string', 'max:1000'],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:500'],
            'interest_method' => ['required', Rule::enum(InterestMethod::class)],
            'repayment_frequency' => ['required', Rule::enum(RepaymentFrequency::class)],
            'min_amount' => ['required', 'numeric', 'min:1'],
            'max_amount' => ['required', 'numeric', 'gte:min_amount'],
            'min_term' => ['required', 'integer', 'min:1', 'max:1000'],
            'max_term' => ['required', 'integer', 'gte:min_term', 'max:1000'],
            'processing_fee_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'late_penalty_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'grace_period_days' => ['required', 'integer', 'min:0', 'max:365'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'code' => mb_strtoupper((string) $this->input('code')),
        ]);
    }
}
