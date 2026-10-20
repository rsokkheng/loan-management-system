<x-layouts.admin :title="__('Loan Products')" :breadcrumbs="['Loan Products']">
    <div class="d-flex justify-content-end mb-3">
        <a href="{{ route('loan-products.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> {{ __('New Product') }}</a>
    </div>

    <div class="row">
        @forelse ($products as $product)
            <div class="col-md-6 col-xl-4">
                <div class="card mb-4 {{ $product->is_active ? '' : 'opacity-75' }}">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title fw-semibold mb-0">{{ $product->name }}</h3>
                        <span class="badge {{ $product->is_active ? 'text-bg-success' : 'text-bg-secondary' }} ms-auto">{{ $product->is_active ? __('Active') : __('Inactive') }}</span>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-baseline gap-2 mb-2">
                            <span class="display-6 fw-semibold">{{ rtrim(rtrim(number_format((float) $product->interest_rate, 2), '0'), '.') }}%</span>
                            <span class="text-secondary">{{ __('per year') }} · {{ $product->interest_method->label() }}</span>
                        </div>
                        @if ($product->description)
                            <p class="text-secondary small">{{ $product->description }}</p>
                        @endif
                        <dl class="row small mb-0">
                            <dt class="col-6 text-secondary fw-normal">{{ __('Code') }}</dt><dd class="col-6"><code>{{ $product->code }}</code></dd>
                            <dt class="col-6 text-secondary fw-normal">{{ __('Repayment') }}</dt><dd class="col-6">{{ $product->repayment_frequency->label() }}</dd>
                            <dt class="col-6 text-secondary fw-normal">{{ __('Amount') }}</dt><dd class="col-6">@money($product->min_amount) – @money($product->max_amount)</dd>
                            <dt class="col-6 text-secondary fw-normal">{{ __('Term') }}</dt><dd class="col-6">{{ __(':min – :max installments', ['min' => $product->min_term, 'max' => $product->max_term]) }}</dd>
                            <dt class="col-6 text-secondary fw-normal">{{ __('Processing fee') }}</dt><dd class="col-6">{{ $product->processing_fee_percent }}%</dd>
                            <dt class="col-6 text-secondary fw-normal">{{ __('Late penalty') }}</dt><dd class="col-6">{{ __(':percent% after :days day(s)', ['percent' => (float) $product->late_penalty_percent, 'days' => $product->grace_period_days]) }}</dd>
                            <dt class="col-6 text-secondary fw-normal">{{ __('Loans issued') }}</dt><dd class="col-6 mb-0">{{ $product->loans_count }}</dd>
                        </dl>
                    </div>
                    <div class="card-footer d-flex gap-2">
                        <a href="{{ route('loan-products.edit', $product) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i> {{ __('Edit') }}</a>
                        @if ($product->loans_count === 0)
                            <form method="POST" action="{{ route('loan-products.destroy', $product) }}" data-confirm="Delete {{ $product->name }}?">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card"><div class="card-body">
                    <x-empty-state icon="box-seam" :title="__('No loan products yet')">
                        <a href="{{ route('loan-products.create') }}">{{ __('Create your first product') }}</a>
                    </x-empty-state>
                </div></div>
            </div>
        @endforelse
    </div>
</x-layouts.admin>
