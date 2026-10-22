<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\JournalType;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['branch_id', 'currency', 'entry_date', 'type', 'description', 'source_type', 'source_id', 'reversal_of_id', 'created_by'])]
class JournalEntry extends Model
{
    use BelongsToBranch;

    protected static function booted(): void
    {
        static::created(function (JournalEntry $entry): void {
            $entry->forceFill([
                'reference' => sprintf('JE-%s-%06d', $entry->created_at->format('Y'), $entry->id),
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
            'currency' => Currency::class,
            'entry_date' => 'date',
            'type' => JournalType::class,
        ];
    }

    public function canBeReversed(): bool
    {
        return ! $this->type->isAutomatic() && $this->type !== JournalType::Reversal;
    }

    /**
     * @return HasMany<JournalLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_of_id');
    }

    /**
     * @return HasOne<JournalEntry, $this>
     */
    public function reversedBy(): HasOne
    {
        return $this->hasOne(JournalEntry::class, 'reversal_of_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
