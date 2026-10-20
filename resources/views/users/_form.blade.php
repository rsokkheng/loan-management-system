@php $isSelf = $user->exists && $user->is(auth()->user()); @endphp
<div class="card mb-4" style="max-width: 760px">
    <div class="card-body">
        <div class="row">
            <x-form.input name="name" :label="__('Full name')" :value="$user->name" required wrapper="col-md-6 mb-3" />
            <x-form.input name="email" type="email" :label="__('Email')" :value="$user->email" required wrapper="col-md-6 mb-3" />
            <x-form.input name="phone" :label="__('Phone')" :value="$user->phone" wrapper="col-md-6 mb-3" />
            <x-form.select name="role" :label="__('Role')" :value="$user->role" required wrapper="col-md-6 mb-3" :disabled="$isSelf"
                :options="collect($roles)->mapWithKeys(fn ($r) => [$r->value => $r->label()])"
                :help="$isSelf ? 'You cannot change your own role.' : 'Admins manage everything; Managers approve and disburse; Loan Officers handle borrowers, applications and payments.'" />
            <x-form.select name="branch_id" :label="__('Branch')" :value="$user->branch_id" :options="$branches" :placeholder="__('All branches (head office)')" wrapper="col-md-6 mb-3" :disabled="$isSelf"
                :help="__('Staff assigned to a branch only see that branch. Leave empty for head-office staff.')" />
            <x-form.select name="locale" :label="__('Language')" :value="$user->locale" :options="\App\Http\Middleware\SetLocale::LOCALES" wrapper="col-md-6 mb-3" />
            <x-form.input name="password" type="password" :label="__('Password')" :required="! $user->exists" wrapper="col-md-6 mb-3" autocomplete="new-password"
                :help="$user->exists ? 'Leave blank to keep the current password.' : null" />
            <x-form.input name="password_confirmation" type="password" :label="__('Confirm password')" :required="! $user->exists" wrapper="col-md-6 mb-3" autocomplete="new-password" />
        </div>
        @unless ($isSelf)
            <x-form.switch name="is_active" :label="__('Account active')" :checked="$user->is_active" :help="__('Disabled users cannot sign in.')" />
        @endunless
    </div>
    <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> {{ __('Save User') }}</button>
        <a href="{{ route('users.index') }}" class="btn btn-light">{{ __('Cancel') }}</a>
    </div>
</div>
