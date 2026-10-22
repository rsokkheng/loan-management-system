<div class="table-responsive">
    <table class="table table-sm table-hover table-nowrap align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th>{{ __('Date') }}</th>
                <th class="text-end">{{ __('Principal') }}</th>
                <th>{{ __('Rate') }}</th>
                <th>{{ __('Installments') }}</th>
                <th>{{ __('Maturity') }}</th>
                <th class="text-end">{{ __('Arrears carried') }}</th>
                <th class="text-end">{{ __('Penalty waived') }}</th>
                <th>{{ __('By') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($loan->restructures as $restructure)
                <tr>
                    <td>{{ $restructure->restructure_date->translatedFormat('d M Y') }}</td>
                    <td class="text-end">@money($restructure->principal_rescheduled, $loan->currency)</td>
                    <td>{{ (float) $restructure->previous_interest_rate }}% → {{ (float) $restructure->new_interest_rate }}%</td>
                    <td>{{ $restructure->previous_remaining_installments }} → {{ $restructure->new_term }}</td>
                    <td>{{ $restructure->previous_maturity_date?->translatedFormat('d M Y') ?? '—' }} → {{ $restructure->new_maturity_date->translatedFormat('d M Y') }}</td>
                    <td class="text-end">@money((float) $restructure->interest_carried + (float) $restructure->penalty_carried, $loan->currency)</td>
                    <td class="text-end">@money($restructure->penalty_waived, $loan->currency)</td>
                    <td>{{ $restructure->creator?->name }}</td>
                </tr>
                <tr class="table-light"><td colspan="8" class="small text-secondary">{{ $restructure->reason }}</td></tr>
            @endforeach
        </tbody>
    </table>
</div>
