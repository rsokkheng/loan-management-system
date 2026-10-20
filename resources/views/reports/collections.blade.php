<x-layouts.admin :title="__('Collections Report')" :breadcrumbs="['Reports' => route('reports.index'), 'Collections']">
    <div class="card mb-4">
        <div class="card-header">@include('reports._range')</div>
        <div class="card-body">
            <div class="row text-center">
                @foreach ([
                    'Payments' => null,
                    'Principal' => $payments->sum('principal_amount'),
                    'Interest' => $payments->sum('interest_amount'),
                    'Penalty' => $payments->sum('penalty_amount'),
                    'Total Collected' => $payments->sum('amount'),
                ] as $label => $value)
                    <div class="col">
                        <div class="stat-label">{{ __($label) }}</div>
                        <div class="fs-5 fw-semibold">@if (is_null($value)){{ $payments->count() }}@else @money($value) @endif</div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="card-body p-0 border-top">
            <div class="table-responsive">
                <table class="table table-sm table-hover table-nowrap align-middle mb-0">
                    <thead class="table-light"><tr><th>{{ __('Date') }}</th><th>{{ __('Receipt') }}</th><th>{{ __('Loan') }}</th><th>{{ __('Borrower') }}</th><th>{{ __('Method') }}</th><th class="text-end">{{ __('Principal') }}</th><th class="text-end">{{ __('Interest') }}</th><th class="text-end">{{ __('Penalty') }}</th><th class="text-end">{{ __('Total') }}</th><th>{{ __('Received By') }}</th></tr></thead>
                    <tbody>
                        @forelse ($payments as $payment)
                            <tr>
                                <td>{{ $payment->payment_date->translatedFormat('d M Y') }}</td>
                                <td><a href="{{ route('payments.show', $payment) }}">{{ $payment->receipt_number }}</a></td>
                                <td>{{ $payment->loan->reference }}</td>
                                <td>{{ $payment->loan->borrower->full_name }}</td>
                                <td>{{ $payment->method->label() }}</td>
                                <td class="text-end">@money($payment->principal_amount)</td>
                                <td class="text-end">@money($payment->interest_amount)</td>
                                <td class="text-end">@money($payment->penalty_amount)</td>
                                <td class="text-end fw-semibold">@money($payment->amount)</td>
                                <td>{{ $payment->receiver?->name }}</td>
                            </tr>
                        @empty
                            <x-empty-state colspan="10" icon="cash-stack" :title="__('No collections in this period')" />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.admin>
