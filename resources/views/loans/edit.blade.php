<x-layouts.admin :title="__('Edit Application')" :breadcrumbs="['Loans' => route('loans.index'), $loan->reference => route('loans.show', $loan), 'Edit']">
    <form method="POST" action="{{ route('loans.update', $loan) }}">
        @csrf
        @method('PUT')
        @include('loans._form')
    </form>
</x-layouts.admin>
