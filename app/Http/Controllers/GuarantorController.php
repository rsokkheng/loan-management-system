<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuarantorRequest;
use App\Models\ActivityLog;
use App\Models\Guarantor;
use App\Models\Loan;
use Illuminate\Http\RedirectResponse;

class GuarantorController extends Controller
{
    public function store(GuarantorRequest $request, Loan $loan): RedirectResponse
    {
        if (! $loan->securityIsEditable()) {
            return back()->with('error', __('Guarantors can only be changed before the loan is disbursed.'));
        }

        $guarantor = $loan->guarantors()->create($request->validated() + ['created_by' => $request->user()->id]);

        ActivityLog::record('created', "Guarantor {$guarantor->full_name} added to loan {$loan->reference}", $loan);

        return back()->with('success', __('Guarantor added.'));
    }

    public function destroy(Guarantor $guarantor): RedirectResponse
    {
        $loan = Loan::query()->findOrFail($guarantor->loan_id);

        if (! $loan->securityIsEditable()) {
            return back()->with('error', __('Guarantors can only be changed before the loan is disbursed.'));
        }

        $guarantor->delete();

        ActivityLog::record('deleted', "Guarantor {$guarantor->full_name} removed from loan {$loan->reference}", $loan);

        return back()->with('success', __('Guarantor removed.'));
    }
}
