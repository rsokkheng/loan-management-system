<?php

namespace App\Http\Controllers;

use App\Http\Requests\BranchRequest;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\User;
use App\Services\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(): View
    {
        $branches = Branch::query()
            ->with(['manager', ...Branch::ADDRESS_RELATIONS])
            ->withCount(['users', 'borrowers' => fn ($query) => $query->withoutGlobalScopes(), 'loans' => fn ($query) => $query->withoutGlobalScopes()])
            ->orderBy('name')
            ->get();

        return view('branches.index', compact('branches'));
    }

    public function create(): View
    {
        return view('branches.create', $this->formData(new Branch(['is_active' => true])));
    }

    public function store(BranchRequest $request): RedirectResponse
    {
        $branch = Branch::create($request->validated());

        ActivityLog::record('created', "Branch {$branch->name} created", $branch);

        return redirect()->route('branches.index')->with('success', __('Branch created.'));
    }

    public function edit(Branch $branch): View
    {
        return view('branches.edit', $this->formData($branch));
    }

    public function update(BranchRequest $request, Branch $branch): RedirectResponse
    {
        $branch->update($request->validated());

        ActivityLog::record('updated', "Branch {$branch->name} updated", $branch);

        return redirect()->route('branches.index')->with('success', __('Branch updated.'));
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $inUse = $branch->users()->exists()
            || $branch->borrowers()->withoutGlobalScopes()->exists()
            || $branch->loans()->withoutGlobalScopes()->exists();

        if ($inUse) {
            return back()->with('error', __('This branch has staff, borrowers or loans. Deactivate it instead.'));
        }

        $branch->delete();

        ActivityLog::record('deleted', "Branch {$branch->name} deleted");

        return redirect()->route('branches.index')->with('success', __('Branch deleted.'));
    }

    /**
     * Change the branch an admin or head-office user is working in.
     */
    public function switch(Request $request, BranchContext $context): RedirectResponse
    {
        abort_unless($context->canSwitch($request->user()), 403);

        $validated = $request->validate(['branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')]]);
        $context->switchTo($validated['branch_id'] ?? null);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Branch $branch): array
    {
        return [
            'branch' => $branch,
            'managers' => User::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ];
    }
}
