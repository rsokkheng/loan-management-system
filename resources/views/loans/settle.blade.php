<x-layouts.admin :title="__('Early Settlement').' '.$loan->reference" :breadcrumbs="['Loans' => route('loans.index'), $loan->reference => route('loans.show', $loan), 'Early Settlement']">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title"><i class="bi bi-calculator me-1"></i> {{ __('Settlement Quote') }}</h3></div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="fw-semibold">{{ $loan->borrower->full_name }}</div>
                        <div class="small text-secondary">{{ $loan->reference }} · {{ __('Maturity') }} {{ $loan->maturity_date?->translatedFormat('d M Y') }}</div>
                    </div>

                    <form method="GET" action="{{ route('loans.settle', $loan) }}" class="mb-3">
                        <label for="date" class="form-label">{{ __('Settlement date') }}</label>
                        <input type="date" name="date" id="date" class="form-control" value="{{ $quote->date->toDateString() }}"
                            min="{{ $loan->disbursed_at->toDateString() }}" max="{{ today()->toDateString() }}" onchange="this.form.submit()">
                    </form>

                    <table class="table mb-0">
                        <tr><td class="text-secondary">{{ __('Remaining principal') }}</td><td class="text-end">@money($quote->principal, $loan->currency)</td></tr>
                        <tr><td class="text-secondary">{{ __('Interest due to date') }}</td><td class="text-end">@money($quote->interest, $loan->currency)</td></tr>
                        <tr><td class="text-secondary">{{ __('Unpaid penalty') }}</td><td class="text-end">@money($quote->penalty, $loan->currency)</td></tr>
                        <tr><td class="text-secondary">{{ __('Early settlement fee') }} ({{ $quote->feePercent }}%)</td><td class="text-end">@money($quote->fee, $loan->currency)</td></tr>
                        <tr class="fs-5 fw-bold"><td>{{ __('Settlement amount') }}</td><td class="text-end text-success">@money($quote->total(), $loan->currency)</td></tr>
                        <tr><td class="text-secondary">{{ __('Future interest waived') }}</td><td class="text-end text-info">@money($quote->interestWaived, $loan->currency)</td></tr>
                    </table>
                    <p class="small text-secondary mt-2 mb-0">{{ __('Current balance under the original schedule is :balance.', ['balance' => $loan->currency->format($loan->outstanding_balance)]) }}</p>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <form method="POST" action="{{ route('loans.settle.store', $loan) }}" class="card mb-4"
                data-confirm="{{ __('Settle loan :reference in full for :amount?', ['reference' => $loan->reference, 'amount' => number_format($quote->total(), 2)]) }}">
                @csrf
                <input type="hidden" name="payment_date" value="{{ $quote->date->toDateString() }}">
                <input type="hidden" name="expected_total" value="{{ number_format($quote->total(), 2, '.', '') }}">
                <div class="card-header"><h3 class="card-title">{{ __('Receive Settlement') }}</h3></div>
                <div class="card-body">
                    <x-form.select name="method" :label="__('Method')" required
                        :options="collect($paymentMethods)->mapWithKeys(fn ($m) => [$m->value => $m->label()])" />
                    <x-form.input name="transaction_reference" :label="__('Transaction reference')" :placeholder="__('Bank / mobile money reference (optional)')" />
                    <x-form.textarea name="notes" :label="__('Notes')" rows="2" />
                    @error('payment_date')
                        <div class="alert alert-danger small">{{ $message }}</div>
                    @enderror
                    <p class="small text-secondary mb-0">{{ __('All remaining installments will be marked as paid and the loan will be closed.') }}</p>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <a href="{{ route('loans.show', $loan) }}" class="btn btn-light">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check2-all me-1"></i> {{ __('Settle Loan') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
