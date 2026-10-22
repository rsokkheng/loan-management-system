<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\InstallmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'loan_id', 'installment_number', 'due_date', 'principal_due', 'interest_due', 'penalty_due',
    'principal_paid', 'interest_paid', 'penalty_paid', 'balance_after', 'is_arrears', 'status', 'paid_at',
])]
class LoanInstallment extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'installment_number' => 'integer',
            'due_date' => 'date',
            'principal_due' => 'decimal:2',
            'interest_due' => 'decimal:2',
            'penalty_due' => 'decimal:2',
            'principal_paid' => 'decimal:2',
            'interest_paid' => 'decimal:2',
            'penalty_paid' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'is_arrears' => 'boolean',
            'status' => InstallmentStatus::class,
            'penalty_applied_at' => 'datetime',
            'paid_at' => 'date',
        ];
    }

    /**
     * @return Attribute<float, never>
     */
    protected function totalDue(): Attribute
    {
        return Attribute::get(fn () => round(
            (float) $this->principal_due + (float) $this->interest_due + (float) $this->penalty_due,
            2,
        ));
    }

    /**
     * @return Attribute<float, never>
     */
    protected function totalPaid(): Attribute
    {
        return Attribute::get(fn () => round(
            (float) $this->principal_paid + (float) $this->interest_paid + (float) $this->penalty_paid,
            2,
        ));
    }

    /**
     * @return Attribute<float, never>
     */
    protected function balance(): Attribute
    {
        return Attribute::get(fn () => round(max(0, $this->total_due - $this->total_paid), 2));
    }

    /**
     * @return Attribute<int, never>
     */
    protected function daysOverdue(): Attribute
    {
        return Attribute::get(function () {
            if ($this->status === InstallmentStatus::Paid || $this->due_date->isFuture() || $this->due_date->isToday()) {
                return 0;
            }

            return (int) $this->due_date->diffInDays(today());
        });
    }

    public function isOverdue(): bool
    {
        return $this->days_overdue > 0;
    }

    /**
     * Unpaid installments whose due date has passed.
     *
     * @param  Builder<LoanInstallment>  $query
     */
    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->where('status', '!=', InstallmentStatus::Paid)
            ->whereDate('due_date', '<', today());
    }

    /**
     * Records belonging to loans in the given currency.
     *
     * @param  Builder<LoanInstallment>  $query
     */
    #[Scope]
    protected function inCurrency(Builder $query, Currency $currency): void
    {
        $query->whereHas('loan', fn (Builder $query) => $query->where('currency', $currency));
    }

    /**
     * @return BelongsTo<Loan, $this>
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
