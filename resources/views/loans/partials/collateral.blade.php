@php
    use App\Enums\CollateralStatus;
    use App\Enums\LoanStatus;

    $editable = $loan->securityIsEditable();
    $canRelease = in_array($loan->status, [LoanStatus::Closed, LoanStatus::Rejected], true);
    $requiredCoverage = (float) $loan->product->min_collateral_coverage;
    $coverage = $loan->collateralCoverage();
@endphp

<div class="d-flex flex-wrap gap-4 p-3 border-bottom small">
    <div><span class="text-secondary">{{ __('Collateral held') }}</span><br><strong>@money($loan->collaterals->where('status', CollateralStatus::Held)->sum('estimated_value'), $loan->currency)</strong></div>
    <div><span class="text-secondary">{{ __('Coverage of principal') }}</span><br>
        <strong class="{{ $requiredCoverage > 0 && $coverage < $requiredCoverage ? 'text-danger' : 'text-success' }}">{{ $coverage }}%</strong>
        @if ($requiredCoverage > 0)
            <span class="text-secondary">/ {{ __('required :percent%', ['percent' => $requiredCoverage + 0]) }}</span>
        @endif
    </div>
</div>

<div class="table-responsive">
    <table class="table table-sm table-hover table-nowrap align-middle mb-0">
        <thead class="table-light"><tr><th>{{ __('Type') }}</th><th>{{ __('Description') }}</th><th>{{ __('Document no.') }}</th><th>{{ __('Owner') }}</th><th class="text-end">{{ __('Estimated value') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
        <tbody>
            @forelse ($loan->collaterals as $collateral)
                <tr>
                    <td><i class="bi bi-{{ $collateral->type->icon() }} me-1 text-secondary"></i>{{ $collateral->type->label() }}</td>
                    <td>
                        {{ $collateral->description }}
                        @if ($collateral->location)<div class="small text-secondary">{{ $collateral->location }}</div>@endif
                    </td>
                    <td>{{ $collateral->document_number ?: '—' }}</td>
                    <td>{{ $collateral->owner_name ?: '—' }}</td>
                    <td class="text-end">
                        @money($collateral->estimated_value, $loan->currency)
                        @if ($collateral->valuation_date)<div class="small text-secondary">{{ $collateral->valuation_date->translatedFormat('d M Y') }}</div>@endif
                    </td>
                    <td>
                        <x-badge :status="$collateral->status" />
                        @if ($collateral->released_at)
                            <div class="small text-secondary">{{ $collateral->released_at->translatedFormat('d M Y') }} · {{ $collateral->releaser?->name }}</div>
                        @endif
                    </td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-1">
                            @if ($collateral->file_path)
                                <a href="{{ route('collaterals.document', $collateral) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Download') }}"><i class="bi bi-download"></i></a>
                            @endif
                            @if ($canRelease && $collateral->status === CollateralStatus::Held)
                                <form method="POST" action="{{ route('collaterals.release', $collateral) }}" data-confirm="{{ __('Release this collateral back to its owner?') }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-success"><i class="bi bi-box-arrow-up-right me-1"></i> {{ __('Release') }}</button>
                                </form>
                            @endif
                            @if ($editable)
                                <form method="POST" action="{{ route('collaterals.destroy', $collateral) }}" data-confirm="{{ __('Remove this collateral?') }}">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" title="{{ __('Remove') }}"><i class="bi bi-trash"></i></button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <x-empty-state colspan="7" icon="safe" :title="__('No collateral recorded')" />
            @endforelse
        </tbody>
    </table>
</div>

@if ($editable)
    <div class="p-3 border-top">
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#collateralModal"><i class="bi bi-plus-lg me-1"></i> {{ __('Add Collateral') }}</button>
    </div>

    @push('modals')
        <div class="modal fade" id="collateralModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form method="POST" action="{{ route('loans.collaterals.store', $loan) }}" enctype="multipart/form-data" class="modal-content">
                    @csrf
                    <input type="hidden" name="_modal" value="collateralModal">
                    <div class="modal-header"><h5 class="modal-title">{{ __('Add Collateral') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
                    <div class="modal-body">
                        <div class="row">
                            <x-form.select name="type" :label="__('Type')" required wrapper="col-md-4 mb-3"
                                :options="collect($collateralTypes)->mapWithKeys(fn ($t) => [$t->value => $t->label()])" />
                            <x-form.input name="description" :label="__('Description')" required wrapper="col-md-8 mb-3" :placeholder="__('e.g. Honda Dream 2020, black')" />
                            <x-form.input name="document_number" :label="__('Document no.')" wrapper="col-md-4 mb-3" :placeholder="__('Title / plate number')" />
                            <x-form.input name="owner_name" :label="__('Owner')" wrapper="col-md-4 mb-3" />
                            <x-form.input name="estimated_value" type="number" :step="$loan->currency->step()" min="1" :label="__('Estimated value')" :prepend="$loan->currency->symbol()" required wrapper="col-md-4 mb-3" />
                            <x-form.input name="valuation_date" type="date" :label="__('Valuation date')" :value="today()->toDateString()" :max="today()->toDateString()" wrapper="col-md-4 mb-3" />
                            <x-form.input name="location" :label="__('Location')" wrapper="col-md-8 mb-3" />
                            <x-form.input name="document" type="file" :label="__('Scan of title / ownership document')" wrapper="col-12 mb-0" :help="__('PDF or image up to 5 MB.')" accept=".pdf,.jpg,.jpeg,.png" />
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Add Collateral') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endpush
@endif
