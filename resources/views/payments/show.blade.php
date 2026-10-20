<x-layouts.admin :title="__('Payment').' '.$payment->receipt_number" :breadcrumbs="['Payments' => route('payments.index'), $payment->receipt_number]">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0"><i class="bi bi-receipt me-1"></i> {{ $payment->receipt_number }}</h3>
                    <div class="ms-auto d-flex gap-2">
                        <a href="{{ route('payments.receipt', $payment) }}" target="_blank" class="btn btn-sm btn-primary"><i class="bi bi-printer me-1"></i> {{ __('Print Receipt') }}</a>
                        <a href="{{ route('loans.show', $payment->loan) }}" class="btn btn-sm btn-outline-secondary">{{ __('View Loan') }}</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="text-center py-3">
                        <div class="stat-label">{{ __('Amount received') }}</div>
                        <div class="display-5 fw-semibold text-success">@money($payment->amount)</div>
                        <div class="text-secondary">{{ $payment->payment_date->translatedFormat('l, d F Y') }} · {{ $payment->method->label() }}</div>
                    </div>
                    <table class="table mb-0">
                        <tr><td class="text-secondary">{{ __('Borrower') }}</td><td class="text-end"><a href="{{ route('borrowers.show', $payment->loan->borrower) }}">{{ $payment->loan->borrower->full_name }}</a></td></tr>
                        <tr><td class="text-secondary">{{ __('Loan') }}</td><td class="text-end">{{ $payment->loan->reference }}</td></tr>
                        <tr><td class="text-secondary">{{ __('Applied to principal') }}</td><td class="text-end">@money($payment->principal_amount)</td></tr>
                        <tr><td class="text-secondary">{{ __('Applied to interest') }}</td><td class="text-end">@money($payment->interest_amount)</td></tr>
                        <tr><td class="text-secondary">{{ __('Applied to penalty') }}</td><td class="text-end">@money($payment->penalty_amount)</td></tr>
                        <tr><td class="text-secondary">{{ __('Transaction reference') }}</td><td class="text-end">{{ $payment->transaction_reference ?: '—' }}</td></tr>
                        <tr><td class="text-secondary">{{ __('Received by') }}</td><td class="text-end">{{ $payment->receiver?->name }}</td></tr>
                        <tr><td class="text-secondary">{{ __('Loan balance now') }}</td><td class="text-end fw-semibold">@money($payment->loan->outstanding_balance)</td></tr>
                        @if ($payment->notes)
                            <tr><td class="text-secondary">{{ __('Notes') }}</td><td class="text-end">{{ $payment->notes }}</td></tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
