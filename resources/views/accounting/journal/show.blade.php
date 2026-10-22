<x-layouts.admin :title="$entry->reference" :breadcrumbs="[__('Journal Entries') => route('accounting.journal.index'), $entry->reference]">
    <div class="row justify-content-center">
        <div class="col-xl-9">
            <div class="card mb-4">
                <div class="card-header d-flex flex-wrap align-items-center gap-2">
                    <h3 class="card-title mb-0">{{ $entry->reference }}</h3>
                    <x-badge :status="$entry->type" />
                    @if ($entry->reversedBy)
                        <span class="badge text-bg-dark">{{ __('Reversed by') }} {{ $entry->reversedBy->reference }}</span>
                    @endif
                    <div class="ms-auto d-flex gap-2">
                        @if ($entry->canBeReversed() && ! $entry->reversedBy)
                            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#reverseModal"><i class="bi bi-arrow-counterclockwise me-1"></i> {{ __('Reverse') }}</button>
                        @endif
                        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></button>
                    </div>
                </div>
                <div class="card-body">
                    <dl class="row mb-4">
                        <dt class="col-sm-3 text-secondary fw-normal">{{ __('Date') }}</dt><dd class="col-sm-9">{{ $entry->entry_date->translatedFormat('d F Y') }}</dd>
                        <dt class="col-sm-3 text-secondary fw-normal">{{ __('Description') }}</dt><dd class="col-sm-9">{{ $entry->description }}</dd>
                        <dt class="col-sm-3 text-secondary fw-normal">{{ __('Branch') }}</dt><dd class="col-sm-9">{{ $entry->branch?->name ?? '—' }}</dd>
                        <dt class="col-sm-3 text-secondary fw-normal">{{ __('Posted by') }}</dt><dd class="col-sm-9">{{ $entry->creator?->name ?? __('System') }} · {{ $entry->created_at->translatedFormat('d M Y H:i') }}</dd>
                        @if ($entry->source instanceof \App\Models\Loan)
                            <dt class="col-sm-3 text-secondary fw-normal">{{ __('Source') }}</dt><dd class="col-sm-9"><a href="{{ route('loans.show', $entry->source) }}">{{ __('Loan') }} {{ $entry->source->reference }}</a></dd>
                        @elseif ($entry->source instanceof \App\Models\Payment)
                            <dt class="col-sm-3 text-secondary fw-normal">{{ __('Source') }}</dt><dd class="col-sm-9"><a href="{{ route('payments.show', $entry->source) }}">{{ __('Receipt') }} {{ $entry->source->receipt_number }}</a></dd>
                        @elseif ($entry->reversalOf)
                            <dt class="col-sm-3 text-secondary fw-normal">{{ __('Reverses') }}</dt><dd class="col-sm-9"><a href="{{ route('accounting.journal.show', $entry->reversalOf) }}">{{ $entry->reversalOf->reference }}</a></dd>
                        @endif
                    </dl>

                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>{{ __('Account') }}</th><th>{{ __('Memo') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th></tr></thead>
                        <tbody>
                            @foreach ($entry->lines as $line)
                                <tr>
                                    <td class="{{ (float) $line->credit > 0 ? 'ps-4' : '' }}">
                                        <a href="{{ route('accounting.accounts.show', $line->account) }}" class="text-decoration-none"><code>{{ $line->account->code }}</code> {{ $line->account->display_name }}</a>
                                    </td>
                                    <td class="text-secondary">{{ $line->memo }}</td>
                                    <td class="text-end">{{ (float) $line->debit > 0 ? number_format((float) $line->debit, 2) : '' }}</td>
                                    <td class="text-end">{{ (float) $line->credit > 0 ? number_format((float) $line->credit, 2) : '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light fw-semibold">
                            <tr>
                                <td colspan="2">{{ __('Total') }}</td>
                                <td class="text-end">@money($entry->lines->sum('debit'), $entry->currency)</td>
                                <td class="text-end">@money($entry->lines->sum('credit'), $entry->currency)</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if ($entry->canBeReversed() && ! $entry->reversedBy)
        <div class="modal fade" id="reverseModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('accounting.journal.reverse', $entry) }}" class="modal-content">
                    @csrf
                    <div class="modal-header"><h5 class="modal-title">{{ __('Reverse Entry') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <p class="small text-secondary">{{ __('A new entry with opposite amounts will be posted today. The original stays in the ledger for the audit trail.') }}</p>
                        <x-form.input name="reason" :label="__('Reason')" required />
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-danger">{{ __('Reverse') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</x-layouts.admin>
