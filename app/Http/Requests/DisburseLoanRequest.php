<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DisburseLoanRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'disbursed_at' => ['required', 'date', 'before_or_equal:today'],
            'first_repayment_date' => ['required', 'date', 'after:disbursed_at'],
            'disbursement_method' => ['required', Rule::enum(PaymentMethod::class)],
        ];
    }
}
