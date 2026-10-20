<?php

namespace App\Http\Requests;

use App\Models\LoanProduct;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LoanRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'borrower_id' => [Rule::requiredIf($this->isMethod('post')), 'integer', Rule::exists('borrowers', 'id')->withoutTrashed()],
            'loan_product_id' => ['required', 'integer', Rule::exists('loan_products', 'id')->where('is_active', true)],
            'loan_officer_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'principal_amount' => ['required', 'numeric', 'min:1'],
            'term' => ['required', 'integer', 'min:1'],
            'application_date' => ['required', 'date', 'before_or_equal:today'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Ensure the amount and term fall within the selected product's limits.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $product = LoanProduct::query()->find($this->integer('loan_product_id'));
                $amount = (float) $this->input('principal_amount');
                $term = $this->integer('term');

                if ($amount < (float) $product->min_amount || $amount > (float) $product->max_amount) {
                    $validator->errors()->add('principal_amount', __('The amount must be between :min and :max for :product.', [
                        'min' => number_format((float) $product->min_amount, 2),
                        'max' => number_format((float) $product->max_amount, 2),
                        'product' => $product->name,
                    ]));
                }

                if ($term < $product->min_term || $term > $product->max_term) {
                    $validator->errors()->add('term', __('The term must be between :min and :max installments for :product.', ['min' => $product->min_term, 'max' => $product->max_term, 'product' => $product->name]));
                }
            },
        ];
    }
}
