<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-bs-theme="light">
<head>
    <title>{{ __('Loan Schedule') }} {{ $loan->reference }}</title>
    @include('partials.print-layout-head')
</head>
<body>
<div class="no-print text-center mt-3">
    <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer me-1"></i> {{ __('Print') }}</button>
    <a href="{{ route('loans.show', $loan) }}" class="btn btn-light">{{ __('Back to loan') }}</a>
</div>

<div class="sheet">
    @include('partials.print-letterhead', ['documentTitle' => 'Loan Repayment Schedule'])

    <div class="row mb-4">
        <div class="col-6">
            <div class="stat-label">{{ __('Borrower') }}</div>
            <div class="fw-semibold">{{ $loan->borrower->full_name }}</div>
            <div class="small">{{ $loan->borrower->code }} · {{ __('ID') }} {{ $loan->borrower->national_id }}</div>
            <div class="small">{{ $loan->borrower->phone }}</div>
            <div class="small">{{ $loan->borrower->full_address }}</div>
        </div>
        <div class="col-6">
            <table class="table table-sm table-borderless small mb-0">
                <tr><td class="text-secondary">{{ __('Loan reference') }}</td><td class="text-end fw-semibold">{{ $loan->reference }}</td></tr>
                <tr><td class="text-secondary">{{ __('Product') }}</td><td class="text-end">{{ $loan->product->name }}</td></tr>
                <tr><td class="text-secondary">{{ __('Principal') }}</td><td class="text-end">@money($loan->principal_amount)</td></tr>
                <tr><td class="text-secondary">{{ __('Interest') }}</td><td class="text-end">{{ (float) $loan->interest_rate }}% p.a. ({{ $loan->interest_method->label() }})</td></tr>
                <tr><td class="text-secondary">{{ __('Term') }}</td><td class="text-end">{{ $loan->term }} × {{ $loan->repayment_frequency->label() }}</td></tr>
                <tr><td class="text-secondary">{{ __('Disbursed') }}</td><td class="text-end">{{ $loan->disbursed_at?->translatedFormat('d M Y') }}</td></tr>
                <tr><td class="text-secondary">{{ __('Maturity') }}</td><td class="text-end">{{ $loan->maturity_date?->translatedFormat('d M Y') }}</td></tr>
            </table>
        </div>
    </div>

    <table class="table table-sm table-bordered align-middle">
        <thead class="table-light">
            <tr><th>#</th><th>{{ __('Due Date') }}</th><th class="text-end">{{ __('Principal') }}</th><th class="text-end">{{ __('Interest') }}</th><th class="text-end">{{ __('Installment') }}</th><th class="text-end">{{ __('Balance') }}</th><th class="text-end">{{ __('Paid') }}</th><th>{{ __('Status') }}</th></tr>
        </thead>
        <tbody>
            @foreach ($loan->installments as $installment)
                <tr>
                    <td>{{ $installment->installment_number }}</td>
                    <td>{{ $installment->due_date->translatedFormat('d M Y') }}</td>
                    <td class="text-end">@money($installment->principal_due)</td>
                    <td class="text-end">@money($installment->interest_due)</td>
                    <td class="text-end fw-semibold">@money((float) $installment->principal_due + (float) $installment->interest_due)</td>
                    <td class="text-end">@money($installment->balance_after)</td>
                    <td class="text-end">@money($installment->total_paid)</td>
                    <td>{{ $installment->status->label() }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot class="table-light fw-semibold">
            <tr>
                <td colspan="2">{{ __('Total') }}</td>
                <td class="text-end">@money($loan->installments->sum('principal_due'))</td>
                <td class="text-end">@money($loan->installments->sum('interest_due'))</td>
                <td class="text-end">@money($loan->total_payable)</td>
                <td></td>
                <td class="text-end">@money($loan->amount_paid)</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <div class="row mt-5 pt-4 small">
        <div class="col-6 text-center"><div class="border-top pt-2 mx-4">{{ __('Borrower signature') }}</div></div>
        <div class="col-6 text-center"><div class="border-top pt-2 mx-4">{{ __('Loan officer') }}: {{ $loan->officer?->name }}</div></div>
    </div>
</div>
</body>
</html>
