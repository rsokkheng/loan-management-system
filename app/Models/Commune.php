<?php

namespace App\Models;

use App\Models\Concerns\IsLocation;
use Database\Factories\CommuneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Imported gazetteer reference data; not edited from the app.
 */
#[Fillable(['code', 'name_kh', 'name_en', 'district_id'])]
class Commune extends Model
{
    /** @use HasFactory<CommuneFactory> */
    use HasFactory, IsLocation, SoftDeletes;

    /**
     * @return BelongsTo<District, $this>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }
}
