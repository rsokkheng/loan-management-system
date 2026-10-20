<x-layouts.admin :title="__('Receive Payment')" :breadcrumbs="['Payments' => route('payments.index'), 'Receive']">
    <div class="card mb-4">
        <div class="card-header">
            <form method="GET" class="d-flex gap-2">
                <input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="{{ __('Find an active loan by reference, borrower name, phone or ID…') }}" autofocus>
                <button class="btn btn-primary"><i class="bi bi-search"></i></button>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-nowrap align-middle mb-0">
                    <thead><tr><th>{{ __('Loan') }}</th><th>{{ __('Borrower') }}</th><th>{{ __('Phone') }}</th><th class="text-end">{{ __('Outstanding') }}</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($loans as $loan)
                            <tr>
                                <td class="fw-semibold">{{ $loan->reference }}</td>
                                <td>{{ $loan->borrower->full_name }}</td>
                                <td>{{ $loan->borrower->phone }}</td>
                                <td class="text-end fw-semibold">@money($loan->outstanding_balance)</td>
                                <td class="text-end">
                                    <a href="{{ route('loans.show', $loan) }}#receive" class="btn btn-sm btn-success" data-open-payment><i class="bi bi-cash-coin me-1"></i> {{ __('Receive') }}</a>
                                </td>
                            </tr>
                        @empty
                            <x-empty-state colspan="5" icon="search" :title="__('No active loans match your search')" />
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
