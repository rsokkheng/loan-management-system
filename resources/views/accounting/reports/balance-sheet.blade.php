<x-layouts.admin :title="__('Balance Sheet')" :breadcrumbs="[__('Accounting'), __('Balance Sheet')]">
    <div class="card mb-4" style="max-width: 900px">
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
            @include('accounting.reports._header', ['statementTitle' => __('Balance Sheet'), 'period' => __('As of :date', ['date' => $asOf->translatedFormat('d F Y')])])

            <table class="table table-sm align-middle mb-0">
                <tbody>
                    <tr class="table-light"><th colspan="2">{{ __('Assets') }}</th></tr>
                    @foreach ($assets as $account)
                        <tr><td class="ps-4"><code>{{ $account->code }}</code> {{ $account->display_name }}</td><td class="text-end">@money($account->balance)</td></tr>
                    @endforeach
                    <tr class="fw-bold"><td>{{ __('Total Assets') }}</td><td class="text-end">@money($total_assets)</td></tr>

                    <tr class="table-light"><th colspan="2">{{ __('Liabilities') }}</th></tr>
                    @forelse ($liabilities as $account)
                        <tr><td class="ps-4"><code>{{ $account->code }}</code> {{ $account->display_name }}</td><td class="text-end">@money($account->balance)</td></tr>
                    @empty
                        <tr><td class="ps-4 text-secondary">—</td><td class="text-end">@money(0)</td></tr>
                    @endforelse
                    <tr class="fw-semibold"><td>{{ __('Total Liabilities') }}</td><td class="text-end">@money($total_liabilities)</td></tr>

                    <tr class="table-light"><th colspan="2">{{ __('Equity') }}</th></tr>
                    @foreach ($equity as $account)
                        <tr><td class="ps-4"><code>{{ $account->code }}</code> {{ $account->display_name }}</td><td class="text-end">@money($account->balance)</td></tr>
                    @endforeach
                    <tr><td class="ps-4">{{ __('Accumulated profit (loss)') }}</td><td class="text-end">@money($earnings)</td></tr>
                    <tr class="fw-semibold"><td>{{ __('Total Equity') }}</td><td class="text-end">@money($total_equity)</td></tr>
                </tbody>
                <tfoot>
                    <tr class="fw-bold border-top border-2">
                        <td>{{ __('Total Liabilities & Equity') }}</td>
                        <td class="text-end">@money($total_liabilities + $total_equity)</td>
                    </tr>
                </tfoot>
            </table>

            @if (round($total_assets - $total_liabilities - $total_equity, 2) == 0)
                <div class="text-success small mt-2"><i class="bi bi-check-circle me-1"></i> {{ __('Assets equal liabilities plus equity.') }}</div>
            @else
                <div class="text-danger small mt-2"><i class="bi bi-x-circle me-1"></i> {{ __('The balance sheet does not balance.') }}</div>
            @endif
        </div>
    </div>
</x-layouts.admin>
