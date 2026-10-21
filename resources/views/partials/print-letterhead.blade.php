<div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-4">
    <div class="d-flex align-items-center gap-3">
        <span class="brand-mark" style="width:48px;height:48px;font-size:1.4rem">L</span>
        <div>
            <h4 class="mb-0">{{ $appSettings['company_name'] }}</h4>
            @if ($appSettings['company_address'])
                <div class="small text-secondary">{{ $appSettings['company_address'] }}</div>
            @endif
            <div class="small text-secondary">{{ collect([$appSettings['company_phone'], $appSettings['company_email']])->filter()->implode(' · ') }}</div>
        </div>
    </div>
    <div class="text-end">
        <h5 class="mb-0 text-uppercase">{{ $documentTitle }}</h5>
        <div class="small text-secondary">{{ __('Printed') }} {{ now()->translatedFormat('d M Y H:i') }}</div>
    </div>
</div>
