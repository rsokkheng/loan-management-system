<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $account = $this->route('account');

        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('accounts')->ignore($account)],
            'name' => ['required', 'string', 'max:150'],
            'name_km' => ['nullable', 'string', 'max:150'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'parent_id' => ['nullable', 'integer', Rule::exists('accounts', 'id'), Rule::notIn([$account?->id])],
            'is_cash' => ['boolean'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_cash' => $this->boolean('is_cash'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
