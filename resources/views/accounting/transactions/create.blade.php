@php $isIncome = $type === 'income'; @endphp
<x-layouts.admin :title="$isIncome ? __('Record Income') : __('Record Expense')" :breadcrumbs="[__('Accounting'), $isIncome ? __('Record Income') : __('Record Expense')]">
    <ul class="nav nav-pills mb-3">
        <li class="nav-item"><a class="nav-link {{ $isIncome ? '' : 'active' }}" href="{{ route('accounting.transactions.create', ['type' => 'expense']) }}"><i class="bi bi-dash-circle me-1"></i> {{ __('Expense') }}</a></li>
        <li class="nav-item"><a class="nav-link {{ $isIncome ? 'active' : '' }}" href="{{ route('accounting.transactions.create', ['type' => 'income']) }}"><i class="bi bi-plus-circle me-1"></i> {{ __('Income') }}</a></li>
    </ul>

    <form method="POST" action="{{ route('accounting.transactions.store') }}" class="card mb-4" style="max-width: 760px">
        @csrf
        <input type="hidden" name="type" value="{{ $type }}">
        <div class="card-body">
            <div class="row">
                <x-form.input name="entry_date" type="date" :label="__('Date')" :value="today()->toDateString()" :max="today()->toDateString()" required wrapper="col-md-4 mb-3" />
                <x-form.input name="amount" type="number" step="0.01" min="0.01" :label="__('Amount')" :prepend="$appSettings['currency_symbol']" required wrapper="col-md-4 mb-3" />
                @if ($canSwitchBranch)
                    <x-form.select name="branch_id" :label="__('Branch')" :value="$currentBranch?->id" :options="$branches" required :placeholder="__('Select…')" wrapper="col-md-4 mb-3" />
                @endif
                <x-form.select name="account_id" :label="$isIncome ? __('Income category') : __('Expense category')" required :placeholder="__('Select…')" wrapper="col-md-6 mb-3"
                    :options="$categories->mapWithKeys(fn ($a) => [$a->id => $a->code.' · '.$a->display_name])" />
                <x-form.select name="cash_account_id" :label="$isIncome ? __('Received into') : __('Paid from')" required wrapper="col-md-6 mb-3"
                    :options="$cashAccounts->mapWithKeys(fn ($a) => [$a->id => $a->code.' · '.$a->display_name])" />
                <x-form.input name="description" :label="__('Description')" required wrapper="col-12 mb-3" :placeholder="$isIncome ? __('e.g. Bank interest received') : __('e.g. Office rent for October')" />
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn {{ $isIncome ? 'btn-success' : 'btn-danger' }}"><i class="bi bi-check-lg me-1"></i> {{ $isIncome ? __('Record Income') : __('Record Expense') }}</button>
            <a href="{{ route('accounting.journal.index') }}" class="btn btn-light">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.admin>
