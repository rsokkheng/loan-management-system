<x-layouts.admin :title="__('Trial Balance')" :breadcrumbs="[__('Accounting'), __('Trial Balance')]">
    <div class="card mb-4">
        <div class="card-header no-print">
            <form method="GET" class="d-flex gap-2 align-items-end">
                <div>
                    <label class="form-label small mb-1">{{ __('As of') }}</label>
                    <input type="date" name="as_of" value="{{ $asOf->toDateString() }}" class="form-control form-control-sm">
                </div>
                <button class="btn btn-sm btn-secondary">{{ __('Apply') }}</button>
                <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></button>
            </form>
        </div>
        <div class="card-body">
            @include('accounting.reports._header', ['statementTitle' => __('Trial Balance'), 'period' => __('As of :date', ['date' => $asOf->translatedFormat('d F Y')])])

            <table class="table table-sm align-middle">
                <thead class="table-light"><tr><th style="width: 90px">{{ __('Code') }}</th><th>{{ __('Account') }}</th><th>{{ __('Type') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th></tr></thead>
                <tbody>
                    @forelse ($accounts as $account)
                        <tr>
                            <td><code>{{ $account->code }}</code></td>
                            <td><a href="{{ route('accounting.accounts.show', $account) }}" class="text-decoration-none">{{ $account->display_name }}</a></td>
                            <td>{{ $account->type->label() }}</td>
                            <td class="text-end">{{ $account->tb_debit > 0 ? number_format($account->tb_debit, 2) : '' }}</td>
                            <td class="text-end">{{ $account->tb_credit > 0 ? number_format($account->tb_credit, 2) : '' }}</td>
                        </tr>
                    @empty
                        <x-empty-state colspan="5" icon="journal" :title="__('No postings yet')" />
                    @endforelse
                </tbody>
                <tfoot class="fw-bold border-top border-2">
                    <tr>
                        <td colspan="3">{{ __('Total') }}</td>
                        <td class="text-end">@money($debit)</td>
                        <td class="text-end">@money($credit)</td>
                    </tr>
                </tfoot>
            </table>

            @if (round($debit - $credit, 2) == 0)
                <div class="text-success small"><i class="bi bi-check-circle me-1"></i> {{ __('Debits equal credits. The books are balanced.') }}</div>
            @else
                <div class="text-danger small"><i class="bi bi-x-circle me-1"></i> {{ __('The books are out of balance.') }}</div>
            @endif
        </div>
    </div>
</x-layouts.admin>
