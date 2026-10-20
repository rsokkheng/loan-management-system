<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CashTransactionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $categoryType = $this->input('type') === 'income' ? AccountType::Income : AccountType::Expense;

        return [
            'type' => ['required', Rule::in(['expense', 'income'])],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'entry_date' => ['required', 'date', 'before_or_equal:today'],
            'account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('type', $categoryType->value)->where('is_active', true)],
            'cash_account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('is_cash', true)->where('is_active', true)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:255'],
        ];
    }
}
