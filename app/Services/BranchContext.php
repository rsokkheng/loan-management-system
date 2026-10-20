<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Decides which branch the current user is working in.
 *
 * Staff assigned to a branch are locked to it. Admins and unassigned
 * head-office staff may switch between branches or view all of them.
 */
class BranchContext
{
    public const SESSION_KEY = 'branch_id';

    /**
     * The branch to filter records by, or null for "all branches".
     */
    public function id(): ?int
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return null;
        }

        if (! $this->canSwitch($user)) {
            return $user->branch_id;
        }

        $selected = session(self::SESSION_KEY);

        return $selected ? (int) $selected : null;
    }

    public function canSwitch(?User $user = null): bool
    {
        $user ??= Auth::user();

        return $user instanceof User && ($user->isAdmin() || $user->branch_id === null);
    }

    public function switchTo(?int $branchId): void
    {
        session([self::SESSION_KEY => $branchId]);
    }

    /**
     * @var array<int, Branch|null>
     */
    private array $resolved = [];

    public function current(): ?Branch
    {
        $id = $this->id();

        if (! $id) {
            return null;
        }

        return $this->resolved[$id] ??= Branch::query()->find($id);
    }

    /**
     * Pick the branch a new record should be created in.
     */
    public function forNewRecord(?int $requestedBranchId = null): int
    {
        $branchId = $this->canSwitch() ? ($requestedBranchId ?: $this->id()) : $this->id();

        if (! $branchId) {
            throw ValidationException::withMessages(['branch_id' => __('Please select a branch.')]);
        }

        return $branchId;
    }
}
