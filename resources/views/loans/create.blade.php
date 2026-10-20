<x-layouts.admin :title="__('New Loan Application')" :breadcrumbs="['Loans' => route('loans.index'), 'New Application']">
    @if ($products->isEmpty())
        <div class="alert alert-warning">
            {{ __('No active loan products are available.') }}
            @can('manage-products') <a href="{{ route('loan-products.create') }}">{{ __('Create a loan product') }}</a> @endcan
        </div>
    @endif
    <form method="POST" action="{{ route('loans.store') }}">
        @csrf
        @include('loans._form')
    </form>
</x-layouts.admin>
