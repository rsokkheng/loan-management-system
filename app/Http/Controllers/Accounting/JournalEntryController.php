<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\Currency;
use App\Enums\JournalType;
use App\Http\Controllers\Controller;
use App\Http\Requests\JournalEntryRequest;
use App\Models\Account;
use App\Models\Branch;
use App\Models\JournalEntry;
use App\Services\BranchContext;
use App\Services\CurrencyContext;
use App\Services\LedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class JournalEntryController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->value();

        $entries = JournalEntry::query()
            ->with(['branch', 'creator'])
            ->withSum('lines', 'debit')
            ->where('currency', app(CurrencyContext::class)->current())
            ->when(JournalType::tryFrom((string) $request->input('type')), fn ($query, $type) => $query->where('type', $type))
            ->when($search, fn ($query) => $query->where(fn ($query) => $query
                ->where('reference', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")))
            ->when($request->date('from'), fn ($query, $from) => $query->whereDate('entry_date', '>=', $from))
            ->when($request->date('to'), fn ($query, $to) => $query->whereDate('entry_date', '<=', $to))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('accounting.journal.index', ['entries' => $entries, 'types' => JournalType::cases()]);
    }

    public function create(): View
    {
        return view('accounting.journal.create', [
            'accounts' => Account::query()->active()->orderBy('code')->get(),
            'branches' => Branch::query()->active()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(JournalEntryRequest $request, LedgerService $ledger, BranchContext $context): RedirectResponse
    {
        $entry = $ledger->post(
            type: JournalType::Manual,
            date: Carbon::parse($request->validated('entry_date')),
            description: $request->validated('description'),
            lines: collect($request->validated('lines'))->map(fn (array $line) => [
                'account' => (int) $line['account_id'],
                'debit' => $line['debit'] ?? 0,
                'credit' => $line['credit'] ?? 0,
                'memo' => $line['memo'] ?? null,
            ])->all(),
            branchId: $context->forNewRecord($request->integer('branch_id') ?: null),
            user: $request->user(),
            currency: Currency::tryFrom((string) $request->validated('currency')) ?? app(CurrencyContext::class)->current(),
        );

        return redirect()->route('accounting.journal.show', $entry)->with('success', __('Journal entry :reference posted.', ['reference' => $entry->reference]));
    }

    public function show(JournalEntry $journalEntry): View
    {
        $journalEntry->load(['lines.account', 'branch', 'creator', 'source', 'reversalOf', 'reversedBy']);

        return view('accounting.journal.show', ['entry' => $journalEntry]);
    }

    public function reverse(Request $request, JournalEntry $journalEntry, LedgerService $ledger): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:200']]);

        $journalEntry->load('lines');
        $reversal = $ledger->reverse($journalEntry, $request->user(), $validated['reason']);

        return redirect()->route('accounting.journal.show', $reversal)->with('success', __('Entry reversed.'));
    }
}
