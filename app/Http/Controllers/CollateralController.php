<?php

namespace App\Http\Controllers;

use App\Enums\CollateralStatus;
use App\Enums\LoanStatus;
use App\Http\Requests\CollateralRequest;
use App\Models\ActivityLog;
use App\Models\Collateral;
use App\Models\Loan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CollateralController extends Controller
{
    public function store(CollateralRequest $request, Loan $loan): RedirectResponse
    {
        if (! $loan->securityIsEditable()) {
            return back()->with('error', __('Collateral can only be added before the loan is disbursed.'));
        }

        $data = collect($request->validated())->except('document')->all();

        if ($file = $request->file('document')) {
            $data['file_path'] = $file->store("collaterals/{$loan->id}", 'local');
            $data['original_name'] = $file->getClientOriginalName();
        }

        $collateral = $loan->collaterals()->create($data + [
            'status' => CollateralStatus::Held,
            'created_by' => $request->user()->id,
        ]);

        ActivityLog::record('created', "Collateral \"{$collateral->description}\" added to loan {$loan->reference}", $loan, [
            'estimated_value' => (float) $collateral->estimated_value,
        ]);

        return back()->with('success', __('Collateral added.'));
    }

    /**
     * Hand the collateral back to its owner once the loan no longer needs it.
     */
    public function release(Request $request, Collateral $collateral): RedirectResponse
    {
        $loan = $this->loanFor($collateral);

        if (! in_array($loan->status, [LoanStatus::Closed, LoanStatus::Rejected], true)) {
            return back()->with('error', __('Collateral can only be released after the loan is closed or rejected.'));
        }

        if ($collateral->status === CollateralStatus::Released) {
            return back()->with('error', __('This collateral has already been released.'));
        }

        $collateral->forceFill([
            'status' => CollateralStatus::Released,
            'released_at' => now(),
            'released_by' => $request->user()->id,
        ])->save();

        ActivityLog::record('updated', "Collateral \"{$collateral->description}\" released for loan {$loan->reference}", $loan);

        return back()->with('success', __('Collateral released to the owner.'));
    }

    public function document(Collateral $collateral): StreamedResponse
    {
        $this->loanFor($collateral);

        abort_unless($collateral->file_path, 404);

        return Storage::disk('local')->download($collateral->file_path, $collateral->original_name);
    }

    public function destroy(Collateral $collateral): RedirectResponse
    {
        $loan = $this->loanFor($collateral);

        if (! $loan->securityIsEditable()) {
            return back()->with('error', __('Collateral can only be removed before the loan is disbursed.'));
        }

        if ($collateral->file_path) {
            Storage::disk('local')->delete($collateral->file_path);
        }

        $collateral->delete();

        ActivityLog::record('deleted', "Collateral \"{$collateral->description}\" removed from loan {$loan->reference}", $loan);

        return back()->with('success', __('Collateral removed.'));
    }

    /**
     * Collateral isn't branch-scoped itself, so resolve it through its (scoped) loan.
     */
    private function loanFor(Collateral $collateral): Loan
    {
        return Loan::query()->findOrFail($collateral->loan_id);
    }
}
