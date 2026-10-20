<x-layouts.admin :title="__('Branches')" :breadcrumbs="[__('Branches')]">
    <div class="d-flex justify-content-end mb-3">
        <a href="{{ route('branches.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> {{ __('New Branch') }}</a>
    </div>

    <div class="card mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-nowrap align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Branch') }}</th><th>{{ __('Code') }}</th><th>{{ __('Manager') }}</th><th>{{ __('Phone') }}</th>
                            <th class="text-center">{{ __('Staff') }}</th><th class="text-center">{{ __('Borrowers') }}</th><th class="text-center">{{ __('Loans') }}</th>
                            <th>{{ __('Status') }}</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($branches as $branch)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $branch->name }}</div>
                                    <div class="small text-secondary">{{ $branch->address ?: '—' }}</div>
                                </td>
                                <td><code>{{ $branch->code }}</code></td>
                                <td>{{ $branch->manager?->name ?? '—' }}</td>
                                <td>{{ $branch->phone ?: '—' }}</td>
                                <td class="text-center">{{ $branch->users_count }}</td>
                                <td class="text-center">{{ $branch->borrowers_count }}</td>
                                <td class="text-center">{{ $branch->loans_count }}</td>
                                <td><span class="badge {{ $branch->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $branch->is_active ? __('Active') : __('Inactive') }}</span></td>
                                <td class="text-end">
                                    <a href="{{ route('branches.edit', $branch) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <form method="POST" action="{{ route('branches.destroy', $branch) }}" class="d-inline" data-confirm="{{ __('Delete :name?', ['name' => $branch->name]) }}">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <x-empty-state colspan="9" icon="building" :title="__('No branches yet')" />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.admin>
