<x-layouts.admin :title="__('Income Statement')" :breadcrumbs="[__('Accounting'), __('Income Statement')]">
    <div class="card mb-4" style="max-width: 900px">
        <div class="card-header no-print">
            <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
                <div>
                    <label class="form-label small mb-1">{{ __('From') }}</label>
                    <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm">
                </div>
                <div>
                    <label class="form-label small mb-1">{{ __('To') }}</label>
                    <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm">
                </div>
                <button class="btn btn-sm btn-secondary">{{ __('Apply') }}</button>
                <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></button>
            </form>
        </div>
        <div class="card-body">
            @include('accounting.reports._header', ['statementTitle' => __('Income Statement'), 'period' => $from->translatedFormat('d M Y').' – '.$to->translatedFormat('d M Y')])

            <table class="table table-sm align-middle mb-0">
                <tbody>
                    <tr class="table-light"><th colspan="2">{{ __('Income') }}</th></tr>
                    @foreach ($income as $account)
                        <tr>
                            <td class="ps-4"><code>{{ $account->code }}</code> {{ $account->display_name }}</td>
                            <td class="text-end">@money($account->balance)</td>
                        </tr>
                    @endforeach
                    <tr class="fw-semibold"><td>{{ __('Total Income') }}</td><td class="text-end">@money($total_income)</td></tr>

                    <tr class="table-light"><th colspan="2">{{ __('Expenses') }}</th></tr>
                    @foreach ($expenses as $account)
                        <tr>
                            <td class="ps-4"><code>{{ $account->code }}</code> {{ $account->display_name }}</td>
                            <td class="text-end">@money($account->balance)</td>
                        </tr>
                    @endforeach
                    <tr class="fw-semibold"><td>{{ __('Total Expenses') }}</td><td class="text-end">@money($total_expenses)</td></tr>
                </tbody>
                <tfoot>
                    <tr class="fs-5 fw-bold border-top border-2 {{ $net_income >= 0 ? 'text-success' : 'text-danger' }}">
                        <td>{{ $net_income >= 0 ? __('Net Profit') : __('Net Loss') }}</td>
                        <td class="text-end">@money($net_income)</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</x-layouts.admin>
