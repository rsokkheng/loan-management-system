{{-- Shared borrower form fields. Expects: $borrower, $genders --}}
<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header"><h3 class="card-title">{{ __('Personal Information') }}</h3></div>
            <div class="card-body">
                <div class="row">
                    <x-form.input name="first_name" :label="__('First name')" :value="$borrower->first_name" required wrapper="col-md-6 mb-3" />
                    <x-form.input name="last_name" :label="__('Last name')" :value="$borrower->last_name" required wrapper="col-md-6 mb-3" />
                    <x-form.select name="gender" :label="__('Gender')" :value="$borrower->gender" required :placeholder="__('Select…')" wrapper="col-md-4 mb-3"
                        :options="collect($genders)->mapWithKeys(fn ($g) => [$g->value => $g->label()])" />
                    <x-form.input name="date_of_birth" type="date" :label="__('Date of birth')" :value="$borrower->date_of_birth?->toDateString()" wrapper="col-md-4 mb-3" />
                    <x-form.input name="national_id" :label="__('National ID / Passport')" :value="$borrower->national_id" required wrapper="col-md-4 mb-3" />
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h3 class="card-title">{{ __('Contact & Address') }}</h3></div>
            <div class="card-body">
                <div class="row">
                    <x-form.input name="phone" :label="__('Phone')" :value="$borrower->phone" required wrapper="col-md-6 mb-3" />
                    <x-form.input name="email" type="email" :label="__('Email')" :value="$borrower->email" wrapper="col-md-6 mb-3" />
                </div>
                <x-address-fields :model="$borrower" required />
                <div class="row">
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h3 class="card-title">{{ __('Employment & Income') }}</h3></div>
            <div class="card-body">
                <div class="row">
                    <x-form.input name="occupation" :label="__('Occupation')" :value="$borrower->occupation" wrapper="col-md-4 mb-3" />
                    <x-form.input name="employer" :label="__('Employer / Business')" :value="$borrower->employer" wrapper="col-md-4 mb-3" />
                    <x-form.input name="monthly_income" type="number" step="0.01" min="0" :label="__('Monthly income')" :prepend="$appSettings['currency_symbol']"
                        :value="$borrower->monthly_income" wrapper="col-md-4 mb-3" />
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header"><h3 class="card-title">{{ __('Photo') }}</h3></div>
            <div class="card-body text-center">
                @if ($borrower->exists)
                    <x-avatar :borrower="$borrower" size="lg" class="mb-3" />
                @endif
                <x-form.input name="photo" type="file" accept="image/*" :help="__('JPG or PNG, max 2 MB.')" />
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h3 class="card-title">{{ __('Status & Notes') }}</h3></div>
            <div class="card-body">
                @if ($borrower->exists)
                    <div class="mb-3">
                        <label class="form-label">{{ __('Branch') }}</label>
                        <input type="text" class="form-control" value="{{ $borrower->branch?->name ?? '—' }}" disabled>
                    </div>
                @elseif ($canSwitchBranch)
                    <x-form.select name="branch_id" :label="__('Branch')" :value="$borrower->branch_id" :options="$branches" required :placeholder="__('Select…')" />
                @endif
                <x-form.switch name="is_blacklisted" :label="__('Blacklisted')" :checked="$borrower->is_blacklisted"
                    :help="__('Blacklisted borrowers cannot apply for new loans.')" />
                <x-form.textarea name="notes" :label="__('Internal notes')" :value="$borrower->notes" rows="4" />
            </div>
        </div>
    </div>
</div>

<div class="d-flex gap-2 mb-4">
    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> {{ __('Save Borrower') }}</button>
    <a href="{{ $borrower->exists ? route('borrowers.show', $borrower) : route('borrowers.index') }}" class="btn btn-light">{{ __('Cancel') }}</a>
</div>
