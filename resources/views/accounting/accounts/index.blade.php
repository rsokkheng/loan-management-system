<x-layouts.admin :title="__('Chart of Accounts')" :breadcrumbs="[__('Accounting'), __('Chart of Accounts')]">
    <div class="d-flex flex-wrap gap-2 justify-content-end mb-3">
        <a href="{{ route('accounting.transactions.create', ['type' => 'expense']) }}" class="btn btn-outline-danger"><i class="bi bi-dash-circle me-1"></i> {{ __('Record Expense') }}</a>
        <a href="{{ route('accounting.transactions.create', ['type' => 'income']) }}" class="btn btn-outline-success"><i class="bi bi-plus-circle me-1"></i> {{ __('Record Income') }}</a>
        <a href="{{ route('accounting.accounts.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> {{ __('New Account') }}</a>
    </div>

    <div class="row">
        @foreach ($groups as $type => $accounts)
            @php $accountType = \App\Enums\AccountType::from($type); @endphp
            <div class="col-xl-6">
                <div class="card mb-4">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title mb-0"><x-badge :status="$accountType" class="me-1" /> </h3>
                        <span class="ms-auto fw-semibold">@money($accounts->sum('balance'))</span>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <tbody>
                                @forelse ($accounts as $account)
                                    <tr class="{{ $account->is_active ? '' : 'text-secondary' }}">
                                        <td style="width: 70px"><code>{{ $account->code }}</code></td>
                                        <td>
                                            <a href="{{ route('accounting.accounts.show', $account) }}" class="text-decoration-none">{{ $account->display_name }}</a>
                                            @if ($account->is_cash)<span class="badge text-bg-light border ms-1"><i class="bi bi-cash"></i></span>@endif
                                            @if ($account->isSystem())<i class="bi bi-gear-fill text-secondary small ms-1" data-bs-toggle="tooltip" title="{{ __('Used by automatic postings') }}"></i>@endif
                                            @unless ($account->is_active)<span class="badge text-bg-secondary ms-1">{{ __('Inactive') }}</span>@endunless
                                        </td>
                                        <td class="text-end fw-semibold">@money($account->balance)</td>
                                        <td class="text-end" style="width: 50px">
                                            <a href="{{ route('accounting.accounts.edit', $account) }}" class="btn btn-sm btn-link p-0"><i class="bi bi-pencil"></i></a>
                                        </td>
                                    </tr>
                                @empty
                                    <x-empty-state colspan="4" icon="journal" :title="__('No accounts')" />
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</x-layouts.admin>
