<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JournalEntryRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'entry_date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('is_active', true)],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.memo' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Drop rows the user left completely empty in the dynamic form.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'lines' => collect($this->input('lines', []))
                ->filter(fn ($line) => is_array($line) && (filled($line['account_id'] ?? null) || filled($line['debit'] ?? null) || filled($line['credit'] ?? null)))
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['lines.*.account_id' => __('account')];
    }
}
