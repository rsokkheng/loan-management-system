<x-layouts.admin :title="__('New Branch')" :breadcrumbs="[__('Branches') => route('branches.index'), __('New')]">
    <form method="POST" action="{{ route('branches.store') }}">
        @csrf
        @include('branches._form')
    </form>
</x-layouts.admin>
