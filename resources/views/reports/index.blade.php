<x-layouts.admin :title="__('Reports')" :breadcrumbs="['Reports']">
    <div class="row">
        @foreach ([
            ['Collections', 'Repayments received in a period, split into principal, interest and penalty.', 'cash-stack', 'success', route('reports.collections')],
            ['Disbursements', 'Loans released in a period with fees and expected interest.', 'send-check', 'primary', route('reports.disbursements')],
            ['Arrears / Aging', 'Overdue loans grouped by 1–30, 31–60, 61–90 and 90+ days.', 'exclamation-triangle', 'danger', route('reports.arrears')],
            ['Portfolio', 'Performance summary for every loan product.', 'pie-chart', 'info', route('reports.portfolio')],
        ] as [$title, $text, $icon, $color, $url])
            <div class="col-md-6 col-xl-3">
                <a href="{{ $url }}" class="card mb-4 text-decoration-none h-100">
                    <div class="card-body">
                        <span class="info-box-icon text-bg-{{ $color }} rounded-3 d-inline-flex p-3 mb-3"><i class="bi bi-{{ $icon }} fs-4"></i></span>
                        <h5 class="text-body">{{ __($title) }}</h5>
                        <p class="text-secondary small mb-0">{{ __($text) }}</p>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
</x-layouts.admin>
