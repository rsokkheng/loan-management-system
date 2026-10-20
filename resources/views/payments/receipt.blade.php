<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-bs-theme="light">
<head>
    <title>{{ __('Receipt') }} {{ $payment->receipt_number }}</title>
    @include('partials.print-layout-head')
    <style>.sheet { max-width: 620px; }</style>
</head>
<body>
<div class="no-print text-center mt-3">
    <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer me-1"></i> {{ __('Print') }}</button>
    <a href="{{ route('payments.show', $payment) }}" class="btn btn-light">{{ __('Back') }}</a>
</div>

<div class="sheet">
    @include('partials.print-letterhead', ['documentTitle' => 'Payment Receipt'])

    <div class="d-flex justify-content-between mb-4">
        <div>
            <div class="stat-label">{{ __('Received from') }}</div>
            <div class="fw-semibold">{{ $payment->loan->borrower->full_name }}</div>
            <div class="small">{{ $payment->loan->borrower->code }} · {{ $payment->loan->borrower->phone }}</div>
        </div>
        <div class="text-end">
            <div class="stat-label">{{ __('Receipt no.') }}</div>
            <div class="fw-semibold">{{ $payment->receipt_number }}</div>
            <div class="small">{{ $payment->payment_date->translatedFormat('d M Y') }}</div>
        </div>
    </div>

    <table class="table table-sm">
        <tr><td class="text-secondary">{{ __('Loan reference') }}</td><td class="text-end">{{ $payment->loan->reference }}</td></tr>
        <tr><td class="text-secondary">{{ __('Payment method') }}</td><td class="text-end">{{ $payment->method->label() }}</td></tr>
        @if ($payment->transaction_reference)
            <tr><td class="text-secondary">{{ __('Transaction ref.') }}</td><td class="text-end">{{ $payment->transaction_reference }}</td></tr>
        @endif
        <tr><td class="text-secondary">{{ __('Principal') }}</td><td class="text-end">@money($payment->principal_amount)</td></tr>
        <tr><td class="text-secondary">{{ __('Interest') }}</td><td class="text-end">@money($payment->interest_amount)</td></tr>
        <tr><td class="text-secondary">{{ __('Penalty') }}</td><td class="text-end">@money($payment->penalty_amount)</td></tr>
        <tr class="fs-5 fw-bold"><td>{{ __('Total paid') }}</td><td class="text-end">@money($payment->amount)</td></tr>
        <tr><td class="text-secondary">{{ __('Balance remaining') }}</td><td class="text-end">@money($payment->loan->outstanding_balance)</td></tr>
    </table>

    <div class="d-flex justify-content-between align-items-end mt-5 small">
        <div>{{ __('Received by:') }} <strong>{{ $payment->receiver?->name }}</strong></div>
        <div class="text-center"><div class="border-top pt-2 px-5">{{ __('Signature') }}</div></div>
    </div>

    @if ($appSettings['receipt_footer'])
        <p class="text-center text-secondary small mt-4 mb-0">{{ $appSettings['receipt_footer'] }}</p>
    @endif
</div>
</body>
</html>
