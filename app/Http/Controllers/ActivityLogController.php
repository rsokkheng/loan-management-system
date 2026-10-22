<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $logs = ActivityLog::query()
            ->with('user')
            ->when($request->string('action')->value(), fn ($query, string $action) => $query->where('action', $action))
            ->when($request->integer('user'), fn ($query, int $userId) => $query->where('user_id', $userId))
            ->when($request->integer('loan'), fn ($query, int $loanId) => $query->where(fn (Builder $query) => $query
                ->whereMorphedTo('subject', Loan::class)->where('subject_id', $loanId)
                ->orWhere(fn (Builder $query) => $query
                    ->where('subject_type', (new Payment)->getMorphClass())
                    ->whereIn('subject_id', Payment::query()->withoutGlobalScopes()->where('loan_id', $loanId)->select('id')))))
            ->when($request->integer('borrower'), fn ($query, int $borrowerId) => $query
                ->where('subject_type', (new Borrower)->getMorphClass())->where('subject_id', $borrowerId))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('activity-logs.index', [
            'logs' => $logs,
            'actions' => ActivityLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
