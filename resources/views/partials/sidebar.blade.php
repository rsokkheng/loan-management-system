@php
    $menu = [
        ['header' => 'Main'],
        ['label' => 'Dashboard', 'icon' => 'speedometer2', 'route' => 'dashboard', 'active' => 'dashboard'],
        ['label' => 'Borrowers', 'icon' => 'people', 'route' => 'borrowers.index', 'active' => 'borrowers.*'],
        ['label' => 'Loans', 'icon' => 'bank', 'active' => 'loans.*', 'children' => [
            ['label' => 'All Loans', 'route' => 'loans.index', 'active' => 'loans.index', 'query' => []],
            ['label' => 'New Application', 'route' => 'loans.create', 'active' => 'loans.create'],
            ['label' => 'Pending Approval', 'route' => 'loans.index', 'query' => ['status' => 'pending']],
            ['label' => 'Overdue', 'route' => 'loans.index', 'query' => ['status' => 'overdue']],
        ]],
        ['label' => 'Repayments', 'icon' => 'cash-stack', 'active' => 'payments.*', 'children' => [
            ['label' => 'Receive Payment', 'route' => 'payments.create', 'active' => 'payments.create'],
            ['label' => 'Payment History', 'route' => 'payments.index', 'active' => 'payments.index'],
        ]],
        ['label' => 'Loan Calculator', 'icon' => 'calculator', 'route' => 'calculator', 'active' => 'calculator'],
        ['header' => 'Analytics', 'can' => 'view-reports'],
        ['label' => 'Reports', 'icon' => 'bar-chart-line', 'active' => 'reports.*', 'can' => 'view-reports', 'children' => [
            ['label' => 'Overview', 'route' => 'reports.index', 'active' => 'reports.index'],
            ['label' => 'Collections', 'route' => 'reports.collections', 'active' => 'reports.collections'],
            ['label' => 'Disbursements', 'route' => 'reports.disbursements', 'active' => 'reports.disbursements'],
            ['label' => 'Arrears / Aging', 'route' => 'reports.arrears', 'active' => 'reports.arrears'],
            ['label' => 'Portfolio at Risk', 'route' => 'reports.portfolio-at-risk', 'active' => 'reports.portfolio-at-risk'],
            ['label' => 'Branch Profitability', 'route' => 'reports.branch-profitability', 'active' => 'reports.branch-profitability'],
            ['label' => 'Portfolio', 'route' => 'reports.portfolio', 'active' => 'reports.portfolio'],
        ]],
        ['header' => 'Accounting', 'can' => 'manage-accounting'],
        ['label' => 'Bookkeeping', 'icon' => 'journal-bookmark', 'active' => 'accounting.accounts.*|accounting.journal.*|accounting.transactions.*', 'can' => 'manage-accounting', 'children' => [
            ['label' => 'Chart of Accounts', 'route' => 'accounting.accounts.index', 'active' => 'accounting.accounts.*'],
            ['label' => 'Journal Entries', 'route' => 'accounting.journal.index', 'active' => 'accounting.journal.index'],
            ['label' => 'New Journal Entry', 'route' => 'accounting.journal.create', 'active' => 'accounting.journal.create'],
            ['label' => 'Record Expense', 'route' => 'accounting.transactions.create', 'query' => ['type' => 'expense']],
            ['label' => 'Record Income', 'route' => 'accounting.transactions.create', 'query' => ['type' => 'income']],
        ]],
        ['label' => 'Financial Statements', 'icon' => 'clipboard-data', 'active' => 'accounting.trial-balance|accounting.income-statement|accounting.balance-sheet', 'can' => 'manage-accounting', 'children' => [
            ['label' => 'Trial Balance', 'route' => 'accounting.trial-balance', 'active' => 'accounting.trial-balance'],
            ['label' => 'Income Statement', 'route' => 'accounting.income-statement', 'active' => 'accounting.income-statement'],
            ['label' => 'Balance Sheet', 'route' => 'accounting.balance-sheet', 'active' => 'accounting.balance-sheet'],
        ]],
        ['header' => 'Administration', 'can' => 'manage-products'],
        ['label' => 'Branches', 'icon' => 'building', 'route' => 'branches.index', 'active' => 'branches.*', 'can' => 'manage-branches'],
        ['label' => 'Loan Products', 'icon' => 'box-seam', 'route' => 'loan-products.index', 'active' => 'loan-products.*', 'can' => 'manage-products'],
        ['label' => 'Users', 'icon' => 'person-gear', 'route' => 'users.index', 'active' => 'users.*', 'can' => 'manage-users'],
        ['label' => 'Activity Log', 'icon' => 'clock-history', 'route' => 'activity-logs.index', 'active' => 'activity-logs.*', 'can' => 'view-activity'],
        ['label' => 'Settings', 'icon' => 'gear', 'route' => 'settings.edit', 'active' => 'settings.*', 'can' => 'manage-settings'],
    ];

    $isChildActive = function (array $child): bool {
        if (isset($child['query'])) {
            return request()->routeIs($child['route'])
                && collect($child['query'])->every(fn ($value, $key) => request($key) === $value)
                && ($child['query'] !== [] || ! request()->filled('status'));
        }

        return request()->routeIs(...explode('|', $child['active']));
    };
@endphp

<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
    <div class="sidebar-brand">
        <a href="{{ route('dashboard') }}" class="brand-link d-flex align-items-center gap-2 text-decoration-none">
            <span class="brand-mark">L</span>
            <span class="brand-text fw-semibold text-truncate" style="max-width: 170px">{{ $appSettings['company_name'] }}</span>
        </a>
    </div>

    <div class="sidebar-wrapper">
        <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation" aria-label="{{ __('Main navigation') }}" data-accordion="false">
                @foreach ($menu as $item)
                    @continue(isset($item['can']) && auth()->user()->cannot($item['can']))

                    @if (isset($item['header']))
                        <li class="nav-header">{{ mb_strtoupper(__($item['header'])) }}</li>
                    @elseif (isset($item['children']))
                        @php $open = request()->routeIs(...explode('|', $item['active'])); @endphp
                        <li class="nav-item {{ $open ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ $open ? 'active' : '' }}">
                                <i class="nav-icon bi bi-{{ $item['icon'] }}"></i>
                                <p>{{ __($item['label']) }} <i class="nav-arrow bi bi-chevron-right"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                @foreach ($item['children'] as $child)
                                    <li class="nav-item">
                                        <a href="{{ route($child['route'], $child['query'] ?? []) }}" class="nav-link {{ $isChildActive($child) ? 'active' : '' }}">
                                            <i class="nav-icon bi bi-circle"></i>
                                            <p>{{ __($child['label']) }}</p>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @else
                        <li class="nav-item">
                            <a href="{{ route($item['route']) }}" class="nav-link {{ request()->routeIs($item['active']) ? 'active' : '' }}">
                                <i class="nav-icon bi bi-{{ $item['icon'] }}"></i>
                                <p>{{ __($item['label']) }}</p>
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </nav>
    </div>
</aside>
