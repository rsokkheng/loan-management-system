@php $locked = $account->exists && $account->isSystem(); @endphp
<div class="card mb-4" style="max-width: 760px">
    <div class="card-body">
        @if ($locked)
            <div class="alert alert-info small"><i class="bi bi-gear-fill me-1"></i> {{ __('This is a system account used by automatic loan postings. Only its code, names and description can be changed.') }}</div>
        @endif
        <div class="row">
            <x-form.input name="code" :label="__('Code')" :value="$account->code" required wrapper="col-md-3 mb-3" />
            <x-form.input name="name" :label="__('Name (English)')" :value="$account->name" required wrapper="col-md-5 mb-3" />
            <x-form.input name="name_km" :label="__('Name (Khmer)')" :value="$account->name_km" wrapper="col-md-4 mb-3" />
            <x-form.select name="type" :label="__('Type')" :value="$account->type" required wrapper="col-md-6 mb-3" :disabled="$locked"
                :options="collect($types)->mapWithKeys(fn ($t) => [$t->value => $t->label()])" />
            <x-form.select name="parent_id" :label="__('Parent account')" :value="$account->parent_id" :placeholder="__('None')" wrapper="col-md-6 mb-3"
                :options="$parents->mapWithKeys(fn ($p) => [$p->id => $p->code.' · '.$p->display_name])" />
            <x-form.textarea name="description" :label="__('Description')" :value="$account->description" rows="2" wrapper="col-12 mb-3" />
        </div>
        @unless ($locked)
            <x-form.switch name="is_cash" :label="__('Cash or bank account')" :checked="$account->is_cash" :help="__('Cash accounts can be used to pay expenses and receive income.')" />
            <x-form.switch name="is_active" :label="__('Active')" :checked="$account->is_active" />
        @endunless
    </div>
    <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> {{ __('Save Account') }}</button>
        <a href="{{ route('accounting.accounts.index') }}" class="btn btn-light">{{ __('Cancel') }}</a>
        @if ($account->exists && ! $locked)
            <button type="submit" form="delete-account" class="btn btn-outline-danger ms-auto"><i class="bi bi-trash"></i></button>
        @endif
    </div>
</div>
