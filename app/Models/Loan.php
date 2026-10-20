<?php

namespace App\Models;

use App\Enums\InstallmentStatus;
use App\Enums\InterestMethod;
use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Enums\RepaymentFrequency;
use App\Models\Concerns\BelongsToBranch;
use Database\Factories\LoanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'branch_id',     'borrower_id', 'loan_product_id', 'loan_officer_id', 'principal_amount', 'interest_rate',
    'interest_method', 'repayment_frequency', 'term', 'processing_fee', 'late_penalty_percent',
    'grace_period_days', 'total_interest', 'total_payable', 'status', 'purpose', 'notes',
    'application_date', 'created_by',
])]
class Loan extends Model
{
    /** @use HasFactory<LoanFactory> */
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::created(function (Loan $loan): void {
            $loan->forceFill([
                'reference' => sprintf('LN-%s-%05d', $loan->created_at->format('Y'), $loan->id),
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
            'principal_amount' => 'decimal:2',
            'interest_rate' => 'decimal:4',
            'interest_method' => InterestMethod::class,
            'repayment_frequency' => RepaymentFrequency::class,
            'term' => 'integer',
            'processing_fee' => 'decimal:2',
            'late_penalty_percent' => 'decimal:2',
            'grace_period_days' => 'integer',
            'total_interest' => 'decimal:2',
            'total_payable' => 'decimal:2',
            'total_penalty' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'status' => LoanStatus::class,
            'application_date' => 'date',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'disbursed_at' => 'date',
            'disbursement_method' => PaymentMethod::class,
            'first_repayment_date' => 'date',
            'maturity_date' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Amount still owed, including any penalties charged so far.
     *
     * @return Attribute<float, never>
     */
    protected function outstandingBalance(): Attribute
    {
        return Attribute::get(fn () => round(
            max(0, (float) $this->total_payable + (float) $this->total_penalty - (float) $this->amount_paid),
            2,
        ));
    }

    /**
     * Repayment progress as a percentage of the total amount due.
     *
     * @return Attribute<int, never>
     */
    protected function progressPercent(): Attribute
    {
        return Attribute::get(function () {
            $totalDue = (float) $this->total_payable + (float) $this->total_penalty;

            return $totalDue > 0 ? (int) min(100, round((float) $this->amount_paid / $totalDue * 100)) : 0;
        });
    }

    public function isEditable(): bool
    {
        return $this->status === LoanStatus::Pending;
    }

    public function isOverdue(): bool
    {
        if ($this->status !== LoanStatus::Active) {
            return false;
        }

        return $this->installments()->overdue()->exists();
    }

    /**
     * @param  Builder<Loan>  $query
     */
    #[Scope]
    protected function withStatus(Builder $query, LoanStatus ...$statuses): void
    {
        $query->whereIn('status', $statuses);
    }

    /**
     * Active loans with at least one installment past its due date.
     *
     * @param  Builder<Loan>  $query
     */
    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->where('status', LoanStatus::Active)
            ->whereHas('installments', fn (Builder $query) => $query->overdue());
    }

    /**
     * Filter loans by reference or borrower details.
     *
     * @param  Builder<Loan>  $query
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when($term, function (Builder $query, string $term): void {
            $query->where(function (Builder $query) use ($term): void {
                $query->where('reference', 'like', "%{$term}%")
                    ->orWhereHas('borrower', fn (Builder $query) => $query->search($term));
            });
        });
    }

    /**
     * @return BelongsTo<Borrower, $this>
     */
    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Borrower::class)->withTrashed();
    }

    /**
     * @return BelongsTo<LoanProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'loan_officer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function disburser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disbursed_by');
    }

    /**
     * @return HasMany<LoanInstallment, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class)->orderBy('installment_number');
    }

    /**
     * @return HasMany<LoanInstallment, $this>
     */
    public function unpaidInstallments(): HasMany
    {
        return $this->installments()->where('status', '!=', InstallmentStatus::Paid);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return MorphMany<JournalEntry, $this>
     */
    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }
}
