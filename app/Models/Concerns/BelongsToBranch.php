<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use App\Services\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Restricts queries to the current user's branch (see BranchContext).
 * Route model binding goes through this scope too, so records from other
 * branches return 404 for branch-locked staff.
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope('branch', function (Builder $query): void {
            $branchId = app(BranchContext::class)->id();

            if ($branchId) {
                $query->where($query->qualifyColumn('branch_id'), $branchId);
            }
        });
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
