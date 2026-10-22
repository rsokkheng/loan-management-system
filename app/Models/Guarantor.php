<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\Relationship;
use Database\Factories\GuarantorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'loan_id', 'full_name', 'relationship', 'gender', 'national_id', 'phone', 'occupation', 'employer',
    'monthly_income', 'address', 'notes', 'created_by',
])]
class Guarantor extends Model
{
    /** @use HasFactory<GuarantorFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'relationship' => Relationship::class,
            'gender' => Gender::class,
            'monthly_income' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Loan, $this>
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
