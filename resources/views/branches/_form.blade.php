<div class="card mb-4" style="max-width: 760px">
    <div class="card-body">
        <div class="row">
            <x-form.input name="name" :label="__('Branch name')" :value="$branch->name" required wrapper="col-md-8 mb-3" />
            <x-form.input name="code" :label="__('Code')" :value="$branch->code" required wrapper="col-md-4 mb-3" placeholder="PP01" />
            <x-form.input name="phone" :label="__('Phone')" :value="$branch->phone" wrapper="col-md-6 mb-3" />
            <x-form.input name="email" type="email" :label="__('Email')" :value="$branch->email" wrapper="col-md-6 mb-3" />
            <x-form.input name="address" :label="__('Address')" :value="$branch->address" wrapper="col-12 mb-3" />
            <x-form.select name="manager_id" :label="__('Branch manager')" :value="$branch->manager_id" :options="$managers" :placeholder="__('None')" wrapper="col-md-6 mb-3" />
        </div>
        <x-form.switch name="is_active" :label="__('Branch is active')" :checked="$branch->is_active" />
    </div>
    <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> {{ __('Save Branch') }}</button>
        <a href="{{ route('branches.index') }}" class="btn btn-light">{{ __('Cancel') }}</a>
    </div>
</div>
