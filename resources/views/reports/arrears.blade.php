<x-layouts.admin :title="__('Arrears & Aging')" :breadcrumbs="['Reports' => route('reports.index'), 'Arrears']">
    <div class="row">
        @foreach ($buckets as $label => $bucket)
            <div class="col-6 col-lg-3">
                <div class="card mb-4 border-start border-4 border-{{ ['1-30' => 'info', '31-60' => 'warning', '61-90' => 'danger', '90+' => 'dark'][$label] ?? 'danger' }}">
                    <div class="card-body py-3">
                        <div class="stat-label">{{ __(':range days', ['range' => $label]) }}</div>
                        <div class="fs-5 fw-semibold">@money($bucket['amount'])</div>
                        <div class="small text-secondary">{{ __(':count loan(s)', ['count' => $bucket['loans']]) }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">{{ __('Loans in Arrears') }}</h3>
            <div class="card-tools">
                <a href="{{ route('reports.arrears', ['export' => 'csv']) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-filetype-csv me-1"></i> {{ __('Export CSV') }}</a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover table-nowrap align-middle mb-0">
                    <thead class="table-light"><tr><th>{{ __('Loan') }}</th><th>{{ __('Borrower') }}</th><th>{{ __('Phone') }}</th><th class="text-center">{{ __('Overdue Inst.') }}</th><th>{{ __('Oldest Due') }}</th><th class="text-center">{{ __('Days') }}</th><th class="text-end">{{ __('Amount Overdue') }}</th></tr></thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td><a href="{{ route('loans.show', $row['loan']) }}">{{ $row['loan']->reference }}</a></td>
                                <td>{{ $row['loan']->borrower->full_name }}</td>
                                <td>{{ $row['loan']->borrower->phone }}</td>
                                <td class="text-center">{{ $row['installments'] }}</td>
                                <td>{{ $row['oldest_due']->translatedFormat('d M Y') }}</td>
                                <td class="text-center"><span class="badge {{ $row['days_overdue'] > 90 ? 'text-bg-dark' : ($row['days_overdue'] > 30 ? 'text-bg-danger' : 'text-bg-warning') }}">{{ $row['days_overdue'] }}</span></td>
                                <td class="text-end fw-semibold">@money($row['amount'])</td>
                            </tr>
                        @empty
                            <x-empty-state colspan="7" icon="emoji-smile" :title="__('No loans in arrears')" />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.admin>
