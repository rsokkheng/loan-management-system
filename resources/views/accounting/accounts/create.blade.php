<x-layouts.admin :title="__('New Account')" :breadcrumbs="[__('Chart of Accounts') => route('accounting.accounts.index'), __('New')]">
    <form method="POST" action="{{ route('accounting.accounts.store') }}">
        @csrf
        @include('accounting.accounts._form')
    </form>
</x-layouts.admin>
