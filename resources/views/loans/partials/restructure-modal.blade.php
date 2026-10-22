@php
    $unpaid = $loan->installments->filter(fn ($i) => $i->status !== \App\Enums\InstallmentStatus::Paid);
    $unpaidPrincipal = $unpaid->sum(fn ($i) => (float) $i->principal_due - (float) $i->principal_paid);
    $arrearsInterest = $unpaid->filter(fn ($i) => $i->is_arrears || $i->due_date->lte(today()))->sum(fn ($i) => (float) $i->interest_due - (float) $i->interest_paid);
    $unpaidPenalty = $unpaid->sum(fn ($i) => (float) $i->penalty_due - (float) $i->penalty_paid);
@endphp

<div class="modal fade" id="restructureModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ route('loans.restructure', $loan) }}" class="modal-content" data-confirm="{{ __('Replace the remaining schedule of :reference with a new one?', ['reference' => $loan->reference]) }}">
            @csrf
            <div class="modal-header"><h5 class="modal-title">{{ __('Restructure Loan') }} · {{ $loan->reference }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row text-center g-2 mb-3">
                    <div class="col-4"><div class="stat-label">{{ __('Unpaid principal') }}</div><div class="fw-semibold">@money($unpaidPrincipal, $loan->currency)</div><div class="small text-secondary">{{ __('rescheduled') }}</div></div>
                    <div class="col-4"><div class="stat-label">{{ __('Overdue interest') }}</div><div class="fw-semibold">@money($arrearsInterest, $loan->currency)</div><div class="small text-secondary">{{ __('carried as arrears') }}</div></div>
                    <div class="col-4"><div class="stat-label">{{ __('Unpaid penalty') }}</div><div class="fw-semibold">@money($unpaidPenalty, $loan->currency)</div><div class="small text-secondary">{{ __('carried or waived') }}</div></div>
                </div>
                <div class="row">
                    <x-form.input name="term" type="number" min="1" :label="__('New number of installments')" :value="$unpaid->where('is_arrears', false)->count() ?: 1" required wrapper="col-md-4 mb-3"
                        :help="$loan->repayment_frequency->label()" />
                    <x-form.input name="interest_rate" type="number" step="0.0001" min="0" :label="__('Annual interest rate')" append="%" :value="(float) $loan->interest_rate" required wrapper="col-md-4 mb-3" />
                    <x-form.input name="first_repayment_date" type="date" :label="__('First new due date')" :value="$loan->repayment_frequency->addPeriods(today(), 1)->toDateString()" :min="today()->addDay()->toDateString()" required wrapper="col-md-4 mb-3" />
                </div>
                @if ($unpaidPenalty > 0)
                    <x-form.switch name="waive_penalty" :label="__('Waive unpaid penalty (:amount)', ['amount' => $loan->currency->format($unpaidPenalty)])" />
                @endif
                <x-form.textarea name="reason" :label="__('Reason for restructuring')" rows="2" required :placeholder="__('e.g. Business affected by flooding; income reduced for 3 months')" />
                <p class="small text-secondary mb-0">{{ __('Installments with no payments are replaced. Partly paid installments are closed at the amount already paid. Overdue interest and any penalty kept are due together with the first new installment.') }}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-calendar2-range me-1"></i> {{ __('Restructure') }}</button>
            </div>
        </form>
    </div>
</div>
