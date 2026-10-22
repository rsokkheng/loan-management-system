<x-layouts.admin :title="__('Branch Profitability')" :breadcrumbs="['Reports' => route('reports.index'), 'Branch Profitability']">
    <div class="card mb-4">
        <div class="card-header">@include('reports._range')</div>
        <div class="card-body">
            <div class="row text-center">
                @foreach ([
                    'Total Income' => [$totals['income'], ''],
                    'Total Expenses' => [$totals['expenses'], ''],
                    'Net Profit' => [$totals['net'], $totals['net'] < 0 ? 'text-danger' : 'text-success'],
                ] as $label => [$value, $class])
                    <div class="col">
                        <div class="stat-label">{{ __($label) }}</div>
                        <div class="fs-5 fw-semibold {{ $class }}">@money($value)</div>
                    </div>
                @endforeach
                <div class="col">
                    <div class="stat-label">{{ __('Profit margin') }}</div>
                    <div class="fs-5 fw-semibold">{{ $totals['income'] > 0 ? round($totals['net'] / $totals['income'] * 100, 1).'%' : '—' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h3 class="card-title">{{ __('Profit & Loss by Branch') }}</h3></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover table-nowrap align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Branch') }}</th>
                            <th class="text-end">{{ __('Interest') }}</th>
                            <th class="text-end">{{ __('Fees') }}</th>
                            <th class="text-end">{{ __('Penalty') }}</th>
                            <th class="text-end">{{ __('Recovery') }}</th>
                            <th class="text-end">{{ __('Other') }}</th>
                            <th class="text-end">{{ __('Total Income') }}</th>
                            <th class="text-end">{{ __('Loan Loss') }}</th>
                            <th class="text-end">{{ __('Operating') }}</th>
                            <th class="text-end">{{ __('Net Profit') }}</th>
                            <th class="text-end">{{ __('Margin') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td class="fw-semibold">{{ $row['name'] }}</td>
                                <td class="text-end">@money($row['interest'])</td>
                                <td class="text-end">@money($row['fees'])</td>
                                <td class="text-end">@money($row['penalty'])</td>
                                <td class="text-end">@money($row['recovery'])</td>
                                <td class="text-end">@money($row['other_income'])</td>
                                <td class="text-end fw-semibold">@money($row['income'])</td>
                                <td class="text-end text-danger">@money($row['loan_loss'])</td>
                                <td class="text-end">@money($row['operating'])</td>
                                <td class="text-end fw-bold {{ $row['net'] < 0 ? 'text-danger' : 'text-success' }}">@money($row['net'])</td>
                                <td class="text-end">{{ $row['margin'] !== null ? $row['margin'].'%' : '—' }}</td>
                            </tr>
                        @empty
                            <x-empty-state colspan="11" icon="building" :title="__('No branches')" />
                        @endforelse
                    </tbody>
                    @if ($rows->count() > 1)
                        <tfoot class="table-light fw-semibold">
                            <tr>
                                <td>{{ __('Total') }}</td>
                                @foreach (['interest', 'fees', 'penalty', 'recovery', 'other_income', 'income', 'loan_loss', 'operating', 'net'] as $key)
                                    <td class="text-end">@money($totals[$key])</td>
                                @endforeach
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h3 class="card-title">{{ __('Portfolio by Branch') }}</h3></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover table-nowrap align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Branch') }}</th>
                            <th class="text-center">{{ __('Active Loans') }}</th>
                            <th class="text-end">{{ __('Outstanding principal') }}</th>
                            <th class="text-end">{{ __('Disbursed in period') }}</th>
                            <th class="text-end">PAR 30</th>
                            <th class="text-end" title="{{ __('Net profit for the period ÷ outstanding principal') }}">{{ __('Return on portfolio') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td class="fw-semibold">{{ $row['name'] }}</td>
                                <td class="text-center">{{ $row['active_loans'] }}</td>
                                <td class="text-end">@money($row['outstanding'])</td>
                                <td class="text-end">@money($row['disbursed'])</td>
                                <td class="text-end {{ $row['par30'] >= 10 ? 'text-danger' : ($row['par30'] >= 5 ? 'text-warning' : 'text-success') }}">{{ number_format($row['par30'], 2) }}%</td>
                                <td class="text-end">{{ $row['return_on_portfolio'] !== null ? $row['return_on_portfolio'].'%' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <p class="small text-secondary">{{ __('Figures come from the general ledger for the selected period and reporting currency. Expenses posted without a branch appear under head office.') }}</p>
</x-layouts.admin>
