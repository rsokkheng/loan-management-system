<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\AccountingReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountingReportController extends Controller
{
    public function __construct(private AccountingReportService $reports) {}

    public function trialBalance(Request $request): View
    {
        $asOf = $request->date('as_of') ?? today();

        return view('accounting.reports.trial-balance', ['asOf' => $asOf] + $this->reports->trialBalance($asOf));
    }

    public function incomeStatement(Request $request): View
    {
        $from = $request->date('from') ?? now()->startOfYear();
        $to = $request->date('to') ?? today();

        return view('accounting.reports.income-statement', ['from' => $from, 'to' => $to] + $this->reports->incomeStatement($from, $to));
    }

    public function balanceSheet(Request $request): View
    {
        $asOf = $request->date('as_of') ?? today();

        return view('accounting.reports.balance-sheet', ['asOf' => $asOf] + $this->reports->balanceSheet($asOf));
    }
}
