<x-layouts.admin :title="__('Edit User')" :breadcrumbs="['Users' => route('users.index'), $user->name]">
    <form method="POST" action="{{ route('users.update', $user) }}">
        @csrf
        @method('PUT')
        @if ($user->is(auth()->user()))
            <input type="hidden" name="role" value="{{ $user->role->value }}">
            <input type="hidden" name="is_active" value="1">
            <input type="hidden" name="branch_id" value="{{ $user->branch_id }}">
        @endif
        @include('users._form')
    </form>
</x-layouts.admin>
