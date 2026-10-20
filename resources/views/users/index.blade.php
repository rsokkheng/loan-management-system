<x-layouts.admin :title="__('Users')" :breadcrumbs="['Users']">
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center">
            <h3 class="card-title mb-0">{{ __('Staff Accounts') }}</h3>
            <a href="{{ route('users.create') }}" class="btn btn-sm btn-primary ms-auto"><i class="bi bi-person-plus me-1"></i> {{ __('New User') }}</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-nowrap align-middle mb-0">
                    <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Email') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Role') }}</th><th>{{ __('Branch') }}</th><th class="text-center">{{ __('Loans') }}</th><th>{{ __('Last Login') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="avatar">{{ $user->initials() }}</span>
                                        <span class="fw-semibold">{{ $user->name }}</span>
                                        @if ($user->is(auth()->user()))<span class="badge text-bg-light border">{{ __('You') }}</span>@endif
                                    </div>
                                </td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->phone ?: '—' }}</td>
                                <td><x-badge :status="$user->role" /></td>
                                <td>{{ $user->branch?->name ?? __('All Branches') }}</td>
                                <td class="text-center">{{ $user->managed_loans_count }}</td>
                                <td>{{ $user->last_login_at?->diffForHumans() ?? __('Never') }}</td>
                                <td>
                                    <span class="badge {{ $user->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $user->is_active ? __('Active') : __('Disabled') }}</span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    @unless ($user->is(auth()->user()))
                                        <form method="POST" action="{{ route('users.destroy', $user) }}" class="d-inline" data-confirm="Delete {{ $user->name }}? Consider disabling the account instead.">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @if ($users->hasPages())
            <div class="card-footer">{{ $users->links() }}</div>
        @endif
    </div>
</x-layouts.admin>
