<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * History record of one rescheduling of a loan's remaining balance.
 */
#[Fillable([
    'loan_id', 'restructure_date', 'principal_rescheduled', 'interest_carried', 'penalty_carried', 'penalty_waived',
    'previous_interest_rate', 'new_interest_rate', 'previous_remaining_installments', 'new_term',
    'previous_maturity_date', 'new_maturity_date', 'reason', 'created_by',
])]
class LoanRestructure extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'restructure_date' => 'date',
            'principal_rescheduled' => 'decimal:2',
            'interest_carried' => 'decimal:2',
            'penalty_carried' => 'decimal:2',
            'penalty_waived' => 'decimal:2',
            'previous_interest_rate' => 'decimal:4',
            'new_interest_rate' => 'decimal:4',
            'previous_remaining_installments' => 'integer',
            'new_term' => 'integer',
            'previous_maturity_date' => 'date',
            'new_maturity_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Loan, $this>
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
