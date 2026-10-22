<?php

namespace App\Http\Requests;

use App\Enums\ScheduleType;
use App\Models\LoanProduct;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
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
            'plan' => ['nullable', 'array', 'max:1000'],
            'plan.*.due_date' => ['required', 'date'],
            'plan.*.principal' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Flexible schedules take their term from the number of rows entered;
     * rows left completely empty in the form are ignored.
     */
    protected function prepareForValidation(): void
    {
        $isFlexible = LoanProduct::query()->find($this->integer('loan_product_id'))?->schedule_type === ScheduleType::Flexible;

        $plan = $isFlexible ? collect($this->input('plan', []))
            ->filter(fn ($row) => is_array($row) && (filled($row['due_date'] ?? null) || filled($row['principal'] ?? null)))
            ->values() : collect();

        $this->merge(['plan' => $plan->all()]);

        if ($isFlexible) {
            $this->merge(['term' => max(1, $plan->count())]);
        }
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

                if ($product->schedule_type === ScheduleType::Flexible) {
                    $this->validateFlexiblePlan($validator, $product, $amount);
                }

                if ($term < $product->min_term || $term > $product->max_term) {
                    $validator->errors()->add('term', __('The term must be between :min and :max installments for :product.', ['min' => $product->min_term, 'max' => $product->max_term, 'product' => $product->name]));
                }
            },
        ];
    }

    /**
     * Due dates must run forward from the application date and the principal must add up to the loan amount.
     */
    private function validateFlexiblePlan(Validator $validator, LoanProduct $product, float $amount): void
    {
        $plan = collect($this->input('plan', []));

        if ($plan->isEmpty()) {
            $validator->errors()->add('plan', __('Enter at least one due date for a flexible schedule.'));

            return;
        }

        $previous = Carbon::parse($this->input('application_date'));

        foreach ($plan as $index => $row) {
            $dueDate = Carbon::parse($row['due_date']);

            if ($dueDate->lte($previous)) {
                $validator->errors()->add("plan.{$index}.due_date", __('Each due date must be after the previous one and after the application date.'));

                return;
            }

            $previous = $dueDate;
        }

        $total = $product->currency->round($plan->sum(fn (array $row) => (float) $row['principal']));

        if ($total !== $product->currency->round($amount)) {
            $validator->errors()->add('plan', __('The principal in the schedule adds up to :total but the loan amount is :amount.', [
                'total' => $product->currency->format($total),
                'amount' => $product->currency->format($amount),
            ]));
        }
    }
}
