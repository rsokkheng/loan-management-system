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
                            <x-form.input name="currency_code" :label="__('Currency code')" :value="$settings['currency_code']" maxlength="3" required wrapper="col-6 mb-3" />
                            <x-form.input name="currency_symbol" :label="__('Symbol')" :value="$settings['currency_symbol']" required wrapper="col-6 mb-3" />
                        </div>
                        <x-form.textarea name="receipt_footer" :label="__('Receipt footer')" :value="$settings['receipt_footer']" rows="2" />
                    </div>
                </div>
            </div>
        </div>
        <button class="btn btn-primary mb-4"><i class="bi bi-check-lg me-1"></i> {{ __('Save Settings') }}</button>
    </form>
</x-layouts.admin>
