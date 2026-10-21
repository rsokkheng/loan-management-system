<x-layouts.admin :title="__('Borrowers')" :breadcrumbs="['Borrowers']">
    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap gap-2 align-items-center">
            <form method="GET" class="d-flex flex-wrap gap-2 me-auto">
                <input type="search" name="search" value="{{ request('search') }}" class="form-control form-control-sm" style="width: 260px" placeholder="{{ __('Name, code, phone or national ID') }}">
                <select name="status" class="form-select form-select-sm" style="width: 160px" onchange="this.form.submit()">
                    <option value="">{{ __('All borrowers') }}</option>
                    <option value="blacklisted" @selected(request('status') === 'blacklisted')>{{ __('Blacklisted') }}</option>
                </select>
                <button class="btn btn-sm btn-secondary"><i class="bi bi-search"></i></button>
            </form>
            <a href="{{ route('borrowers.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-person-plus me-1"></i> {{ __('New Borrower') }}</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-nowrap align-middle mb-0">
                    <thead>
                        <tr><th>{{ __('Borrower') }}</th><th>{{ __('Code') }}</th><th>{{ __('Phone') }}</th><th>{{ __('National ID') }}</th><th>{{ __('Location') }}</th><th class="text-center">{{ __('Loans') }}</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($borrowers as $borrower)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <x-avatar :borrower="$borrower" />
                                        <div>
                                            <a href="{{ route('borrowers.show', $borrower) }}" class="fw-semibold text-decoration-none">{{ $borrower->full_name }}</a>
                                            @if ($borrower->is_blacklisted)
                                                <span class="badge text-bg-danger ms-1">{{ __('Blacklisted') }}</span>
                                            @endif
                                            <div class="small text-secondary">{{ $borrower->occupation ?: '—' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td><code>{{ $borrower->code }}</code></td>
                                <td>{{ $borrower->phone }}</td>
                                <td>{{ $borrower->national_id }}</td>
                                <td>
                                    {{ collect([$borrower->district?->display_name, $borrower->province?->display_name])->filter()->implode(', ') ?: '—' }}
                                    @if (! $currentBranch)<div class="small text-secondary"><i class="bi bi-building"></i> {{ $borrower->branch?->name }}</div>@endif
                                </td>
                                <td class="text-center"><span class="badge text-bg-light border">{{ $borrower->loans_count }}</span></td>
                                <td class="text-end">
                                    <a href="{{ route('borrowers.show', $borrower) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('View') }}"><i class="bi bi-eye"></i></a>
                                    <a href="{{ route('borrowers.edit', $borrower) }}" class="btn btn-sm btn-outline-primary" title="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                                </td>
                            </tr>
                        @empty
                            <x-empty-state colspan="7" icon="people" :title="__('No borrowers found')">
                                <a href="{{ route('borrowers.create') }}">{{ __('Register the first borrower') }}</a>
                            </x-empty-state>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($borrowers->hasPages())
            <div class="card-footer">{{ $borrowers->links() }}</div>
        @endif
    </div>
</x-layouts.admin>
