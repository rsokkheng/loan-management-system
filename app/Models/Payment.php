<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'branch_id', 'loan_id', 'amount', 'principal_amount', 'interest_amount', 'penalty_amount', 'is_settlement', 'is_recovery',
    'settlement_fee', 'interest_waived', 'payment_date',
    'method', 'transaction_reference', 'notes', 'received_by',
])]
class Payment extends Model
{
    use BelongsToBranch;

    protected static function booted(): void
    {
        static::created(function (Payment $payment): void {
            $payment->forceFill([
                'receipt_number' => sprintf('RCT-%s-%06d', $payment->created_at->format('Y'), $payment->id),
            ])->saveQuietly();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'principal_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'penalty_amount' => 'decimal:2',
            'is_settlement' => 'boolean',
            'is_recovery' => 'boolean',
            'settlement_fee' => 'decimal:2',
            'interest_waived' => 'decimal:2',
            'payment_date' => 'date',
            'method' => PaymentMethod::class,
        ];
    }

    /**
     * Records belonging to loans in the given currency.
     *
     * @param  Builder<Payment>  $query
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
        return $this->belongsTo(Loan::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return MorphMany<JournalEntry, $this>
     */
    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }
}
