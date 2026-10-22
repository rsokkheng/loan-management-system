<x-layouts.admin :title="__('Settings')" :breadcrumbs="['Settings']">
    <form method="POST" action="{{ route('settings.update') }}">
        @csrf
        @method('PUT')
        <div class="row">
            <div class="col-lg-7">
                <div class="card mb-4">
                    <div class="card-header"><h3 class="card-title">{{ __('Company') }}</h3></div>
                    <div class="card-body">
                        <x-form.input name="company_name" :label="__('Company name')" :value="$settings['company_name']" required />
                        <div class="row">
                            <x-form.input name="company_email" type="email" :label="__('Email')" :value="$settings['company_email']" wrapper="col-md-6 mb-3" />
                            <x-form.input name="company_phone" :label="__('Phone')" :value="$settings['company_phone']" wrapper="col-md-6 mb-3" />
                        </div>
                        <x-form.input name="company_address" :label="__('Address')" :value="$settings['company_address']" />
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card mb-4">
                    <div class="card-header"><h3 class="card-title">{{ __('Currency & Receipts') }}</h3></div>
                    <div class="card-body">
                        <div class="row">
                            <x-form.select name="currency_code" :label="__('Default currency')" :value="$settings['currency_code']" required wrapper="col-12 mb-3"
                                :options="collect(\App\Enums\Currency::cases())->mapWithKeys(fn ($c) => [$c->value => $c->value.' – '.$c->label()])"
                                :help="__('Used for new loan products and as the starting reporting currency.')" />
                        </div>
                        <x-form.textarea name="receipt_footer" :label="__('Receipt footer')" :value="$settings['receipt_footer']" rows="2" />
                        <x-form.input name="early_settlement_fee_percent" type="number" step="0.01" min="0" max="100" :label="__('Early settlement fee')" append="%"
                            :value="$settings['early_settlement_fee_percent']" :help="__('Charged on the remaining principal when a loan is paid off before maturity.')" />
                    </div>
                </div>
            </div>
        </div>
        <div class="card mb-4">
            <div class="card-header"><h3 class="card-title">{{ __('Approval Limits') }}</h3></div>
            <div class="card-body">
                <p class="small text-secondary">{{ __('Largest principal a Manager may approve. Larger loans need an administrator. Leave blank for no limit.') }}</p>
                <x-form.switch name="require_maker_checker" :label="__('Maker-checker: staff cannot approve applications they prepared')" :checked="$settings['require_maker_checker'] === '1'"
                    :help="__('The creator and the assigned loan officer of an application cannot approve it.')" />
                <div class="row">
                    @foreach (\App\Enums\Currency::cases() as $currency)
                        @php $key = 'approval_limit_manager_'.strtolower($currency->value); @endphp
                        <x-form.input :name="$key" type="number" :step="$currency->step()" min="0" :label="__('Manager limit (:currency)', ['currency' => $currency->value])"
                            :prepend="$currency->symbol()" :value="$settings[$key]" wrapper="col-md-4 mb-3" />
                    @endforeach
                </div>
            </div>
        </div>
        <button class="btn btn-primary mb-4"><i class="bi bi-check-lg me-1"></i> {{ __('Save Settings') }}</button>
    </form>
</x-layouts.admin>
