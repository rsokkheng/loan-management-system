<?php

namespace App\Http\Controllers;

use App\Http\Requests\FamilyMemberRequest;
use App\Models\ActivityLog;
use App\Models\Borrower;
use App\Models\FamilyMember;
use Illuminate\Http\RedirectResponse;

class FamilyMemberController extends Controller
{
    public function store(FamilyMemberRequest $request, Borrower $borrower): RedirectResponse
    {
        $member = $borrower->familyMembers()->create($request->validated());

        ActivityLog::record('created', "Family member {$member->full_name} added to borrower {$borrower->code}", $borrower);

        return back()->with('success', __('Family member added.'));
    }

    public function destroy(FamilyMember $familyMember): RedirectResponse
    {
        $borrower = Borrower::query()->findOrFail($familyMember->borrower_id);

        $familyMember->delete();

        ActivityLog::record('deleted', "Family member {$familyMember->full_name} removed from borrower {$borrower->code}", $borrower);

        return back()->with('success', __('Family member removed.'));
    }
}
