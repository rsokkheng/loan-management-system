<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\InterestMethod;
use App\Enums\RepaymentFrequency;
use App\Enums\ScheduleType;
use Database\Factories\LoanProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'code', 'currency', 'description', 'interest_rate', 'interest_method', 'repayment_frequency', 'schedule_type',
    'min_amount', 'max_amount', 'min_term', 'max_term', 'processing_fee_percent',
    'late_penalty_percent', 'grace_period_days', 'requires_guarantor', 'min_collateral_coverage', 'is_active',
])]
class LoanProduct extends Model
{
    /** @use HasFactory<LoanProductFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'interest_rate' => 'decimal:4',
            'interest_method' => InterestMethod::class,
            'repayment_frequency' => RepaymentFrequency::class,
            'schedule_type' => ScheduleType::class,
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'min_term' => 'integer',
            'max_term' => 'integer',
            'processing_fee_percent' => 'decimal:2',
            'late_penalty_percent' => 'decimal:2',
            'grace_period_days' => 'integer',
            'requires_guarantor' => 'boolean',
            'min_collateral_coverage' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<LoanProduct>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return HasMany<Loan, $this>
     */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }
}
