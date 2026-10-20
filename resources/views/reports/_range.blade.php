<form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
    <div>
        <label class="form-label small mb-1">{{ __('From') }}</label>
        <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm">
    </div>
    <div>
        <label class="form-label small mb-1">{{ __('To') }}</label>
        <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm">
    </div>
    <button class="btn btn-sm btn-secondary"><i class="bi bi-funnel me-1"></i> {{ __('Apply') }}</button>
    <a href="{{ request()->fullUrlWithQuery(['export' => 'csv', 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-filetype-csv me-1"></i> {{ __('Export CSV') }}</a>
    <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></button>
</form>
