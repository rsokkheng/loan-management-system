<x-layouts.admin :title="__('Disbursements Report')" :breadcrumbs="['Reports' => route('reports.index'), 'Disbursements']">
    <div class="card mb-4">
        <div class="card-header">@include('reports._range')</div>
        <div class="card-body">
            <div class="row text-center">
                <div class="col"><div class="stat-label">{{ __('Loans') }}</div><div class="fs-5 fw-semibold">{{ $loans->count() }}</div></div>
                <div class="col"><div class="stat-label">{{ __('Principal') }}</div><div class="fs-5 fw-semibold">@money($loans->sum('principal_amount'))</div></div>
                <div class="col"><div class="stat-label">{{ __('Fees') }}</div><div class="fs-5 fw-semibold">@money($loans->sum('processing_fee'))</div></div>
                <div class="col"><div class="stat-label">{{ __('Expected Interest') }}</div><div class="fs-5 fw-semibold">@money($loans->sum('total_interest'))</div></div>
            </div>
        </div>
        <div class="card-body p-0 border-top">
            <div class="table-responsive">
                <table class="table table-sm table-hover table-nowrap align-middle mb-0">
                    <thead class="table-light"><tr><th>{{ __('Date') }}</th><th>{{ __('Loan') }}</th><th>{{ __('Borrower') }}</th><th>{{ __('Product') }}</th><th class="text-end">{{ __('Principal') }}</th><th class="text-end">{{ __('Fee') }}</th><th class="text-end">{{ __('Interest') }}</th><th class="text-end">{{ __('Total Payable') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                        @forelse ($loans as $loan)
                            <tr>
                                <td>{{ $loan->disbursed_at->translatedFormat('d M Y') }}</td>
                                <td><a href="{{ route('loans.show', $loan) }}">{{ $loan->reference }}</a></td>
                                <td>{{ $loan->borrower->full_name }}</td>
                                <td>{{ $loan->product->name }}</td>
                                <td class="text-end">@money($loan->principal_amount)</td>
                                <td class="text-end">@money($loan->processing_fee)</td>
                                <td class="text-end">@money($loan->total_interest)</td>
                                <td class="text-end fw-semibold">@money($loan->total_payable)</td>
                                <td><x-badge :status="$loan->status" /></td>
                            </tr>
                        @empty
                            <x-empty-state colspan="9" icon="send" :title="__('No disbursements in this period')" />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.admin>
