<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\JournalLine;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Builds financial statements from journal lines, respecting the current branch.
 */
class AccountingReportService
{
    public function __construct(private BranchContext $branches) {}

    /**
     * Sum of debits and credits per account for entries in the date range.
     *
     * @return Collection<int, object{account_id: int, debit: float, credit: float}>
     */
    public function totalsByAccount(?CarbonInterface $from, CarbonInterface $to): Collection
    {
        $branchId = $this->branches->id();

        return JournalLine::query()->toBase()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->when($branchId, fn ($query) => $query->where('journal_entries.branch_id', $branchId))
            ->when($from, fn ($query) => $query->whereDate('journal_entries.entry_date', '>=', $from))
            ->whereDate('journal_entries.entry_date', '<=', $to)
            ->groupBy('journal_lines.account_id')
            ->selectRaw('journal_lines.account_id, SUM(journal_lines.debit) as debit, SUM(journal_lines.credit) as credit')
            ->get()
            ->map(fn (object $row) => (object) ['account_id' => (int) $row->account_id, 'debit' => (float) $row->debit, 'credit' => (float) $row->credit])
            ->keyBy('account_id');
    }

    /**
     * Every account with its natural balance, optionally limited to some types.
     *
     * @return Collection<int, Account>
     */
    public function accountsWithBalances(?CarbonInterface $from, CarbonInterface $to, AccountType ...$types): Collection
    {
        $totals = $this->totalsByAccount($from, $to);

        return Account::query()
            ->when($types, fn ($query) => $query->ofType(...$types))
            ->orderBy('code')
            ->get()
            ->each(function (Account $account) use ($totals): void {
                $row = $totals->get($account->id);
                $account->setAttribute('total_debit', $row->debit ?? 0.0);
                $account->setAttribute('total_credit', $row->credit ?? 0.0);
                $account->setAttribute('balance', $account->type->balanceFrom(($row->debit ?? 0) - ($row->credit ?? 0)));
            });
    }

    /**
     * @return array{accounts: Collection<int, Account>, debit: float, credit: float}
     */
    public function trialBalance(CarbonInterface $asOf): array
    {
        $accounts = $this->accountsWithBalances(null, $asOf)
            ->filter(fn (Account $account) => round($account->total_debit - $account->total_credit, 2) != 0)
            ->each(function (Account $account): void {
                $net = round($account->total_debit - $account->total_credit, 2);
                $account->setAttribute('tb_debit', max($net, 0));
                $account->setAttribute('tb_credit', max(-$net, 0));
            })
            ->values();

        return [
            'accounts' => $accounts,
            'debit' => round($accounts->sum('tb_debit'), 2),
            'credit' => round($accounts->sum('tb_credit'), 2),
        ];
    }

    /**
     * @return array{income: Collection<int, Account>, expenses: Collection<int, Account>, total_income: float, total_expenses: float, net_income: float}
     */
    public function incomeStatement(CarbonInterface $from, CarbonInterface $to): array
    {
        $accounts = $this->accountsWithBalances($from, $to, AccountType::Income, AccountType::Expense);
        $income = $accounts->where('type', AccountType::Income)->values();
        $expenses = $accounts->where('type', AccountType::Expense)->values();

        $totalIncome = round($income->sum('balance'), 2);
        $totalExpenses = round($expenses->sum('balance'), 2);

        return [
            'income' => $income,
            'expenses' => $expenses,
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'net_income' => round($totalIncome - $totalExpenses, 2),
        ];
    }

    /**
     * Assets = Liabilities + Equity, where equity includes all profit earned to date.
     *
     * @return array{assets: Collection<int, Account>, liabilities: Collection<int, Account>, equity: Collection<int, Account>, earnings: float, total_assets: float, total_liabilities: float, total_equity: float}
     */
    public function balanceSheet(CarbonInterface $asOf): array
    {
        $accounts = $this->accountsWithBalances(null, $asOf);
        $pick = fn (AccountType $type) => $accounts->where('type', $type)->filter(fn (Account $account) => $account->balance != 0)->values();

        $earnings = round(
            $accounts->where('type', AccountType::Income)->sum('balance') - $accounts->where('type', AccountType::Expense)->sum('balance'),
            2,
        );

        $equity = $pick(AccountType::Equity);

        return [
            'assets' => $pick(AccountType::Asset),
            'liabilities' => $pick(AccountType::Liability),
            'equity' => $equity,
            'earnings' => $earnings,
            'total_assets' => round($pick(AccountType::Asset)->sum('balance'), 2),
            'total_liabilities' => round($pick(AccountType::Liability)->sum('balance'), 2),
            'total_equity' => round($equity->sum('balance') + $earnings, 2),
        ];
    }

    /**
     * Account statement with an opening balance and a running balance per line.
     *
     * @return array{opening: float, lines: Collection<int, JournalLine>, closing: float}
     */
    public function ledger(Account $account, CarbonInterface $from, CarbonInterface $to): array
    {
        $before = $this->totalsByAccount(null, $from->copy()->subDay())->get($account->id);
        $opening = $account->type->balanceFrom(($before->debit ?? 0) - ($before->credit ?? 0));

        $lines = JournalLine::query()
            ->with('entry')
            ->where('account_id', $account->id)
            ->whereHas('entry', fn ($query) => $query
                ->whereDate('entry_date', '>=', $from)
                ->whereDate('entry_date', '<=', $to))
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entries.id')
            ->orderBy('journal_lines.id')
            ->select('journal_lines.*')
            ->get();

        $running = $opening;
        foreach ($lines as $line) {
            $running = round($running + $account->type->balanceFrom((float) $line->debit - (float) $line->credit), 2);
            $line->setAttribute('running_balance', $running);
        }

        return ['opening' => $opening, 'lines' => $lines, 'closing' => $running];
    }
}
