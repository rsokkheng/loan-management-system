<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:150'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'currency_code' => ['required', Rule::enum(Currency::class)],
            'receipt_footer' => ['nullable', 'string', 'max:500'],
            'early_settlement_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'approval_limit_manager_usd' => ['nullable', 'numeric', 'min:0'],
            'approval_limit_manager_khr' => ['nullable', 'numeric', 'min:0'],
            'require_maker_checker' => ['required', 'in:0,1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['require_maker_checker' => $this->boolean('require_maker_checker') ? '1' : '0']);
    }
}
