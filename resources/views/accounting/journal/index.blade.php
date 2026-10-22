<x-layouts.admin :title="__('Journal Entries')" :breadcrumbs="[__('Accounting'), __('Journal Entries')]">
    <div class="card mb-4">
        <div class="card-header">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">{{ __('Search') }}</label>
                    <input type="search" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="{{ __('Reference or description') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">{{ __('Type') }}</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">{{ __('From') }}</label>
                    <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">{{ __('To') }}</label>
                    <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-sm btn-secondary"><i class="bi bi-funnel me-1"></i> {{ __('Filter') }}</button>
                    <a href="{{ route('accounting.journal.create') }}" class="btn btn-sm btn-primary ms-auto"><i class="bi bi-plus-lg"></i> {{ __('New Entry') }}</a>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-nowrap align-middle mb-0">
                    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Reference') }}</th><th>{{ __('Type') }}</th><th>{{ __('Description') }}</th><th>{{ __('Branch') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('By') }}</th></tr></thead>
                    <tbody>
                        @forelse ($entries as $entry)
                            <tr>
                                <td>{{ $entry->entry_date->translatedFormat('d M Y') }}</td>
                                <td><a href="{{ route('accounting.journal.show', $entry) }}" class="fw-semibold">{{ $entry->reference }}</a></td>
                                <td><x-badge :status="$entry->type" /></td>
                                <td class="text-wrap">{{ \Illuminate\Support\Str::limit($entry->description, 70) }}</td>
                                <td>{{ $entry->branch?->name ?? '—' }}</td>
                                <td class="text-end fw-semibold">@money($entry->lines_sum_debit, $entry->currency)</td>
                                <td>{{ $entry->creator?->name ?? __('System') }}</td>
                            </tr>
                        @empty
                            <x-empty-state colspan="7" icon="journal" :title="__('No journal entries found')" />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($entries->hasPages())
            <div class="card-footer">{{ $entries->links() }}</div>
        @endif
    </div>
</x-layouts.admin>
