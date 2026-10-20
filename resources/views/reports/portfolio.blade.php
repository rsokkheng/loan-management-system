<x-layouts.admin :title="__('Portfolio Summary')" :breadcrumbs="['Reports' => route('reports.index'), 'Portfolio']">
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">{{ __('Performance by Product') }}</h3>
            <div class="card-tools"><button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></button></div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-nowrap align-middle mb-0">
                    <thead class="table-light"><tr><th>{{ __('Product') }}</th><th class="text-center">{{ __('Active') }}</th><th class="text-center">{{ __('Closed') }}</th><th class="text-end">{{ __('Disbursed') }}</th><th class="text-end">{{ __('Expected Interest') }}</th><th class="text-end">{{ __('Collected') }}</th><th class="text-end">{{ __('Outstanding') }}</th></tr></thead>
                    <tbody>
                        @forelse ($products as $product)
                            <tr>
                                <td class="fw-semibold">{{ $product->name }} <span class="text-secondary small">({{ $product->code }})</span></td>
                                <td class="text-center">{{ $product->active_count }}</td>
                                <td class="text-center">{{ $product->closed_count }}</td>
                                <td class="text-end">@money($product->disbursed_total ?? 0)</td>
                                <td class="text-end">@money($product->interest_total ?? 0)</td>
                                <td class="text-end">@money($product->collected_total ?? 0)</td>
                                <td class="text-end fw-semibold">@money($outstanding[$product->id] ?? 0)</td>
                            </tr>
                        @empty
                            <x-empty-state colspan="7" icon="box-seam" :title="__('No products')" />
                        @endforelse
                    </tbody>
                    @if ($products->isNotEmpty())
                        <tfoot class="table-light fw-semibold">
                            <tr>
                                <td>{{ __('Total') }}</td>
                                <td class="text-center">{{ $products->sum('active_count') }}</td>
                                <td class="text-center">{{ $products->sum('closed_count') }}</td>
                                <td class="text-end">@money($products->sum('disbursed_total'))</td>
                                <td class="text-end">@money($products->sum('interest_total'))</td>
                                <td class="text-end">@money($products->sum('collected_total'))</td>
                                <td class="text-end">@money($outstanding->sum())</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</x-layouts.admin>
