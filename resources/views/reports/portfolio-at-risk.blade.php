@php
    $parColor = fn (float $percent) => $percent >= 10 ? 'danger' : ($percent >= 5 ? 'warning' : 'success');
@endphp

<x-layouts.admin :title="__('Portfolio at Risk')" :breadcrumbs="['Reports' => route('reports.index'), 'Portfolio at Risk']">
    <div class="row">
        <div class="col-6 col-lg">
            <div class="card mb-4 border-start border-4 border-primary">
                <div class="card-body py-3">
                    <div class="stat-label">{{ __('Outstanding principal') }}</div>
                    <div class="fs-5 fw-semibold">@money($summary['outstanding'])</div>
                    <div class="small text-secondary">{{ __(':count loan(s)', ['count' => $summary['loans']]) }}</div>
                </div>
            </div>
        </div>
        @foreach ($summary['par'] as $days => $par)
            <div class="col-6 col-lg">
                <div class="card mb-4 border-start border-4 border-{{ $parColor($par['percent']) }}">
                    <div class="card-body py-3">
                        <div class="stat-label">PAR {{ $days }}</div>
                        <div class="fs-5 fw-semibold text-{{ $parColor($par['percent']) }}">{{ number_format($par['percent'], 2) }}%</div>
                        <div class="small text-secondary">@money($par['principal']) · {{ __(':count loan(s)', ['count' => $par['loans']]) }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <p class="small text-secondary">{{ __('PAR 30 is the outstanding principal of loans more than 30 days late, as a share of all outstanding principal. Below 5% is healthy; above 10% needs action.') }}</p>

    @foreach ([['By Branch', 'Branch', $byBranch], ['By Loan Officer', 'Loan officer', $byOfficer]] as [$title, $column, $groups])
        <div class="card mb-4">
            <div class="card-header"><h3 class="card-title">{{ __($title) }}</h3></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-nowrap align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __($column) }}</th>
                                <th class="text-center">{{ __('Active Loans') }}</th>
                                <th class="text-end">{{ __('Outstanding principal') }}</th>
                                @foreach (array_keys($summary['par']) as $days)
                                    <th class="text-end">PAR {{ $days }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($groups as $group)
                                <tr>
                                    <td class="fw-semibold">{{ $group['name'] }}</td>
                                    <td class="text-center">{{ $group['loans'] }}</td>
                                    <td class="text-end">@money($group['outstanding'])</td>
                                    @foreach ($group['par'] as $par)
                                        <td class="text-end text-{{ $parColor($par['percent']) }}">{{ number_format($par['percent'], 2) }}%</td>
                                    @endforeach
                                </tr>
                            @empty
                                <x-empty-state colspan="7" icon="inbox" :title="__('No active loans')" />
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endforeach

    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">{{ __('Loans more than 30 days late') }}</h3>
            <div class="card-tools">
                <a href="{{ route('reports.portfolio-at-risk', ['export' => 'csv']) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-filetype-csv me-1"></i> {{ __('Export CSV') }}</a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover table-nowrap align-middle mb-0">
                    <thead class="table-light"><tr><th>{{ __('Loan') }}</th><th>{{ __('Borrower') }}</th><th>{{ __('Branch') }}</th><th>{{ __('Loan officer') }}</th><th class="text-center">{{ __('Days') }}</th><th class="text-end">{{ __('Outstanding principal') }}</th></tr></thead>
                    <tbody>
                        @forelse ($atRiskLoans as $row)
                            <tr>
                                <td><a href="{{ route('loans.show', $row['loan']) }}">{{ $row['loan']->reference }}</a></td>
                                <td>{{ $row['loan']->borrower->full_name }}</td>
                                <td>{{ $row['loan']->branch?->name ?? '—' }}</td>
                                <td>{{ $row['loan']->officer?->name ?? '—' }}</td>
                                <td class="text-center"><span class="badge {{ $row['days_late'] > 90 ? 'text-bg-dark' : 'text-bg-danger' }}">{{ $row['days_late'] }}</span></td>
                                <td class="text-end fw-semibold">@money($row['principal'])</td>
                            </tr>
                        @empty
                            <x-empty-state colspan="6" icon="emoji-smile" :title="__('No loans more than 30 days late')" />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.admin>
