<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\AccountType;
use App\Enums\Currency;
use App\Enums\JournalType;
use App\Http\Controllers\Controller;
use App\Http\Requests\CashTransactionRequest;
use App\Models\Account;
use App\Models\Branch;
use App\Services\BranchContext;
use App\Services\CurrencyContext;
use App\Services\LedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Simple expense / income form for staff who don't write journal entries by hand.
 */
class CashTransactionController extends Controller
{
    public function create(Request $request): View
    {
        $type = $request->input('type') === 'income' ? 'income' : 'expense';

        return view('accounting.transactions.create', [
            'type' => $type,
            'categories' => Account::query()->active()->ofType($type === 'income' ? AccountType::Income : AccountType::Expense)
                ->whereNull('system_key')->orderBy('code')->get(),
            'cashAccounts' => Account::query()->active()->where('is_cash', true)->orderBy('code')->get(),
            'branches' => Branch::query()->active()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(CashTransactionRequest $request, LedgerService $ledger, BranchContext $context): RedirectResponse
    {
        $data = $request->validated();
        $isIncome = $data['type'] === 'income';
        $amount = (float) $data['amount'];

        $entry = $ledger->post(
            type: $isIncome ? JournalType::Income : JournalType::Expense,
            date: Carbon::parse($data['entry_date']),
            description: $data['description'],
            lines: [
                ['account' => (int) $data['account_id'], 'debit' => $isIncome ? 0 : $amount, 'credit' => $isIncome ? $amount : 0],
                ['account' => (int) $data['cash_account_id'], 'debit' => $isIncome ? $amount : 0, 'credit' => $isIncome ? 0 : $amount],
            ],
            branchId: $context->forNewRecord($request->integer('branch_id') ?: null),
            user: $request->user(),
            currency: Currency::tryFrom((string) $request->validated('currency')) ?? app(CurrencyContext::class)->current(),
        );

        return redirect()->route('accounting.journal.show', $entry)
            ->with('success', $isIncome ? __('Income recorded.') : __('Expense recorded.'));
    }
}
