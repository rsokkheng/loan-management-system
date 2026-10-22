<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['branch_id', 'name', 'email', 'phone', 'password', 'role', 'locale', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Largest principal this user may approve in the currency, or null for no limit.
     */
    public function approvalLimit(Currency $currency): ?float
    {
        if ($this->isAdmin()) {
            return null;
        }

        $limit = Setting::get('approval_limit_'.$this->role->value.'_'.strtolower($currency->value));

        return filled($limit) ? (float) $limit : null;
    }

    public function canApproveAmount(float $principal, Currency $currency): bool
    {
        $limit = $this->approvalLimit($currency);

        return $limit === null || $principal <= $limit;
    }

    /**
     * Initials used for the avatar placeholder.
     */
    public function initials(): string
    {
        return collect(explode(' ', $this->name))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<Loan, $this>
     */
    public function managedLoans(): HasMany
    {
        return $this->hasMany(Loan::class, 'loan_officer_id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function receivedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'received_by');
    }
}
