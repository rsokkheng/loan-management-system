<?php

namespace App\Http\Requests;

use App\Enums\Gender;
use App\Enums\Relationship;
use App\Models\Loan;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuarantorRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:150'],
            'relationship' => ['required', Rule::enum(Relationship::class)],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'national_id' => ['nullable', 'string', 'max:50', $this->isNotTheBorrower(...)],
            'phone' => ['required', 'string', 'max:30'],
            'occupation' => ['nullable', 'string', 'max:150'],
            'employer' => ['nullable', 'string', 'max:150'],
            'monthly_income' => ['nullable', 'numeric', 'min:0'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * A borrower cannot guarantee their own loan.
     */
    private function isNotTheBorrower(string $attribute, mixed $value, Closure $fail): void
    {
        $loan = $this->route('loan');

        if ($loan instanceof Loan && filled($value) && $loan->borrower->national_id === $value) {
            $fail(__('The borrower cannot be their own guarantor.'));
        }
    }
}
