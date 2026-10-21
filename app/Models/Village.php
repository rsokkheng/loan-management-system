<?php

namespace App\Models;

use App\Models\Concerns\IsLocation;
use Database\Factories\VillageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Imported gazetteer reference data; not edited from the app.
 */
#[Fillable(['code', 'name_kh', 'name_en', 'commune_id'])]
class Village extends Model
{
    /** @use HasFactory<VillageFactory> */
    use HasFactory, IsLocation, SoftDeletes;

    /**
     * @return BelongsTo<Commune, $this>
     */
    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }
}
