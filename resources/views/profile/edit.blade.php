<x-layouts.admin :title="__('My Profile')" :breadcrumbs="['Profile']">
    <div class="row">
        <div class="col-lg-6">
            <form method="POST" action="{{ route('profile.update') }}" class="card mb-4">
                @csrf
                @method('PUT')
                <div class="card-header"><h3 class="card-title">{{ __('Profile Information') }}</h3></div>
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <span class="avatar avatar-lg">{{ $user->initials() }}</span>
                        <div>
                            <div class="fw-semibold fs-5">{{ $user->name }}</div>
                            <x-badge :status="$user->role" />
                        </div>
                    </div>
                    <x-form.input name="name" :label="__('Name')" :value="$user->name" required />
                    <x-form.input name="email" type="email" :label="__('Email')" :value="$user->email" required />
                    <x-form.input name="phone" :label="__('Phone')" :value="$user->phone" />
                    <x-form.select name="locale" :label="__('Language')" :value="$user->locale" :options="\App\Http\Middleware\SetLocale::LOCALES" />
                    <div class="small text-secondary"><i class="bi bi-building me-1"></i>{{ __('Branch') }}: {{ $user->branch?->name ?? __('All Branches') }}</div>
                </div>
                <div class="card-footer"><button class="btn btn-primary">{{ __('Save Profile') }}</button></div>
            </form>
        </div>
        <div class="col-lg-6">
            <form method="POST" action="{{ route('profile.password') }}" class="card mb-4">
                @csrf
                @method('PUT')
                <div class="card-header"><h3 class="card-title">{{ __('Change Password') }}</h3></div>
                <div class="card-body">
                    @foreach (['current_password' => 'Current password', 'password' => 'New password', 'password_confirmation' => 'Confirm new password'] as $field => $label)
                        <div class="mb-3">
                            <label for="{{ $field }}" class="form-label">{{ __($label) }}</label>
                            <input type="password" name="{{ $field }}" id="{{ $field }}" class="form-control @error($field, 'password') is-invalid @enderror" required autocomplete="{{ $field === 'current_password' ? 'current-password' : 'new-password' }}">
                            @error($field, 'password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @endforeach
                </div>
                <div class="card-footer"><button class="btn btn-warning">{{ __('Update Password') }}</button></div>
            </form>
        </div>
    </div>
</x-layouts.admin>
