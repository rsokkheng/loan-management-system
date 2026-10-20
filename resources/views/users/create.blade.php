<x-layouts.admin :title="__('New User')" :breadcrumbs="['Users' => route('users.index'), 'New']">
    <form method="POST" action="{{ route('users.store') }}">
        @csrf
        @include('users._form')
    </form>
</x-layouts.admin>
