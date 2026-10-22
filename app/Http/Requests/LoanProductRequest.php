<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use App\Enums\InterestMethod;
use App\Enums\RepaymentFrequency;
use App\Enums\ScheduleType;
use App\Models\LoanProduct;
use Closure;
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
            'currency' => ['required', Rule::enum(Currency::class), $this->currencyIsUnchangedOnceUsed(...)],
            'description' => ['nullable', 'string', 'max:1000'],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:500'],
            'interest_method' => ['required', Rule::enum(InterestMethod::class)],
            'repayment_frequency' => ['required', Rule::enum(RepaymentFrequency::class)],
            'schedule_type' => ['required', Rule::enum(ScheduleType::class)],
            'min_amount' => ['required', 'numeric', 'min:1'],
            'max_amount' => ['required', 'numeric', 'gte:min_amount'],
            'min_term' => ['required', 'integer', 'min:1', 'max:1000'],
            'max_term' => ['required', 'integer', 'gte:min_term', 'max:1000'],
            'processing_fee_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'late_penalty_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'grace_period_days' => ['required', 'integer', 'min:0', 'max:365'],
            'requires_guarantor' => ['boolean'],
            'min_collateral_coverage' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * A product's currency is locked once loans have been issued under it.
     */
    private function currencyIsUnchangedOnceUsed(string $attribute, mixed $value, Closure $fail): void
    {
        $product = $this->route('loanProduct');

        if ($product instanceof LoanProduct && $product->currency->value !== $value && $product->loans()->exists()) {
            $fail(__('The currency cannot be changed because this product already has loans.'));
        }
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'requires_guarantor' => $this->boolean('requires_guarantor'),
            'min_collateral_coverage' => $this->input('min_collateral_coverage') ?: 0,
            'code' => mb_strtoupper((string) $this->input('code')),
        ]);
    }
}
