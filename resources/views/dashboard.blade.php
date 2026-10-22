<x-layouts.admin :title="__('Dashboard')" :breadcrumbs="['Dashboard']">
    {{-- KPI boxes --}}
    <div class="row">
        @foreach ([
            ['label' => 'Outstanding Portfolio', 'amount' => $stats['outstanding'], 'icon' => 'wallet2', 'color' => 'primary', 'link' => route('loans.index', ['status' => 'active']), 'note' => __(':count active loans', ['count' => $stats['active_loans']])],
            ['label' => 'Collected This Month', 'amount' => $stats['collected_this_month'], 'icon' => 'cash-coin', 'color' => 'success', 'link' => route('payments.index', ['from' => now()->startOfMonth()->toDateString()]), 'note' => __('Repayments received')],
            ['label' => 'Overdue Amount', 'amount' => $stats['overdue_amount'], 'icon' => 'exclamation-triangle', 'color' => 'danger', 'link' => route('loans.index', ['status' => 'overdue']), 'note' => __(':count loans in arrears', ['count' => $stats['overdue_loans']])],
            ['label' => 'Total Disbursed', 'amount' => $stats['total_disbursed'], 'icon' => 'bank', 'color' => 'warning', 'link' => route('loans.index'), 'note' => __('All time')],
        ] as $box)
            <div class="col-lg-3 col-6">
                <div class="small-box text-bg-{{ $box['color'] }}">
                    <div class="inner">
                        <h3>@money($box['amount'])</h3>
                        <p class="mb-0">{{ __($box['label']) }}</p>
                    </div>
                    <i class="small-box-icon bi bi-{{ $box['icon'] }}"></i>
                    <a href="{{ $box['link'] }}" class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover">
                        {{ $box['note'] }} <i class="bi bi-arrow-right-circle"></i>
                    </a>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        @foreach ([
            ['Borrowers', $stats['borrowers'], 'people', 'info', route('borrowers.index')],
            ['Active Loans', $stats['active_loans'], 'graph-up-arrow', 'primary', route('loans.index', ['status' => 'active'])],
            ['Awaiting Approval / Disbursement', $stats['pending_loans'], 'hourglass-split', 'warning', route('loans.index', ['status' => 'pending'])],
            ['PAR 30', $stats['portfolio_at_risk'].'%', 'shield-exclamation', 'danger', auth()->user()->can('view-reports') ? route('reports.portfolio-at-risk') : route('loans.index', ['status' => 'overdue'])],
        ] as [$label, $value, $icon, $color, $link])
            <div class="col-md-3 col-sm-6">
                <a href="{{ $link }}" class="text-decoration-none text-reset">
                    <div class="info-box">
                        <span class="info-box-icon text-bg-{{ $color }} shadow-sm"><i class="bi bi-{{ $icon }}"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">{{ __($label) }}</span>
                            <span class="info-box-number fs-5">{{ $value }}</span>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    @if ($branchSummary && $branchSummary->count() > 1)
        <div class="card mb-4">
            <div class="card-header"><h3 class="card-title"><i class="bi bi-building me-1"></i> {{ __('Branch Performance') }}</h3></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-nowrap align-middle mb-0">
                        <thead><tr><th>{{ __('Branch') }}</th><th class="text-center">{{ __('Active Loans') }}</th><th class="text-end">{{ __('Total Disbursed') }}</th><th class="text-end">{{ __('Outstanding') }}</th><th style="width: 30%">{{ __('Share of portfolio') }}</th><th></th></tr></thead>
                        <tbody>
                            @php $portfolio = max(1, $branchSummary->sum('outstanding')); @endphp
                            @foreach ($branchSummary as $branch)
                                @php $share = round((float) $branch->outstanding / $portfolio * 100); @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $branch->name }}</td>
                                    <td class="text-center">{{ $branch->active_loans }}</td>
                                    <td class="text-end">@money($branch->disbursed ?? 0)</td>
                                    <td class="text-end fw-semibold">@money($branch->outstanding ?? 0)</td>
                                    <td>
                                        <div class="progress" style="height: 8px"><div class="progress-bar" style="width: {{ $share }}%"></div></div>
                                        <div class="small text-secondary">{{ $share }}%</div>
                                    </td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('branches.switch') }}">
                                            @csrf
                                            <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                                            <button class="btn btn-sm btn-outline-secondary">{{ __('Open') }} <i class="bi bi-arrow-right"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- Charts --}}
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">{{ __('Disbursements vs Collections') }} <small class="text-secondary">· {{ __('last 12 months') }}</small></h3>
                </div>
                <div class="card-body">
                    <div style="height: 300px"><canvas id="trendChart"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title">{{ __('Loans by Status') }}</h3></div>
                <div class="card-body">
                    @if ($statusBreakdown->isEmpty())
                        <x-empty-state icon="pie-chart" :title="__('No loans yet')" />
                    @else
                        <div style="height: 300px"><canvas id="statusChart"></canvas></div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- Overdue --}}
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title"><i class="bi bi-exclamation-octagon text-danger me-1"></i> {{ __('Overdue Installments') }}</h3>
                    <div class="card-tools">
                        <a href="{{ route('loans.index', ['status' => 'overdue']) }}" class="btn btn-sm btn-outline-secondary">{{ __('View all') }}</a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-nowrap align-middle mb-0">
                            <thead><tr><th>{{ __('Loan') }}</th><th>{{ __('Borrower') }}</th><th>{{ __('Due') }}</th><th class="text-end">{{ __('Balance') }}</th></tr></thead>
                            <tbody>
                                @forelse ($overdueList as $installment)
                                    <tr>
                                        <td><a href="{{ route('loans.show', $installment->loan) }}">{{ $installment->loan->reference }}</a></td>
                                        <td>{{ $installment->loan->borrower->full_name }}</td>
                                        <td>
                                            {{ $installment->due_date->translatedFormat('d M Y') }}
                                            <span class="badge text-bg-danger ms-1">{{ $installment->days_overdue }}d</span>
                                        </td>
                                        <td class="text-end fw-semibold">@money($installment->balance)</td>
                                    </tr>
                                @empty
                                    <x-empty-state colspan="4" icon="emoji-smile" :title="__('No overdue installments')" />
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Upcoming --}}
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title"><i class="bi bi-calendar-week text-primary me-1"></i> {{ __('Due in the Next 7 Days') }}</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-nowrap align-middle mb-0">
                            <thead><tr><th>{{ __('Loan') }}</th><th>{{ __('Borrower') }}</th><th>{{ __('Due') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
                            <tbody>
                                @forelse ($upcomingInstallments as $installment)
                                    <tr>
                                        <td><a href="{{ route('loans.show', $installment->loan) }}">{{ $installment->loan->reference }}</a></td>
                                        <td>{{ $installment->loan->borrower->full_name }}</td>
                                        <td>{{ $installment->due_date->isToday() ? 'Today' : $installment->due_date->translatedFormat('D, d M') }}</td>
                                        <td class="text-end fw-semibold">@money($installment->balance)</td>
                                    </tr>
                                @empty
                                    <x-empty-state colspan="4" icon="calendar-check" :title="__('Nothing due this week')" />
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent payments --}}
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title"><i class="bi bi-receipt me-1"></i> {{ __('Recent Payments') }}</h3>
            <div class="card-tools">
                <a href="{{ route('payments.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('All payments') }}</a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-nowrap align-middle mb-0">
                    <thead><tr><th>{{ __('Receipt') }}</th><th>{{ __('Date') }}</th><th>{{ __('Loan') }}</th><th>{{ __('Borrower') }}</th><th>{{ __('Method') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
                    <tbody>
                        @forelse ($recentPayments as $payment)
                            <tr>
                                <td><a href="{{ route('payments.show', $payment) }}">{{ $payment->receipt_number }}</a></td>
                                <td>{{ $payment->payment_date->translatedFormat('d M Y') }}</td>
                                <td>{{ $payment->loan->reference }}</td>
                                <td>{{ $payment->loan->borrower->full_name }}</td>
                                <td>{{ $payment->method->label() }}</td>
                                <td class="text-end fw-semibold text-success">@money($payment->amount)</td>
                            </tr>
                        @empty
                            <x-empty-state colspan="6" icon="receipt" :title="__('No payments recorded yet')" />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const money = (v) => @js($reportingCurrency->format(0)).replace('0', Number(v).toLocaleString(undefined, { maximumFractionDigits: 0 }));
                const trend = @js($chart);

                new Chart(document.getElementById('trendChart'), {
                    type: 'bar',
                    data: {
                        labels: trend.labels,
                        datasets: [
                            { label: @js(__('Disbursed')), data: trend.disbursed, backgroundColor: 'rgba(13,110,253,.75)', borderRadius: 4 },
                            { label: @js(__('Collected')), data: trend.collected, type: 'line', borderColor: '#198754', backgroundColor: 'rgba(25,135,84,.15)', fill: true, tension: .35, pointRadius: 3 },
                        ],
                    },
                    options: {
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: { tooltip: { callbacks: { label: (c) => `${c.dataset.label}: ${money(c.parsed.y)}` } } },
                        scales: { y: { beginAtZero: true, ticks: { callback: money } } },
                    },
                });

                const statusCanvas = document.getElementById('statusChart');
                if (statusCanvas) {
                    const statuses = @js($statusBreakdown);
                    const labels = @js(collect(\App\Enums\LoanStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]));
                    const colors = { pending: '#ffc107', approved: '#0dcaf0', rejected: '#6c757d', active: '#0d6efd', closed: '#198754', written_off: '#dc3545' };
                    new Chart(statusCanvas, {
                        type: 'doughnut',
                        data: {
                            labels: Object.keys(statuses).map((k) => labels[k] ?? k),
                            datasets: [{ data: Object.values(statuses), backgroundColor: Object.keys(statuses).map((k) => colors[k]) }],
                        },
                        options: { maintainAspectRatio: false, cutout: '65%', plugins: { legend: { position: 'bottom' } } },
                    });
                }
            });
        </script>
    @endpush
</x-layouts.admin>
