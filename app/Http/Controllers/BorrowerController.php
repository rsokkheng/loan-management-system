<?php

namespace App\Http\Controllers;

use App\Enums\Gender;
use App\Http\Requests\BorrowerRequest;
use App\Models\ActivityLog;
use App\Models\Borrower;
use App\Models\Branch;
use App\Services\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BorrowerController extends Controller
{
    public function index(Request $request): View
    {
        $borrowers = Borrower::query()
            ->search($request->string('search')->trim()->value())
            ->when($request->input('status') === 'blacklisted', fn ($query) => $query->where('is_blacklisted', true))
            ->with(['branch', 'province', 'district'])
            ->withCount('loans')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('borrowers.index', compact('borrowers'));
    }

    public function create(): View
    {
        return view('borrowers.create', [
            'borrower' => new Borrower(['branch_id' => app(BranchContext::class)->id()]),
            'genders' => Gender::cases(),
            'branches' => Branch::query()->active()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(BorrowerRequest $request, BranchContext $context): RedirectResponse
    {
        $data = $request->safe()->except('photo');
        $data['branch_id'] = $context->forNewRecord($request->integer('branch_id') ?: null);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('borrowers', 'public');
        }

        $borrower = Borrower::create($data + ['created_by' => $request->user()->id]);

        ActivityLog::record('created', "Borrower {$borrower->full_name} registered", $borrower);

        return redirect()->route('borrowers.show', $borrower)->with('success', __('Borrower registered successfully.'));
    }

    public function show(Borrower $borrower): View
    {
        $borrower->load([
            ...Borrower::ADDRESS_RELATIONS,
            'branch',
            'creator',
            'documents.uploader',
            'loans' => fn ($query) => $query->with('product')->latest('id'),
        ]);

        return view('borrowers.show', compact('borrower'));
    }

    public function edit(Borrower $borrower): View
    {
        return view('borrowers.edit', ['borrower' => $borrower->load('branch'), 'genders' => Gender::cases()]);
    }

    public function update(BorrowerRequest $request, Borrower $borrower): RedirectResponse
    {
        // Loans and payments are booked to the borrower's branch, so it is fixed after registration.
        $data = $request->safe()->except(['photo', 'branch_id']);

        if ($request->hasFile('photo')) {
            if ($borrower->photo_path) {
                Storage::disk('public')->delete($borrower->photo_path);
            }

            $data['photo_path'] = $request->file('photo')->store('borrowers', 'public');
        }

        $borrower->update($data);

        ActivityLog::record('updated', "Borrower {$borrower->full_name} updated", $borrower);

        return redirect()->route('borrowers.show', $borrower)->with('success', __('Borrower updated successfully.'));
    }

    public function destroy(Borrower $borrower): RedirectResponse
    {
        Gate::authorize('delete-records');

        if ($borrower->hasOpenLoan()) {
            return back()->with('error', __('Borrowers with pending or active loans cannot be deleted.'));
        }

        $borrower->delete();

        ActivityLog::record('deleted', "Borrower {$borrower->full_name} deleted", $borrower);

        return redirect()->route('borrowers.index')->with('success', __('Borrower deleted.'));
    }
}
