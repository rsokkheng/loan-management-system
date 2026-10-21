<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Schema;

/**
 * Shared behaviour for imported gazetteer tables (province, district, commune, village).
 */
trait IsLocation
{
    /**
     * Columns present per table, so optional gazetteer columns are only filtered when they exist.
     *
     * @var array<string, list<string>>
     */
    private static array $knownColumns = [];

    /**
     * Name in the current language, falling back to the other one.
     *
     * @return Attribute<string, never>
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn () => (app()->getLocale() === 'km'
            ? ($this->name_kh ?: $this->name_en)
            : ($this->name_en ?: $this->name_kh)) ?? '');
    }

    /**
     * Records that are still in use (active status).
     *
     * The gazetteer's end_date is not maintained (most active villages carry a placeholder
     * like 2000-01-01), so record_status_id is the only reliable signal.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function current(Builder $query): void
    {
        $columns = self::$knownColumns[$this->getTable()] ??= Schema::getColumnListing($this->getTable());

        if (in_array('record_status_id', $columns, true)) {
            $query->where($query->qualifyColumn('record_status_id'), 1);
        }

        $query->orderBy($query->qualifyColumn('code'))->orderBy($query->qualifyColumn('id'));
    }

    /**
     * Id => name pairs for a dropdown.
     *
     * @return array<int, string>
     */
    public static function options(?string $parentColumn = null, ?int $parentId = null): array
    {
        if ($parentColumn && ! $parentId) {
            return [];
        }

        return static::query()
            ->current()
            ->when($parentColumn, fn (Builder $query) => $query->where($parentColumn, $parentId))
            ->get(['id', 'code', 'name_kh', 'name_en'])
            ->mapWithKeys(fn ($location) => [$location->id => $location->display_name])
            ->all();
    }
}
