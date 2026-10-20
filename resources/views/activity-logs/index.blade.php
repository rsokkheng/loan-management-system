<x-layouts.admin :title="__('Activity Log')" :breadcrumbs="['Activity Log']">
    <div class="card mb-4">
        <div class="card-header">
            <form method="GET" class="d-flex gap-2">
                <select name="action" class="form-select form-select-sm" style="width: 200px" onchange="this.form.submit()">
                    <option value="">{{ __('All actions') }}</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected(request('action') === $action)>{{ __(str($action)->headline()->value()) }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead><tr><th style="width: 170px">{{ __('When') }}</th><th style="width: 160px">{{ __('User') }}</th><th style="width: 120px">{{ __('Action') }}</th><th>{{ __('Description') }}</th><th style="width: 130px">{{ __('IP') }}</th></tr></thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td class="text-nowrap" title="{{ $log->created_at }}">{{ $log->created_at->translatedFormat('d M Y H:i') }}</td>
                                <td>{{ $log->user?->name ?? 'System' }}</td>
                                <td><span class="badge text-bg-{{ $log->color() }}">{{ __(str($log->action)->headline()->value()) }}</span></td>
                                <td>
                                    {{ $log->description }}
                                    @if ($log->properties)
                                        <div class="small text-secondary">
                                            @foreach ($log->properties as $key => $value)
                                                {{ __(str($key)->headline()->value()) }}: {{ is_scalar($value) ? $value : json_encode($value) }}@unless ($loop->last) · @endunless
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="text-secondary small">{{ $log->ip_address }}</td>
                            </tr>
                        @empty
                            <x-empty-state colspan="5" icon="clock-history" :title="__('No activity recorded')" />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($logs->hasPages())
            <div class="card-footer">{{ $logs->links() }}</div>
        @endif
    </div>
</x-layouts.admin>
