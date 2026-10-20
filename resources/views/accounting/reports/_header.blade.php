{{-- Statement heading: company, branch and period. Expects $period. --}}
<div class="text-center mb-4">
    <h4 class="mb-0">{{ $appSettings['company_name'] }}</h4>
    <div class="fw-semibold">{{ $statementTitle }}</div>
    <div class="text-secondary small">{{ $currentBranch?->name ?? __('All Branches') }} · {{ $period }}</div>
</div>
