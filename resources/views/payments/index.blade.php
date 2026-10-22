<x-layouts.admin :title="__('Payment History')" :breadcrumbs="['Payments']">
    <div class="card mb-4">
        <div class="card-header">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">{{ __('Search') }}</label>
                    <input type="search" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="{{ __('Receipt, loan or borrower') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">{{ __('From') }}</label>
                    <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">{{ __('To') }}</label>
                    <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">{{ __('Method') }}</label>
                    <select name="method" class="form-select form-select-sm">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($methods as $method)
                            <option value="{{ $method->value }}" @selected(request('method') === $method->value)>{{ $method->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-sm btn-secondary"><i class="bi bi-funnel me-1"></i> {{ __('Filter') }}</button>
                    <a href="{{ route('payments.index') }}" class="btn btn-sm btn-light">{{ __('Reset') }}</a>
                    <a href="{{ route('payments.create') }}" class="btn btn-sm btn-primary ms-auto"><i class="bi bi-plus-lg"></i> {{ __('Receive') }}</a>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-nowrap align-middle mb-0">
                    <thead><tr><th>{{ __('Receipt') }}</th><th>{{ __('Date') }}</th><th>{{ __('Loan') }}</th><th>{{ __('Borrower') }}</th><th>{{ __('Method') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Received By') }}</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($payments as $payment)
                            <tr>
                                <td><a href="{{ route('payments.show', $payment) }}" class="fw-semibold">{{ $payment->receipt_number }}</a></td>
                                <td>{{ $payment->payment_date->translatedFormat('d M Y') }}</td>
                                <td><a href="{{ route('loans.show', $payment->loan) }}">{{ $payment->loan->reference }}</a></td>
                                <td>{{ $payment->loan->borrower->full_name }}</td>
                                <td>{{ $payment->method->label() }}</td>
                                <td class="text-end fw-semibold text-success">@money($payment->amount, $payment->loan->currency)</td>
                                <td>{{ $payment->receiver?->name }}</td>
                                <td class="text-end"><a href="{{ route('payments.receipt', $payment) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="{{ __('Print receipt') }}"><i class="bi bi-printer"></i></a></td>
                            </tr>
                        @empty
                            <x-empty-state colspan="8" icon="receipt" :title="__('No payments found')" />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($payments->hasPages())
            <div class="card-footer">{{ $payments->links() }}</div>
        @endif
    </div>
</x-layouts.admin>
