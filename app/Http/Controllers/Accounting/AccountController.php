<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccountRequest;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Services\AccountingReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    /**
     * Chart of accounts with current balances.
     */
    public function index(AccountingReportService $reports): View
    {
        Account::installDefaults();

        $accounts = $reports->accountsWithBalances(null, today())->groupBy(fn (Account $account) => $account->type->value);

        return view('accounting.accounts.index', [
            'groups' => collect(AccountType::cases())->mapWithKeys(fn (AccountType $type) => [$type->value => $accounts->get($type->value, collect())]),
        ]);
    }

    public function create(): View
    {
        return view('accounting.accounts.create', $this->formData(new Account(['is_active' => true, 'type' => AccountType::Expense])));
    }

    public function store(AccountRequest $request): RedirectResponse
    {
        $account = Account::create($request->validated());

        ActivityLog::record('created', "Account {$account->code} {$account->name} created", $account);

        return redirect()->route('accounting.accounts.index')->with('success', __('Account created.'));
    }

    /**
     * Account ledger (statement) for a date range.
     */
    public function show(Request $request, Account $account, AccountingReportService $reports): View
    {
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? today();

        return view('accounting.accounts.show', ['account' => $account, 'from' => $from, 'to' => $to] + $reports->ledger($account, $from, $to));
    }

    public function edit(Account $account): View
    {
        return view('accounting.accounts.edit', $this->formData($account));
    }

    public function update(AccountRequest $request, Account $account): RedirectResponse
    {
        $data = $request->validated();

        // Automatic postings depend on these, so system accounts keep their type and flags.
        if ($account->isSystem()) {
            $data = array_intersect_key($data, array_flip(['code', 'name', 'name_km', 'description', 'parent_id']));
        }

        $account->update($data);

        ActivityLog::record('updated', "Account {$account->code} {$account->name} updated", $account);

        return redirect()->route('accounting.accounts.index')->with('success', __('Account updated.'));
    }

    public function destroy(Account $account): RedirectResponse
    {
        if ($account->isSystem() || $account->lines()->exists()) {
            return back()->with('error', __('System accounts and accounts with transactions cannot be deleted. Deactivate it instead.'));
        }

        $account->delete();

        ActivityLog::record('deleted', "Account {$account->code} {$account->name} deleted");

        return redirect()->route('accounting.accounts.index')->with('success', __('Account deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Account $account): array
    {
        return [
            'account' => $account,
            'types' => AccountType::cases(),
            'parents' => Account::query()->whereKeyNot($account->id)->orderBy('code')->get(['id', 'code', 'name', 'name_km']),
        ];
    }
}
