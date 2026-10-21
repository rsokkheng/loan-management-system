<?php

namespace App\Models;

use App\Models\Concerns\IsLocation;
use Database\Factories\DistrictFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Imported gazetteer reference data; not edited from the app.
 */
#[Fillable(['code', 'name_kh', 'name_en', 'province_id'])]
class District extends Model
{
    /** @use HasFactory<DistrictFactory> */
    use HasFactory, IsLocation, SoftDeletes;

    /**
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }
}
