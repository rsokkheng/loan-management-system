@php
    $householdIncome = (float) $borrower->monthly_income + (float) $borrower->familyMembers->sum('monthly_income');
    $defaultCurrency = app(\App\Services\CurrencyContext::class)->default();
@endphp

<div class="card mb-4">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title mb-0">{{ __('Family Members') }}</h3>
        <div class="ms-auto small text-secondary">
            {{ __('Household income') }}: <strong class="text-body">@money($householdIncome, $defaultCurrency)</strong>
            · {{ __('Dependents') }}: <strong class="text-body">{{ $borrower->familyMembers->where('is_dependent', true)->count() }}</strong>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover table-nowrap align-middle mb-0">
                <thead class="table-light"><tr><th>{{ __('Name') }}</th><th>{{ __('Relationship') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Occupation') }}</th><th class="text-end">{{ __('Monthly income') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($borrower->familyMembers as $member)
                        <tr>
                            <td class="fw-semibold">
                                {{ $member->full_name }}
                                @if ($member->is_dependent)<span class="badge text-bg-light border ms-1">{{ __('Dependent') }}</span>@endif
                                @if ($member->date_of_birth)<div class="small text-secondary fw-normal">{{ __(':age years', ['age' => $member->date_of_birth->age]) }}</div>@endif
                            </td>
                            <td>{{ $member->relationship->label() }}</td>
                            <td>{{ $member->phone ?: '—' }}</td>
                            <td>{{ $member->occupation ?: '—' }}</td>
                            <td class="text-end">{{ $member->monthly_income !== null ? \App\Enums\Currency::display($member->monthly_income, $defaultCurrency) : '—' }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('family-members.destroy', $member) }}" data-confirm="{{ __('Remove this family member?') }}">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" title="{{ __('Remove') }}"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state colspan="6" icon="people" :title="__('No family members recorded')" />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer">
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#familyMemberModal"><i class="bi bi-person-plus me-1"></i> {{ __('Add Family Member') }}</button>
    </div>
</div>

@push('modals')
    <div class="modal fade" id="familyMemberModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form method="POST" action="{{ route('borrowers.family-members.store', $borrower) }}" class="modal-content">
                @csrf
                <input type="hidden" name="_modal" value="familyMemberModal">
                <div class="modal-header"><h5 class="modal-title">{{ __('Add Family Member') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
                <div class="modal-body">
                    <div class="row">
                        <x-form.input name="full_name" :label="__('Full name')" required wrapper="col-md-6 mb-3" />
                        <x-form.select name="relationship" :label="__('Relationship')" required wrapper="col-md-3 mb-3"
                            :options="collect(\App\Enums\Relationship::cases())->mapWithKeys(fn ($r) => [$r->value => $r->label()])" />
                        <x-form.select name="gender" :label="__('Gender')" wrapper="col-md-3 mb-3" :placeholder="__('Select…')"
                            :options="collect(\App\Enums\Gender::cases())->mapWithKeys(fn ($g) => [$g->value => $g->label()])" />
                        <x-form.input name="date_of_birth" type="date" :label="__('Date of birth')" wrapper="col-md-4 mb-3" />
                        <x-form.input name="phone" :label="__('Phone')" wrapper="col-md-4 mb-3" />
                        <x-form.input name="monthly_income" type="number" step="0.01" min="0" :label="__('Monthly income')" :prepend="$defaultCurrency->symbol()" wrapper="col-md-4 mb-3" />
                        <x-form.input name="occupation" :label="__('Occupation')" wrapper="col-md-6 mb-3" />
                        <div class="col-md-6 mb-3 d-flex align-items-end">
                            <x-form.switch name="is_dependent" :label="__('Financially dependent on the borrower')" wrapper="mb-0" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Add Family Member') }}</button>
                </div>
            </form>
        </div>
    </div>
@endpush
