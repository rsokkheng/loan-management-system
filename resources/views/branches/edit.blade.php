<x-layouts.admin :title="__('Edit Branch')" :breadcrumbs="[__('Branches') => route('branches.index'), $branch->name]">
    <form method="POST" action="{{ route('branches.update', $branch) }}">
        @csrf
        @method('PUT')
        @include('branches._form')
    </form>
</x-layouts.admin>
