<x-layouts.admin :title="__('Edit Loan Product')" :breadcrumbs="['Loan Products' => route('loan-products.index'), $product->name]">
    <div class="callout callout-info mb-4">
        {{ __('Changes apply to new applications only. Existing loans keep the terms they were created with.') }}
    </div>
    <form method="POST" action="{{ route('loan-products.update', $product) }}">
        @csrf
        @method('PUT')
        @include('loan-products._form')
    </form>
</x-layouts.admin>
