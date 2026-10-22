@php $editable = $loan->securityIsEditable(); @endphp

@if ($loan->product->requires_guarantor && $loan->guarantors->isEmpty())
    <div class="alert alert-warning small m-3 mb-0"><i class="bi bi-exclamation-triangle me-1"></i> {{ __('This product requires at least one guarantor before approval.') }}</div>
@endif

<div class="table-responsive">
    <table class="table table-sm table-hover table-nowrap align-middle mb-0">
        <thead class="table-light"><tr><th>{{ __('Name') }}</th><th>{{ __('Relationship') }}</th><th>{{ __('National ID') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Occupation') }}</th><th class="text-end">{{ __('Monthly income') }}</th><th></th></tr></thead>
        <tbody>
            @forelse ($loan->guarantors as $guarantor)
                <tr>
                    <td class="fw-semibold">{{ $guarantor->full_name }}@if ($guarantor->address)<div class="small text-secondary fw-normal">{{ $guarantor->address }}</div>@endif</td>
                    <td>{{ $guarantor->relationship->label() }}</td>
                    <td>{{ $guarantor->national_id ?: '—' }}</td>
                    <td>{{ $guarantor->phone }}</td>
                    <td>{{ $guarantor->occupation ?: '—' }}{{ $guarantor->employer ? ' · '.$guarantor->employer : '' }}</td>
                    <td class="text-end">{{ $guarantor->monthly_income !== null ? \App\Enums\Currency::display($guarantor->monthly_income, $loan->currency) : '—' }}</td>
                    <td class="text-end">
                        @if ($editable)
                            <form method="POST" action="{{ route('guarantors.destroy', $guarantor) }}" data-confirm="{{ __('Remove this guarantor?') }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="{{ __('Remove') }}"><i class="bi bi-trash"></i></button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <x-empty-state colspan="7" icon="person-check" :title="__('No guarantors')" />
            @endforelse
        </tbody>
    </table>
</div>

@if ($editable)
    <div class="p-3 border-top">
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#guarantorModal"><i class="bi bi-person-plus me-1"></i> {{ __('Add Guarantor') }}</button>
    </div>

    @push('modals')
        <div class="modal fade" id="guarantorModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form method="POST" action="{{ route('loans.guarantors.store', $loan) }}" class="modal-content">
                    @csrf
                    <input type="hidden" name="_modal" value="guarantorModal">
                    <div class="modal-header"><h5 class="modal-title">{{ __('Add Guarantor') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
                    <div class="modal-body">
                        <div class="row">
                            <x-form.input name="full_name" :label="__('Full name')" required wrapper="col-md-6 mb-3" />
                            <x-form.select name="relationship" :label="__('Relationship')" required wrapper="col-md-3 mb-3"
                                :options="collect($relationships)->mapWithKeys(fn ($r) => [$r->value => $r->label()])" />
                            <x-form.select name="gender" :label="__('Gender')" wrapper="col-md-3 mb-3" :placeholder="__('Select…')"
                                :options="collect(\App\Enums\Gender::cases())->mapWithKeys(fn ($g) => [$g->value => $g->label()])" />
                            <x-form.input name="national_id" :label="__('National ID')" wrapper="col-md-4 mb-3" />
                            <x-form.input name="phone" :label="__('Phone')" required wrapper="col-md-4 mb-3" />
                            <x-form.input name="monthly_income" type="number" :step="$loan->currency->step()" min="0" :label="__('Monthly income')" :prepend="$loan->currency->symbol()" wrapper="col-md-4 mb-3" />
                            <x-form.input name="occupation" :label="__('Occupation')" wrapper="col-md-6 mb-3" />
                            <x-form.input name="employer" :label="__('Employer')" wrapper="col-md-6 mb-3" />
                            <x-form.input name="address" :label="__('Address')" wrapper="col-12 mb-3" />
                            <x-form.textarea name="notes" :label="__('Notes')" rows="2" wrapper="col-12 mb-0" />
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Add Guarantor') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endpush
@endif
