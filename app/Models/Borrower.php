<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\LoanStatus;
use App\Models\Concerns\BelongsToBranch;
use Database\Factories\BorrowerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'branch_id',     'first_name', 'last_name', 'gender', 'date_of_birth', 'national_id', 'phone', 'email',
    'address', 'city', 'occupation', 'employer', 'monthly_income', 'photo_path',
    'is_blacklisted', 'notes', 'created_by',
])]
class Borrower extends Model
{
    /** @use HasFactory<BorrowerFactory> */
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::created(function (Borrower $borrower): void {
            $borrower->forceFill(['code' => sprintf('BR-%06d', $borrower->id)])->saveQuietly();
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
            'gender' => Gender::class,
            'date_of_birth' => 'date',
            'monthly_income' => 'decimal:2',
            'is_blacklisted' => 'boolean',
        ];
    }

    /**
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => "{$this->first_name} {$this->last_name}");
    }

    /**
     * @return Attribute<?string, never>
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null);
    }

    /**
     * Filter borrowers by name, code, phone or national ID.
     *
     * @param  Builder<Borrower>  $query
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when($term, function (Builder $query, string $term): void {
            $query->where(function (Builder $query) use ($term): void {
                $query->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('national_id', 'like', "%{$term}%");
            });
        });
    }

    public function hasOpenLoan(): bool
    {
        return $this->loans()
            ->whereIn('status', [LoanStatus::Pending, LoanStatus::Approved, LoanStatus::Active])
            ->exists();
    }

    /**
     * @return HasMany<Loan, $this>
     */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /**
     * @return HasMany<BorrowerDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(BorrowerDocument::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
