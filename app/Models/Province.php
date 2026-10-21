<?php

namespace App\Models;

use App\Models\Concerns\IsLocation;
use Database\Factories\ProvinceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Imported gazetteer reference data; not edited from the app.
 */
#[Fillable(['code', 'name_kh', 'name_en'])]
class Province extends Model
{
    /** @use HasFactory<ProvinceFactory> */
    use HasFactory, IsLocation, SoftDeletes;
}
