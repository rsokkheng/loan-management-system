<x-layouts.admin :title="__('Loans')" :breadcrumbs="['Loans']">
    @php
        $tabs = ['' => 'All', 'pending' => 'Pending', 'returned' => 'Returned', 'approved' => 'Approved', 'active' => 'Active', 'overdue' => 'Overdue', 'closed' => 'Closed', 'rejected' => 'Rejected', 'written_off' => 'Written Off'];
    @endphp

    <ul class="nav nav-pills mb-3 flex-nowrap overflow-auto">
        @foreach ($tabs as $value => $label)
            <li class="nav-item">
                <a class="nav-link text-nowrap {{ (string) request('status') === $value ? 'active' : '' }}"
                   href="{{ route('loans.index', array_filter(['status' => $value, 'search' => request('search'), 'product' => request('product')])) }}">{{ __($label) }}</a>
            </li>
        @endforeach
    </ul>

    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap gap-2 align-items-center">
            <form method="GET" class="d-flex flex-wrap gap-2 me-auto">
                <input type="hidden" name="status" value="{{ request('status') }}">
                <input type="search" name="search" value="{{ request('search') }}" class="form-control form-control-sm" style="width: 240px" placeholder="{{ __('Reference or borrower') }}">
                <select name="product" class="form-select form-select-sm" style="width: 180px" onchange="this.form.submit()">
                    <option value="">{{ __('All products') }}</option>
                    @foreach ($products as $id => $name)
                        <option value="{{ $id }}" @selected((int) request('product') === $id)>{{ $name }}</option>
                    @endforeach
                </select>
                <button class="btn btn-sm btn-secondary"><i class="bi bi-search"></i></button>
            </form>
            <a href="{{ route('loans.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i> {{ __('New Application') }}</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-nowrap align-middle mb-0">
                    <thead>
                        <tr><th>{{ __('Reference') }}</th><th>{{ __('Borrower') }}</th><th>{{ __('Product') }}</th><th class="text-end">{{ __('Principal') }}</th><th class="text-end">{{ __('Outstanding') }}</th><th style="width: 140px">{{ __('Progress') }}</th><th>{{ __('Status') }}</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($loans as $loan)
                            <tr>
                                <td>
                                    <a href="{{ route('loans.show', $loan) }}" class="fw-semibold text-decoration-none">{{ $loan->reference }}</a>
                                    <div class="small text-secondary">{{ $loan->application_date->translatedFormat('d M Y') }}</div>
                                </td>
                                <td>
                                    <a href="{{ route('borrowers.show', $loan->borrower) }}" class="text-reset text-decoration-none">{{ $loan->borrower->full_name }}</a>
                                    <div class="small text-secondary">{{ $loan->borrower->phone }}</div>
                                </td>
                                <td>
                                    {{ $loan->product->name }}
                                    @if (! $currentBranch)<div class="small text-secondary"><i class="bi bi-building"></i> {{ $loan->branch?->name }}</div>@endif
                                </td>
                                <td class="text-end">@money($loan->principal_amount, $loan->currency)</td>
                                <td class="text-end fw-semibold">
                                    @if (in_array($loan->status, [\App\Enums\LoanStatus::Active, \App\Enums\LoanStatus::WrittenOff], true))
                                        @money($loan->outstanding_balance, $loan->currency)
                                    @else
                                        <span class="text-secondary">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($loan->disbursed_at)
                                        <div class="progress" role="progressbar" style="height: 6px" aria-valuenow="{{ $loan->progress_percent }}" aria-valuemin="0" aria-valuemax="100">
                                            <div class="progress-bar bg-success" style="width: {{ $loan->progress_percent }}%"></div>
                                        </div>
                                        <div class="small text-secondary">{{ __(':percent% repaid', ['percent' => $loan->progress_percent]) }}</div>
                                    @endif
                                </td>
                                <td><x-badge :status="$loan->status" /></td>
                                <td class="text-end">
                                    <a href="{{ route('loans.show', $loan) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                                </td>
                            </tr>
                        @empty
                            <x-empty-state colspan="8" icon="bank" :title="__('No loans found')" />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($loans->hasPages())
            <div class="card-footer">{{ $loans->links() }}</div>
        @endif
    </div>
</x-layouts.admin>
