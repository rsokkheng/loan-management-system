<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\Relationship;
use Database\Factories\FamilyMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'borrower_id', 'full_name', 'relationship', 'gender', 'date_of_birth', 'phone', 'occupation',
    'monthly_income', 'is_dependent',
])]
class FamilyMember extends Model
{
    /** @use HasFactory<FamilyMemberFactory> */
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
            'date_of_birth' => 'date',
            'monthly_income' => 'decimal:2',
            'is_dependent' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Borrower, $this>
     */
    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Borrower::class);
    }
}
