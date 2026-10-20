<x-layouts.admin :title="__('Loan Calculator')" :breadcrumbs="['Calculator']">
    <div class="row">
        <div class="col-lg-4">
            <form method="GET" class="card mb-4">
                <div class="card-header"><h3 class="card-title">{{ __('Loan Terms') }}</h3></div>
                <div class="card-body">
                    @if ($products->isNotEmpty())
                        <div class="mb-3">
                            <label class="form-label" for="calc-product">{{ __('Start from a product') }}</label>
                            <select id="calc-product" class="form-select">
                                <option value="">{{ __('Custom terms') }}</option>
                                @foreach ($products as $product)
                                    <option data-rate="{{ (float) $product->interest_rate }}" data-method="{{ $product->interest_method->value }}"
                                        data-frequency="{{ $product->repayment_frequency->value }}" data-fee="{{ (float) $product->processing_fee_percent }}">{{ $product->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <x-form.input name="principal" type="number" step="0.01" min="1" :label="__('Loan amount')" :prepend="$appSettings['currency_symbol']" :value="request('principal', 5000)" required />
                    <x-form.input name="interest_rate" type="number" step="0.0001" min="0" :label="__('Annual interest rate')" append="%" :value="request('interest_rate', 18)" required />
                    <x-form.select name="interest_method" :label="__('Interest method')" :value="request('interest_method', 'reducing_balance')"
                        :options="collect($interestMethods)->mapWithKeys(fn ($m) => [$m->value => $m->label()])" />
                    <div class="row">
                        <x-form.select name="repayment_frequency" :label="__('Frequency')" :value="request('repayment_frequency', 'monthly')" wrapper="col-6 mb-3"
                            :options="collect($frequencies)->mapWithKeys(fn ($f) => [$f->value => $f->label()])" />
                        <x-form.input name="term" type="number" min="1" :label="__('Installments')" :value="request('term', 12)" required wrapper="col-6 mb-3" />
                    </div>
                    <div class="row">
                        <x-form.input name="first_due_date" type="date" :label="__('First due date')" :value="request('first_due_date', today()->addMonth()->toDateString())" required wrapper="col-6 mb-3" />
                        <x-form.input name="processing_fee_percent" type="number" step="0.01" min="0" :label="__('Fee')" append="%" :value="request('processing_fee_percent', 0)" wrapper="col-6 mb-3" />
                    </div>
                </div>
                <div class="card-footer">
                    <button class="btn btn-primary w-100"><i class="bi bi-calculator me-1"></i> {{ __('Calculate') }}</button>
                </div>
            </form>
        </div>

        <div class="col-lg-8">
            @if ($quote)
                <div class="row">
                    @foreach ([
                        ['Installment', $quote->installmentAmount(), 'primary'],
                        ['Total Interest', $quote->totalInterest, 'info'],
                        ['Total Payable', $quote->totalPayable(), 'success'],
                        ['Net Disbursed', $quote->principal - $quote->processingFee, 'warning'],
                    ] as [$label, $value, $color])
                        <div class="col-6 col-xl-3">
                            <div class="card mb-4 border-start border-4 border-{{ $color }}">
                                <div class="card-body py-3">
                                    <div class="stat-label">{{ __($label) }}</div>
                                    <div class="fs-5 fw-semibold">@money($value)</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('Amortization Schedule') }}</h3>
                        <div class="card-tools"><button onclick="window.print()" class="btn btn-sm btn-outline-secondary no-print"><i class="bi bi-printer"></i></button></div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover table-nowrap align-middle mb-0">
                                <thead class="table-light"><tr><th>#</th><th>{{ __('Due Date') }}</th><th class="text-end">{{ __('Principal') }}</th><th class="text-end">{{ __('Interest') }}</th><th class="text-end">{{ __('Installment') }}</th><th class="text-end">{{ __('Balance') }}</th></tr></thead>
                                <tbody>
                                    @foreach ($quote->schedule as $line)
                                        <tr>
                                            <td>{{ $line->number }}</td>
                                            <td>{{ $line->dueDate->translatedFormat('d M Y') }}</td>
                                            <td class="text-end">@money($line->principal)</td>
                                            <td class="text-end">@money($line->interest)</td>
                                            <td class="text-end fw-semibold">@money($line->total())</td>
                                            <td class="text-end">@money($line->balanceAfter)</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @else
                <div class="card"><div class="card-body">
                    <x-empty-state icon="calculator" :title="__('Enter loan terms to see the repayment schedule')" />
                </div></div>
            @endif
        </div>
    </div>
</x-layouts.admin>
