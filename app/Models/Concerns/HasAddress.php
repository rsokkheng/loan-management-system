<?php

namespace App\Models\Concerns;

use App\Models\Commune;
use App\Models\District;
use App\Models\Province;
use App\Models\Village;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * House/street plus village → commune → district → province.
 */
trait HasAddress
{
    /**
     * Relations to eager load before showing a full address.
     *
     * @var list<string>
     */
    public const ADDRESS_RELATIONS = ['province', 'district', 'commune', 'village'];

    /**
     * Full address from the smallest part to the largest, in the current language.
     *
     * @return Attribute<string, never>
     */
    protected function fullAddress(): Attribute
    {
        return Attribute::get(fn () => collect([
            $this->address,
            $this->village?->display_name,
            $this->commune?->display_name,
            $this->district?->display_name,
            $this->province?->display_name,
        ])->filter()->implode(', '));
    }

    /**
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class)->withTrashed();
    }

    /**
     * @return BelongsTo<District, $this>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Commune, $this>
     */
    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Village, $this>
     */
    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class)->withTrashed();
    }
}
