<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['user_id', 'action', 'subject_type', 'subject_id', 'description', 'properties', 'changes', 'ip_address'])]
class ActivityLog extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'changes' => 'array',
        ];
    }

    /**
     * Attributes never written to the audit trail.
     *
     * @var list<string>
     */
    public const IGNORED_ATTRIBUTES = ['updated_at', 'created_at', 'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    /**
     * Record an action performed by the current user.
     *
     * When the subject was just updated, its before/after values are captured
     * automatically unless explicit $changes are given.
     *
     * @param  array<string, mixed>  $properties
     * @param  array<string, array{old: mixed, new: mixed}>|null  $changes
     */
    public static function record(string $action, string $description, ?Model $subject = null, array $properties = [], ?array $changes = null): self
    {
        $changes ??= $action === 'created' ? [] : static::changesOf($subject);

        return static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'properties' => $properties ?: null,
            'changes' => $changes ?: null,
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * Fields changed by the subject's last save, with their previous and new values.
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public static function changesOf(?Model $subject): array
    {
        if (! $subject || ! $subject->wasChanged()) {
            return [];
        }

        $previous = $subject->getPrevious();
        $ignored = [...self::IGNORED_ATTRIBUTES, ...$subject->getHidden()];

        return collect($subject->getChanges())
            ->except($ignored)
            ->map(fn (mixed $new, string $key) => ['old' => $previous[$key] ?? null, 'new' => $new])
            ->all();
    }

    /**
     * Compare two key/value sets (e.g. settings) in the same shape as {@see changesOf()}.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public static function diff(array $before, array $after): array
    {
        return collect($after)
            ->filter(fn (mixed $value, string $key) => (string) ($before[$key] ?? '') !== (string) $value)
            ->map(fn (mixed $value, string $key) => ['old' => $before[$key] ?? null, 'new' => $value])
            ->all();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function color(): string
    {
        return match ($this->action) {
            'created', 'login' => 'success',
            'approved', 'disbursed', 'resubmitted' => 'primary',
            'returned', 'restructured' => 'warning',
            'settled', 'recovery' => 'success',
            'payment' => 'info',
            'rejected', 'deleted', 'written_off' => 'danger',
            default => 'secondary',
        };
    }
}
