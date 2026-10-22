@php
    $oldLines = old('lines', [[], []]);
    $accountOptions = $accounts->map(fn ($a) => ['id' => $a->id, 'label' => $a->code.' · '.$a->display_name])->values();
@endphp
<x-layouts.admin :title="__('New Journal Entry')" :breadcrumbs="[__('Journal Entries') => route('accounting.journal.index'), __('New')]">
    <form method="POST" action="{{ route('accounting.journal.store') }}" id="journal-form">
        @csrf
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <x-form.input name="entry_date" type="date" :label="__('Date')" :value="today()->toDateString()" :max="today()->toDateString()" required wrapper="col-md-3 mb-3" />
                    <x-form.input name="description" :label="__('Description')" required wrapper="col-md-4 mb-3" :placeholder="__('e.g. Owner capital injection')" />
                    <x-form.select name="currency" :label="__('Currency')" :value="$reportingCurrency" required wrapper="col-md-2 mb-3"
                        :options="collect(\App\Enums\Currency::cases())->mapWithKeys(fn ($c) => [$c->value => $c->value])" />
                    @if ($canSwitchBranch)
                        <x-form.select name="branch_id" :label="__('Branch')" :value="$currentBranch?->id" :options="$branches" required :placeholder="__('Select…')" wrapper="col-md-3 mb-3" />
                    @endif
                </div>

                @error('lines')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

                <div class="table-responsive">
                    <table class="table align-middle mb-2">
                        <thead class="table-light">
                            <tr><th style="min-width: 260px">{{ __('Account') }}</th><th style="width: 160px" class="text-end">{{ __('Debit') }}</th><th style="width: 160px" class="text-end">{{ __('Credit') }}</th><th>{{ __('Memo') }}</th><th style="width: 40px"></th></tr>
                        </thead>
                        <tbody id="journal-lines">
                            @foreach ($oldLines as $i => $line)
                                <tr>
                                    <td>
                                        <select name="lines[{{ $i }}][account_id]" class="form-select form-select-sm @error("lines.$i.account_id") is-invalid @enderror">
                                            <option value="">{{ __('Select account…') }}</option>
                                            @foreach ($accountOptions as $option)
                                                <option value="{{ $option['id'] }}" @selected((string) ($line['account_id'] ?? '') === (string) $option['id'])>{{ $option['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="number" step="0.01" min="0" name="lines[{{ $i }}][debit]" value="{{ $line['debit'] ?? '' }}" class="form-control form-control-sm text-end" data-debit></td>
                                    <td><input type="number" step="0.01" min="0" name="lines[{{ $i }}][credit]" value="{{ $line['credit'] ?? '' }}" class="form-control form-control-sm text-end" data-credit></td>
                                    <td><input type="text" name="lines[{{ $i }}][memo]" value="{{ $line['memo'] ?? '' }}" class="form-control form-control-sm"></td>
                                    <td><button type="button" class="btn btn-sm btn-link text-danger" data-remove-line><i class="bi bi-x-lg"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="fw-semibold">
                                <td><button type="button" class="btn btn-sm btn-outline-secondary" id="add-line"><i class="bi bi-plus-lg me-1"></i> {{ __('Add line') }}</button></td>
                                <td class="text-end" id="total-debit">0.00</td>
                                <td class="text-end" id="total-credit">0.00</td>
                                <td colspan="2"><span id="balance-status" class="badge"></span></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-primary" id="post-entry"><i class="bi bi-check-lg me-1"></i> {{ __('Post Entry') }}</button>
                <a href="{{ route('accounting.journal.index') }}" class="btn btn-light">{{ __('Cancel') }}</a>
            </div>
        </div>
    </form>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const body = document.getElementById('journal-lines');
                const labels = { balanced: @js(__('Balanced')), off: @js(__('Out of balance by')) };

                const recalc = () => {
                    const sum = (selector) => [...body.querySelectorAll(selector)].reduce((total, input) => total + (parseFloat(input.value) || 0), 0);
                    const debit = sum('[data-debit]');
                    const credit = sum('[data-credit]');
                    document.getElementById('total-debit').textContent = debit.toFixed(2);
                    document.getElementById('total-credit').textContent = credit.toFixed(2);
                    const status = document.getElementById('balance-status');
                    const diff = Math.abs(debit - credit);
                    const balanced = diff < 0.005 && debit > 0;
                    status.className = 'badge ' + (balanced ? 'text-bg-success' : 'text-bg-warning');
                    status.textContent = balanced ? labels.balanced : `${labels.off} ${diff.toFixed(2)}`;
                };

                const renumber = () => body.querySelectorAll('tr').forEach((row, index) => {
                    row.querySelectorAll('[name]').forEach((input) => {
                        input.name = input.name.replace(/lines\[\d+\]/, `lines[${index}]`);
                    });
                });

                document.getElementById('add-line').addEventListener('click', () => {
                    const row = body.querySelector('tr').cloneNode(true);
                    row.querySelectorAll('input').forEach((input) => (input.value = ''));
                    row.querySelector('select').value = '';
                    row.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
                    body.appendChild(row);
                    renumber();
                });

                body.addEventListener('click', (event) => {
                    const button = event.target.closest('[data-remove-line]');
                    if (button && body.querySelectorAll('tr').length > 2) {
                        button.closest('tr').remove();
                        renumber();
                        recalc();
                    }
                });

                // Typing a debit clears the credit on the same line, and vice versa.
                body.addEventListener('input', (event) => {
                    const row = event.target.closest('tr');
                    if (event.target.matches('[data-debit]') && event.target.value) row.querySelector('[data-credit]').value = '';
                    if (event.target.matches('[data-credit]') && event.target.value) row.querySelector('[data-debit]').value = '';
                    recalc();
                });

                recalc();
            });
        </script>
    @endpush
</x-layouts.admin>
