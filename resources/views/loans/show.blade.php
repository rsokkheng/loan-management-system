@php
    use App\Enums\LoanStatus;

    $isActive = $loan->status === LoanStatus::Active;
    $schedule = $loan->installments;
    $nextInstallment = $schedule->first(fn ($i) => $i->status !== \App\Enums\InstallmentStatus::Paid);
@endphp

<x-layouts.admin :title="__('Loan').' '.$loan->reference" :breadcrumbs="['Loans' => route('loans.index'), $loan->reference]">
    {{-- Header --}}
    <div class="card mb-4">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <x-avatar :borrower="$loan->borrower" />
            <div class="me-auto">
                <div class="d-flex align-items-center gap-2">
                    <h4 class="mb-0">{{ $loan->reference }}</h4>
                    <x-badge :status="$loan->status" />
                    @if ($isActive && $overdueBalance > 0)
                        <span class="badge rounded-pill text-bg-danger"><i class="bi bi-exclamation-triangle me-1"></i>{{ __('Overdue') }}</span>
                    @endif
                </div>
                <div class="text-secondary">
                    <a href="{{ route('borrowers.show', $loan->borrower) }}" class="text-reset">{{ $loan->borrower->full_name }}</a>
                    · {{ $loan->borrower->phone }} · {{ $loan->product->name }}
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                @if ($loan->status === LoanStatus::Pending)
                    <a href="{{ route('loans.edit', $loan) }}" class="btn btn-outline-secondary"><i class="bi bi-pencil me-1"></i> {{ __('Edit') }}</a>
                    @can('approve-loans')
                        <form method="POST" action="{{ route('loans.approve', $loan) }}" data-confirm="Approve loan {{ $loan->reference }} for {{ $loan->borrower->full_name }}?">
                            @csrf
                            <button class="btn btn-success"><i class="bi bi-check2-circle me-1"></i> {{ __('Approve') }}</button>
                        </form>
                    @endcan
                @endif

                @if ($loan->status === LoanStatus::Approved)
                    @can('disburse-loans')
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#disburseModal"><i class="bi bi-send-check me-1"></i> {{ __('Disburse') }}</button>
                    @endcan
                @endif

                @if (in_array($loan->status, [LoanStatus::Pending, LoanStatus::Approved], true))
                    @can('approve-loans')
                        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal"><i class="bi bi-x-circle me-1"></i> {{ __('Reject') }}</button>
                    @endcan
                @endif

                @if ($isActive)
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#paymentModal"><i class="bi bi-cash-coin me-1"></i> {{ __('Receive Payment') }}</button>
                    @can('write-off-loans')
                        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#writeOffModal"><i class="bi bi-slash-circle me-1"></i> {{ __('Write Off') }}</button>
                    @endcan
                @endif

                @if ($loan->disbursed_at)
                    <a href="{{ route('loans.print', $loan) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i> {{ __('Print') }}</a>
                @endif

                @if (in_array($loan->status, [LoanStatus::Pending, LoanStatus::Rejected], true))
                    @can('delete-records')
                        <form method="POST" action="{{ route('loans.destroy', $loan) }}" data-confirm="Delete application {{ $loan->reference }}?">
                            @csrf @method('DELETE')
                            <button class="btn btn-outline-danger" title="{{ __('Delete') }}"><i class="bi bi-trash"></i></button>
                        </form>
                    @endcan
                @endif
            </div>
        </div>

        @if ($loan->status === LoanStatus::Rejected)
            <div class="card-footer bg-danger-subtle text-danger-emphasis">
                <strong>{{ __('Rejected') }}</strong> · {{ __(':name on :date', ['name' => $loan->rejecter?->name, 'date' => $loan->rejected_at?->translatedFormat('d M Y')]) }}: {{ $loan->rejection_reason }}
            </div>
        @elseif ($loan->status === LoanStatus::Approved)
            <div class="card-footer bg-info-subtle text-info-emphasis">
                {{ __('Approved by :name on :date. Awaiting disbursement.', ['name' => $loan->approver?->name, 'date' => $loan->approved_at?->translatedFormat('d M Y H:i')]) }}
            </div>
        @endif
    </div>

    {{-- Financial summary --}}
    <div class="row">
        @foreach ([
            ['Principal', $loan->principal_amount, 'primary'],
            ['Total Interest', $loan->total_interest, 'info'],
            ['Total Payable', (float) $loan->total_payable + (float) $loan->total_penalty, 'secondary'],
            ['Amount Paid', $loan->amount_paid, 'success'],
            ['Outstanding', $loan->disbursed_at ? $loan->outstanding_balance : 0, 'warning'],
            ['Overdue', $overdueBalance, 'danger'],
        ] as [$label, $value, $color])
            <div class="col-6 col-md-4 col-xl-2">
                <div class="card mb-4 border-start border-4 border-{{ $color }}">
                    <div class="card-body py-3">
                        <div class="stat-label">{{ __($label) }}</div>
                        <div class="fs-5 fw-semibold">@money($value)</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        {{-- Details --}}
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title">{{ __('Loan Details') }}</h3></div>
                <ul class="list-group list-group-flush">
                    @foreach ([
                        'Interest' => (float) $loan->interest_rate.'% '.__('p.a.').' · '.$loan->interest_method->label(),
                        'Repayment' => $loan->term.' × '.$loan->repayment_frequency->label(),
                        'Processing fee' => null,
                        'Net disbursed' => null,
                        'Late penalty' => __(':percent% after :days day(s)', ['percent' => (float) $loan->late_penalty_percent, 'days' => $loan->grace_period_days]),
                        'Purpose' => $loan->purpose ?: '—',
                        'Branch' => $loan->branch?->name ?? '—',
                        'Loan officer' => $loan->officer?->name ?? '—',
                        'Applied' => $loan->application_date->translatedFormat('d M Y'),
                        'Disbursed' => $loan->disbursed_at ? $loan->disbursed_at->translatedFormat('d M Y').' · '.$loan->disbursement_method?->label() : '—',
                        'First repayment' => $loan->first_repayment_date?->translatedFormat('d M Y') ?? '—',
                        'Maturity' => $loan->maturity_date?->translatedFormat('d M Y') ?? '—',
                    ] as $label => $value)
                        <li class="list-group-item d-flex justify-content-between gap-3">
                            <span class="text-secondary">{{ __($label) }}</span>
                            <span class="text-end">
                                @if ($label === 'Processing fee')
                                    @money($loan->processing_fee)
                                @elseif ($label === 'Net disbursed')
                                    @money((float) $loan->principal_amount - (float) $loan->processing_fee)
                                @else
                                    {{ $value }}
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
                @if ($loan->disbursed_at)
                    <div class="card-body">
                        <div class="d-flex justify-content-between small mb-1"><span>{{ __('Repayment progress') }}</span><span>{{ $loan->progress_percent }}%</span></div>
                        <div class="progress" style="height: 8px"><div class="progress-bar bg-success" style="width: {{ $loan->progress_percent }}%"></div></div>
                        @if ($isActive && $nextInstallment)
                            <div class="small text-secondary mt-2">
                                {{ __('Next due:') }} <strong>@money($nextInstallment->balance)</strong> · {{ $nextInstallment->due_date->translatedFormat('d M Y') }}
                            </div>
                        @endif
                    </div>
                @endif
                @if ($loan->notes)
                    <div class="card-footer small"><strong>{{ __('Notes:') }}</strong> {{ $loan->notes }}</div>
                @endif
            </div>
        </div>

        {{-- Schedule & payments --}}
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header p-0 border-bottom-0">
                    <ul class="nav nav-tabs px-2 pt-2" role="tablist">
                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-schedule" type="button">
                            {{ $schedule->isEmpty() ? __('Schedule Preview') : __('Repayment Schedule') }}</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-payments" type="button">
                            {{ __('Payments') }} <span class="badge text-bg-secondary">{{ $loan->payments->count() }}</span></button></li>
                    </ul>
                </div>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tab-schedule">
                        <div class="table-responsive">
                            @if ($schedule->isNotEmpty())
                                <table class="table table-sm table-hover table-nowrap align-middle mb-0">
                                    <thead class="table-light">
                                        <tr><th>#</th><th>{{ __('Due Date') }}</th><th class="text-end">{{ __('Principal') }}</th><th class="text-end">{{ __('Interest') }}</th><th class="text-end">{{ __('Penalty') }}</th><th class="text-end">{{ __('Total') }}</th><th class="text-end">{{ __('Paid') }}</th><th>{{ __('Status') }}</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($schedule as $installment)
                                            <tr class="{{ $installment->isOverdue() ? 'table-danger' : '' }}">
                                                <td>{{ $installment->installment_number }}</td>
                                                <td>
                                                    {{ $installment->due_date->translatedFormat('d M Y') }}
                                                    @if ($installment->isOverdue())
                                                        <span class="badge text-bg-danger">{{ __(':days days late', ['days' => $installment->days_overdue]) }}</span>
                                                    @endif
                                                </td>
                                                <td class="text-end">@money($installment->principal_due)</td>
                                                <td class="text-end">@money($installment->interest_due)</td>
                                                <td class="text-end">
                                                    @if ((float) $installment->penalty_due > 0)
                                                        <span class="text-danger">@money($installment->penalty_due)</span>
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td class="text-end fw-semibold">@money($installment->total_due)</td>
                                                <td class="text-end">@money($installment->total_paid)</td>
                                                <td><x-badge :status="$installment->status" /></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="table-light fw-semibold">
                                        <tr>
                                            <td colspan="2">{{ __('Total') }}</td>
                                            <td class="text-end">@money($schedule->sum('principal_due'))</td>
                                            <td class="text-end">@money($schedule->sum('interest_due'))</td>
                                            <td class="text-end">@money($schedule->sum('penalty_due'))</td>
                                            <td class="text-end">@money($schedule->sum('total_due'))</td>
                                            <td class="text-end">@money($schedule->sum('total_paid'))</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            @elseif ($preview)
                                <div class="alert alert-light border m-3 mb-0 small">
                                    <i class="bi bi-info-circle me-1"></i> {{ __('Indicative schedule assuming the first repayment one period from today. The actual schedule is generated on disbursement.') }}
                                </div>
                                <table class="table table-sm table-hover table-nowrap align-middle mb-0">
                                    <thead class="table-light"><tr><th>#</th><th>{{ __('Due Date') }}</th><th class="text-end">{{ __('Principal') }}</th><th class="text-end">{{ __('Interest') }}</th><th class="text-end">{{ __('Installment') }}</th><th class="text-end">{{ __('Balance') }}</th></tr></thead>
                                    <tbody>
                                        @foreach ($preview->schedule as $line)
                                            <tr>
                                                <td>{{ $line->number }}</td>
                                                <td>{{ $line->dueDate->translatedFormat('d M Y') }}</td>
                                                <td class="text-end">@money($line->principal)</td>
                                                <td class="text-end">@money($line->interest)</td>
                                                <td class="text-end fw-semibold">@money($line->total())</td>
                                                <td class="text-end">@money($line->balanceAfter)</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <x-empty-state icon="calendar-x" :title="__('No schedule')">{{ __('This loan was never disbursed.') }}</x-empty-state>
                            @endif
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab-payments">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover table-nowrap align-middle mb-0">
                                <thead class="table-light"><tr><th>{{ __('Receipt') }}</th><th>{{ __('Date') }}</th><th>{{ __('Method') }}</th><th class="text-end">{{ __('Principal') }}</th><th class="text-end">{{ __('Interest') }}</th><th class="text-end">{{ __('Penalty') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('By') }}</th></tr></thead>
                                <tbody>
                                    @forelse ($loan->payments->sortByDesc('id') as $payment)
                                        <tr>
                                            <td><a href="{{ route('payments.show', $payment) }}">{{ $payment->receipt_number }}</a></td>
                                            <td>{{ $payment->payment_date->translatedFormat('d M Y') }}</td>
                                            <td>{{ $payment->method->label() }}</td>
                                            <td class="text-end">@money($payment->principal_amount)</td>
                                            <td class="text-end">@money($payment->interest_amount)</td>
                                            <td class="text-end">@money($payment->penalty_amount)</td>
                                            <td class="text-end fw-semibold">@money($payment->amount)</td>
                                            <td>{{ $payment->receiver?->name }}</td>
                                        </tr>
                                    @empty
                                        <x-empty-state colspan="8" icon="receipt" :title="__('No payments yet')" />
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modals --}}
    @if ($isActive)
        <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('payments.store', $loan) }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="paymentModalLabel">{{ __('Receive Payment') }} · {{ $loan->reference }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex gap-2 mb-3">
                            @if ($nextInstallment)
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="document.getElementById('amount').value='{{ number_format($nextInstallment->balance, 2, '.', '') }}'">
                                    {{ __('Next installment') }} (@money($nextInstallment->balance))
                                </button>
                            @endif
                            @if ($overdueBalance > 0)
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="document.getElementById('amount').value='{{ number_format($overdueBalance, 2, '.', '') }}'">
                                    {{ __('Overdue') }} (@money($overdueBalance))
                                </button>
                            @endif
                            <button type="button" class="btn btn-sm btn-outline-success" onclick="document.getElementById('amount').value='{{ number_format($loan->outstanding_balance, 2, '.', '') }}'">
                                {{ __('Pay off') }} (@money($loan->outstanding_balance))
                            </button>
                        </div>
                        <x-form.input name="amount" type="number" step="0.01" min="0.01" :max="$loan->outstanding_balance" :label="__('Amount')" :prepend="$appSettings['currency_symbol']" required />
                        <div class="row">
                            <x-form.input name="payment_date" type="date" :label="__('Payment date')" :value="today()->toDateString()" :max="today()->toDateString()" required wrapper="col-md-6 mb-3" />
                            <x-form.select name="method" :label="__('Method')" required wrapper="col-md-6 mb-3"
                                :options="collect($paymentMethods)->mapWithKeys(fn ($m) => [$m->value => $m->label()])" />
                        </div>
                        <x-form.input name="transaction_reference" :label="__('Transaction reference')" :placeholder="__('Bank / mobile money reference (optional)')" />
                        <x-form.textarea name="notes" :label="__('Notes')" rows="2" />
                        <p class="small text-secondary mb-0">{{ __('Payments are applied to the oldest installment first: penalty, then interest, then principal.') }}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i> {{ __('Record Payment') }}</button>
                    </div>
                </form>
            </div>
        </div>

        @can('write-off-loans')
            <div class="modal fade" id="writeOffModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="POST" action="{{ route('loans.write-off', $loan) }}" class="modal-content">
                        @csrf
                        <div class="modal-header"><h5 class="modal-title">{{ __('Write Off Loan') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">
                            <div class="alert alert-danger small">{{ __('Outstanding balance') }}: <strong>@money($loan->outstanding_balance)</strong>. {{ __('It will be written off as a loss. This cannot be undone.') }}</div>
                            <x-form.textarea name="reason" :label="__('Reason')" required />
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-danger">{{ __('Write Off') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan
    @endif

    @if ($loan->status === LoanStatus::Approved)
        @can('disburse-loans')
            <div class="modal fade" id="disburseModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="POST" action="{{ route('loans.disburse', $loan) }}" class="modal-content">
                        @csrf
                        <div class="modal-header"><h5 class="modal-title">{{ __('Disburse') }} {{ $loan->reference }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">
                            <div class="alert alert-light border small">
                                {{ __('Principal') }} <strong>@money($loan->principal_amount)</strong> − processing fee <strong>@money($loan->processing_fee)</strong>
                                = net payout <strong>@money((float) $loan->principal_amount - (float) $loan->processing_fee)</strong>
                            </div>
                            <div class="row">
                                <x-form.input name="disbursed_at" type="date" :label="__('Disbursement date')" :value="today()->toDateString()" :max="today()->toDateString()" required wrapper="col-md-6 mb-3" />
                                <x-form.input name="first_repayment_date" type="date" :label="__('First repayment date')" :value="$loan->repayment_frequency->addPeriods(today(), 1)->toDateString()" required wrapper="col-md-6 mb-3" />
                            </div>
                            <x-form.select name="disbursement_method" :label="__('Disbursement method')" required
                                :options="collect($paymentMethods)->mapWithKeys(fn ($m) => [$m->value => $m->label()])" />
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-send-check me-1"></i> {{ __('Disburse & Generate Schedule') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan
    @endif

    @if (in_array($loan->status, [LoanStatus::Pending, LoanStatus::Approved], true))
        @can('approve-loans')
            <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="POST" action="{{ route('loans.reject', $loan) }}" class="modal-content">
                        @csrf
                        <div class="modal-header"><h5 class="modal-title">{{ __('Reject Application') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">
                            <x-form.textarea name="rejection_reason" :label="__('Reason for rejection')" required />
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-danger">{{ __('Reject') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan
    @endif

    @php
        $reopen = collect([
            'paymentModal' => ['amount', 'payment_date', 'method'],
            'disburseModal' => ['disbursed_at', 'first_repayment_date', 'disbursement_method'],
            'rejectModal' => ['rejection_reason'],
            'writeOffModal' => ['reason'],
        ])->search(fn ($fields) => $errors->hasAny($fields));
    @endphp
    @if ($reopen)
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const el = document.getElementById(@js($reopen));
                    if (el) bootstrap.Modal.getOrCreateInstance(el).show();
                });
            </script>
        @endpush
    @endif
</x-layouts.admin>
