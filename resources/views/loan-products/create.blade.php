<x-layouts.admin :title="__('New Loan Product')" :breadcrumbs="['Loan Products' => route('loan-products.index'), 'New']">
    <form method="POST" action="{{ route('loan-products.store') }}">
        @csrf
        @include('loan-products._form')
    </form>
</x-layouts.admin>
