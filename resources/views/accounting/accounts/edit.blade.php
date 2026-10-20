<x-layouts.admin :title="__('Edit Account')" :breadcrumbs="[__('Chart of Accounts') => route('accounting.accounts.index'), $account->code]">
    <form method="POST" action="{{ route('accounting.accounts.update', $account) }}">
        @csrf
        @method('PUT')
        @include('accounting.accounts._form')
    </form>
    <form method="POST" action="{{ route('accounting.accounts.destroy', $account) }}" id="delete-account" data-confirm="{{ __('Delete this account?') }}">
        @csrf @method('DELETE')
    </form>
</x-layouts.admin>
