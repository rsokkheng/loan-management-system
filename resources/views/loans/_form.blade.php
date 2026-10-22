<div class="row">
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header"><h3 class="card-title">{{ __('Application Details') }}</h3></div>
            <div class="card-body">
                @if ($loan->exists)
                    <div class="mb-3">
                        <label class="form-label">{{ __('Borrower') }}</label>
                        <input type="text" class="form-control" value="{{ $loan->borrower->full_name }} ({{ $loan->borrower->code }})" disabled>
                    </div>
                @else
                    <x-form.select name="borrower_id" :label="__('Borrower')" :value="$loan->borrower_id" required :placeholder="__('Select a borrower…')"
                        :options="$borrowers->mapWithKeys(fn ($b) => [$b->id => $b->first_name.' '.$b->last_name.' ('.$b->code.')'])"
                        :help="__('Blacklisted borrowers are not listed.')" />
                @endif

                <x-form.select name="loan_product_id" :label="__('Loan product')" required :placeholder="__('Select a product…')">
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected((int) old('loan_product_id', $loan->loan_product_id) === $product->id)
                            data-rate="{{ (float) $product->interest_rate }}"
                            data-currency-symbol="{{ $product->currency->symbol() }}"
                            data-currency-precision="{{ $product->currency->precision() }}"
                            data-currency-suffix="{{ $product->currency === \App\Enums\Currency::Khr ? 1 : 0 }}"
                            data-method="{{ $product->interest_method->value }}"
                            data-method-label="{{ $product->interest_method->label() }}"
                            data-frequency-label="{{ $product->repayment_frequency->label() }}"
                            data-periods="{{ $product->repayment_frequency->periodsPerYear() }}"
                            data-min-amount="{{ (float) $product->min_amount }}"
                            data-max-amount="{{ (float) $product->max_amount }}"
                            data-min-term="{{ $product->min_term }}"
                            data-max-term="{{ $product->max_term }}"
                            data-fee="{{ (float) $product->processing_fee_percent }}"
                            data-penalty="{{ (float) $product->late_penalty_percent }}"
                            data-grace="{{ $product->grace_period_days }}"
                            data-schedule="{{ $product->schedule_type->value }}">{{ $product->name }} ({{ $product->currency->value }})</option>
                    @endforeach
                </x-form.select>

                <div class="row">
                    <x-form.input name="principal_amount" type="number" step="0.01" min="1" :label="__('Loan amount')" :prepend="($loan->currency ?? $reportingCurrency)->symbol()"
                        :value="$loan->principal_amount" required wrapper="col-md-6 mb-3" />
                    <x-form.input name="term" type="number" min="1" :label="__('Term (number of installments)')" :value="$loan->term" required wrapper="col-md-6 mb-3" />
                    <x-form.input name="application_date" type="date" :label="__('Application date')" :value="$loan->application_date?->toDateString()" required wrapper="col-md-6 mb-3" />
                    <x-form.select name="loan_officer_id" :label="__('Loan officer')" :value="$loan->loan_officer_id" :options="$officers" wrapper="col-md-6 mb-3" />
                </div>

                @php
                    $planRows = old('plan', $loan->flexible_plan ?? []) ?: [['due_date' => '', 'principal' => '']];
                @endphp
                <div id="flexible-plan" class="border rounded p-3 mb-3 bg-body-tertiary" hidden>
                    <div class="d-flex align-items-center mb-2">
                        <h6 class="mb-0">{{ __('Flexible Schedule') }}</h6>
                        <div class="ms-auto d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-plan-spread>{{ __('Spread evenly') }}</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-plan-add><i class="bi bi-plus-lg"></i> {{ __('Add row') }}</button>
                        </div>
                    </div>
                    <p class="small text-secondary">{{ __('Enter each due date and the principal due on it. Use 0 for interest-only periods. The principal must add up to the loan amount.') }}</p>
                    @error('plan')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
                    <table class="table table-sm align-middle mb-2">
                        <thead><tr><th style="width: 3rem">#</th><th>{{ __('Due Date') }}</th><th>{{ __('Principal') }}</th><th style="width: 3rem"></th></tr></thead>
                        <tbody data-plan-rows>
                            @foreach ($planRows as $i => $row)
                                <tr data-plan-row>
                                    <td class="text-secondary" data-plan-number>{{ $loop->iteration }}</td>
                                    <td><input type="date" name="plan[{{ $i }}][due_date]" value="{{ $row['due_date'] ?? '' }}" class="form-control form-control-sm @error("plan.$i.due_date") is-invalid @enderror" data-plan-date>
                                        @error("plan.$i.due_date")<div class="invalid-feedback">{{ $message }}</div>@enderror</td>
                                    <td><input type="number" step="any" min="0" name="plan[{{ $i }}][principal]" value="{{ $row['principal'] ?? '' }}" class="form-control form-control-sm text-end" data-plan-principal></td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger" data-plan-remove title="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot><tr><td></td><td class="text-end small text-secondary">{{ __('Total principal') }}</td><td class="text-end fw-semibold" data-plan-total>0</td><td></td></tr></tfoot>
                    </table>
                </div>

                <x-form.input name="purpose" :label="__('Purpose')" :value="$loan->purpose" :placeholder="__('e.g. Working capital for grocery shop')" />
                <x-form.textarea name="notes" :label="__('Notes')" :value="$loan->notes" />
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header"><h3 class="card-title">{{ __('Product Terms') }}</h3></div>
            <div class="card-body" id="product-info"></div>
        </div>
        <div class="card mb-4 border-primary-subtle">
            <div class="card-header"><h3 class="card-title"><i class="bi bi-calculator me-1"></i> {{ __('Estimate') }}</h3></div>
            <div class="card-body" id="loan-estimate"></div>
        </div>
    </div>
</div>

<div class="d-flex gap-2 mb-4">
    <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i> {{ $loan->exists ? 'Update Application' : 'Submit Application' }}</button>
    <a href="{{ $loan->exists ? route('loans.show', $loan) : route('loans.index') }}" class="btn btn-light">{{ __('Cancel') }}</a>
</div>
