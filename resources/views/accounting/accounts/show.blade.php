<x-layouts.admin :title="$account->code.' · '.$account->display_name" :breadcrumbs="[__('Chart of Accounts') => route('accounting.accounts.index'), __('Ledger')]">
    <div class="card mb-4">
        <div class="card-header">
            <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
                <div>
                    <label class="form-label small mb-1">{{ __('From') }}</label>
                    <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm">
                </div>
                <div>
                    <label class="form-label small mb-1">{{ __('To') }}</label>
                    <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm">
                </div>
                <button class="btn btn-sm btn-secondary"><i class="bi bi-funnel me-1"></i> {{ __('Apply') }}</button>
                <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></button>
                <div class="ms-auto text-end">
                    <x-badge :status="$account->type" />
                    <div class="small text-secondary">{{ $account->type->isDebitNormal() ? __('Normal balance: debit') : __('Normal balance: credit') }}</div>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover table-nowrap align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>{{ __('Date') }}</th><th>{{ __('Reference') }}</th><th>{{ __('Description') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th><th class="text-end">{{ __('Balance') }}</th></tr>
                    </thead>
                    <tbody>
                        <tr class="table-secondary">
                            <td colspan="5" class="fw-semibold">{{ __('Opening balance') }}</td>
                            <td class="text-end fw-semibold">@money($opening)</td>
                        </tr>
                        @forelse ($lines as $line)
                            <tr>
                                <td>{{ $line->entry->entry_date->translatedFormat('d M Y') }}</td>
                                <td><a href="{{ route('accounting.journal.show', $line->entry) }}">{{ $line->entry->reference }}</a></td>
                                <td class="text-wrap">{{ $line->entry->description }}@if ($line->memo) <span class="text-secondary small">· {{ $line->memo }}</span>@endif</td>
                                <td class="text-end">{{ (float) $line->debit > 0 ? number_format((float) $line->debit, 2) : '' }}</td>
                                <td class="text-end">{{ (float) $line->credit > 0 ? number_format((float) $line->credit, 2) : '' }}</td>
                                <td class="text-end fw-semibold">@money($line->running_balance)</td>
                            </tr>
                        @empty
                            <x-empty-state colspan="6" icon="journal" :title="__('No transactions in this period')" />
                        @endforelse
                    </tbody>
                    <tfoot class="table-light fw-semibold">
                        <tr>
                            <td colspan="3">{{ __('Closing balance') }}</td>
                            <td class="text-end">{{ number_format($lines->sum('debit'), 2) }}</td>
                            <td class="text-end">{{ number_format($lines->sum('credit'), 2) }}</td>
                            <td class="text-end">@money($closing)</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</x-layouts.admin>
