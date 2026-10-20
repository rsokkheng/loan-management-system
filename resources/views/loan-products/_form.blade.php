<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header"><h3 class="card-title">{{ __('Product Details') }}</h3></div>
            <div class="card-body">
                <div class="row">
                    <x-form.input name="name" :label="__('Product name')" :value="$product->name" required wrapper="col-md-8 mb-3" />
                    <x-form.input name="code" :label="__('Code')" :value="$product->code" required wrapper="col-md-4 mb-3" :placeholder="__('e.g. SME')" />
                    <x-form.textarea name="description" :label="__('Description')" :value="$product->description" rows="2" wrapper="col-12 mb-3" />
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h3 class="card-title">{{ __('Interest & Repayment') }}</h3></div>
            <div class="card-body">
                <div class="row">
                    <x-form.input name="interest_rate" type="number" step="0.0001" min="0" :label="__('Annual interest rate')" append="%" :value="$product->interest_rate" required wrapper="col-md-4 mb-3" />
                    <x-form.select name="interest_method" :label="__('Interest method')" :value="$product->interest_method" required wrapper="col-md-4 mb-3"
                        :options="collect($interestMethods)->mapWithKeys(fn ($m) => [$m->value => $m->label()])" />
                    <x-form.select name="repayment_frequency" :label="__('Repayment frequency')" :value="$product->repayment_frequency" required wrapper="col-md-4 mb-3"
                        :options="collect($frequencies)->mapWithKeys(fn ($f) => [$f->value => $f->label()])" />
                    <x-form.input name="min_amount" type="number" step="0.01" min="1" :label="__('Minimum amount')" :prepend="$appSettings['currency_symbol']" :value="$product->min_amount" required wrapper="col-md-3 mb-3" />
                    <x-form.input name="max_amount" type="number" step="0.01" min="1" :label="__('Maximum amount')" :prepend="$appSettings['currency_symbol']" :value="$product->max_amount" required wrapper="col-md-3 mb-3" />
                    <x-form.input name="min_term" type="number" min="1" :label="__('Min. installments')" :value="$product->min_term" required wrapper="col-md-3 mb-3" />
                    <x-form.input name="max_term" type="number" min="1" :label="__('Max. installments')" :value="$product->max_term" required wrapper="col-md-3 mb-3" />
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header"><h3 class="card-title">{{ __('Fees & Penalties') }}</h3></div>
            <div class="card-body">
                <x-form.input name="processing_fee_percent" type="number" step="0.01" min="0" :label="__('Processing fee')" append="%" :value="$product->processing_fee_percent ?? 0" required :help="__('Deducted from the amount disbursed.')" />
                <x-form.input name="late_penalty_percent" type="number" step="0.01" min="0" :label="__('Late penalty')" append="%" :value="$product->late_penalty_percent ?? 0" required :help="__('Charged once on the overdue installment balance.')" />
                <x-form.input name="grace_period_days" type="number" min="0" :label="__('Grace period')" append="days" :value="$product->grace_period_days ?? 0" required />
                <x-form.switch name="is_active" :label="__('Available for new loans')" :checked="$product->is_active" />
            </div>
        </div>
    </div>
</div>

<div class="d-flex gap-2 mb-4">
    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> {{ __('Save Product') }}</button>
    <a href="{{ route('loan-products.index') }}" class="btn btn-light">{{ __('Cancel') }}</a>
</div>
