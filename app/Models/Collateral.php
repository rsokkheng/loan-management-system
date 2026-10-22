<?php

namespace App\Models;

use App\Enums\CollateralStatus;
use App\Enums\CollateralType;
use Database\Factories\CollateralFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'loan_id', 'type', 'description', 'document_number', 'owner_name', 'estimated_value', 'valuation_date',
    'location', 'file_path', 'original_name', 'status', 'created_by',
])]
class Collateral extends Model
{
    /** @use HasFactory<CollateralFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CollateralType::class,
            'status' => CollateralStatus::class,
            'estimated_value' => 'decimal:2',
            'valuation_date' => 'date',
            'released_at' => 'datetime',
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
    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
