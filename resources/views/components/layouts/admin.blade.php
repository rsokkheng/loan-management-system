@props(['title' => null, 'breadcrumbs' => []])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ $appSettings['company_name'] }}</title>
    <script>
        try { document.documentElement.setAttribute('data-bs-theme', localStorage.getItem('theme') || 'light'); } catch (e) {}
    </script>
    @fonts
    <script>
        window.i18n = @js(collect([
            'Select a product to see its terms.', 'Interest', 'p.a.', 'Repayment', 'Amount', 'Term', 'installments',
            'Processing fee', 'Late penalty', 'after', 'day(s)', 'Installment', 'Total interest', 'Total payable', 'Net disbursed',
            'Estimate only. The final schedule is generated at disbursement.',
        ])->mapWithKeys(fn ($key) => [$key => __($key)]));
    </script>
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
    @stack('styles')
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary" @if ($errors->any() && old('_modal')) data-reopen-modal="{{ old('_modal') }}" @endif>
<div class="app-wrapper">
    {{-- Header --}}
    <nav class="app-header navbar navbar-expand bg-body border-bottom">
        <div class="container-fluid">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="{{ __('Toggle sidebar') }}">
                        <i class="bi bi-list"></i>
                    </a>
                </li>
                <li class="nav-item d-none d-md-block">
                    <a href="{{ route('dashboard') }}" class="nav-link">{{ __('Home') }}</a>
                </li>
                <li class="nav-item d-none d-md-block">
                    <a href="{{ route('calculator') }}" class="nav-link">{{ __('Calculator') }}</a>
                </li>
            </ul>

            <form class="d-none d-md-flex ms-3" action="{{ route('loans.index') }}" method="GET" role="search">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-transparent"><i class="bi bi-search"></i></span>
                    <input class="form-control" type="search" name="search" placeholder="{{ __('Search loans or borrowers…') }}" aria-label="{{ __('Search') }}">
                </div>
            </form>

            <ul class="navbar-nav ms-auto align-items-center">
                {{-- Branch --}}
                @if ($canSwitchBranch)
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-building me-1"></i>
                            <span class="d-none d-sm-inline">{{ $currentBranch?->name ?? __('All Branches') }}</span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end">
                            <h6 class="dropdown-header">{{ __('Working branch') }}</h6>
                            @foreach (['' => __('All Branches')] + $branchOptions->all() as $id => $name)
                                <form method="POST" action="{{ route('branches.switch') }}">
                                    @csrf
                                    <input type="hidden" name="branch_id" value="{{ $id }}">
                                    <button type="submit" class="dropdown-item d-flex justify-content-between align-items-center {{ (string) $currentBranch?->id === (string) $id ? 'active' : '' }}">
                                        {{ $name }}
                                        @if ((string) $currentBranch?->id === (string) $id)<i class="bi bi-check-lg ms-3"></i>@endif
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </li>
                @elseif ($currentBranch)
                    <li class="nav-item d-flex align-items-center">
                        <span class="badge text-bg-light border mx-2"><i class="bi bi-building me-1"></i>{{ $currentBranch->name }}</span>
                    </li>
                @endif

                {{-- Reporting currency --}}
                <li class="nav-item dropdown">
                    <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('Reporting currency') }}">
                        <i class="bi bi-currency-exchange me-1"></i>
                        <span class="d-none d-sm-inline">{{ $reportingCurrency->value }}</span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end">
                        <h6 class="dropdown-header">{{ __('Reporting currency') }}</h6>
                        @foreach (\App\Enums\Currency::cases() as $currency)
                            <form method="POST" action="{{ route('currency.switch') }}">
                                @csrf
                                <input type="hidden" name="currency" value="{{ $currency->value }}">
                                <button type="submit" class="dropdown-item d-flex justify-content-between align-items-center {{ $reportingCurrency === $currency ? 'active' : '' }}">
                                    {{ $currency->symbol() }} {{ $currency->label() }}
                                    @if ($reportingCurrency === $currency)<i class="bi bi-check-lg ms-3"></i>@endif
                                </button>
                            </form>
                        @endforeach
                    </div>
                </li>

                {{-- Language --}}
                <li class="nav-item dropdown">
                    <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('Language') }}">
                        <i class="bi bi-translate"></i>
                        <span class="d-none d-sm-inline">{{ \App\Http\Middleware\SetLocale::LOCALES[app()->getLocale()] }}</span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end">
                        @foreach (\App\Http\Middleware\SetLocale::LOCALES as $code => $language)
                            <form method="POST" action="{{ route('locale.switch', $code) }}">
                                @csrf
                                <button type="submit" class="dropdown-item {{ app()->getLocale() === $code ? 'active' : '' }}">{{ $language }}</button>
                            </form>
                        @endforeach
                    </div>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="{{ route('payments.create') }}" data-bs-toggle="tooltip" data-bs-placement="bottom" title="{{ __('Receive payment') }}">
                        <i class="bi bi-cash-coin"></i>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#" data-toggle-theme aria-label="{{ __('Toggle dark mode') }}">
                        <i data-theme-icon class="bi bi-moon-stars"></i>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#" data-lte-toggle="fullscreen" aria-label="{{ __('Toggle fullscreen') }}">
                        <i data-lte-icon="maximize" class="bi bi-arrows-fullscreen"></i>
                        <i data-lte-icon="minimize" class="bi bi-fullscreen-exit" style="display: none"></i>
                    </a>
                </li>
                <li class="nav-item dropdown user-menu">
                    <a href="#" class="nav-link dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                        <span class="avatar" style="width:30px;height:30px;font-size:.75rem">{{ auth()->user()->initials() }}</span>
                        <span class="d-none d-md-inline">{{ auth()->user()->name }}</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                        <li class="user-header text-bg-primary">
                            <span class="avatar avatar-lg bg-white text-primary mb-2">{{ auth()->user()->initials() }}</span>
                            <p>
                                {{ auth()->user()->name }}
                                <small>{{ auth()->user()->role->label() }}</small>
                            </p>
                        </li>
                        <li class="user-footer">
                            <a href="{{ route('profile.edit') }}" class="btn btn-default btn-flat">{{ __('Profile') }}</a>
                            <form method="POST" action="{{ route('logout') }}" class="d-inline float-end">
                                @csrf
                                <button type="submit" class="btn btn-default btn-flat">{{ __('Sign out') }}</button>
                            </form>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>

    @include('partials.sidebar')

    {{-- Main content --}}
    <main class="app-main">
        <div class="app-content-header">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-sm-6">
                        <h3 class="mb-0">{{ $title }}</h3>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-end mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>
                            @foreach ($breadcrumbs as $label => $url)
                                @if ($loop->last || ! $url)
                                    <li class="breadcrumb-item active" aria-current="page">{{ __(is_int($label) ? $url : $label) }}</li>
                                @else
                                    <li class="breadcrumb-item"><a href="{{ $url }}">{{ __($label) }}</a></li>
                                @endif
                            @endforeach
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="app-content">
            <div class="container-fluid">
                @if ($errors->any() && ! $errors->hasBag('password'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <strong>{{ __('Please fix the following:') }}</strong>
                        <ul class="mb-0 mt-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
                    </div>
                @endif

                {{ $slot }}
            </div>
        </div>
    </main>

    <footer class="app-footer">
        <div class="float-end d-none d-sm-inline">{{ __('Version 1.0') }}</div>
        <strong>&copy; {{ date('Y') }} {{ $appSettings['company_name'] }}.</strong> {{ __('All rights reserved.') }}
    </footer>
</div>

{{-- Flash toasts --}}
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080">
    @foreach (['success' => 'check-circle', 'error' => 'x-circle', 'warning' => 'exclamation-triangle'] as $type => $icon)
        @if (session($type))
            <div class="toast align-items-center text-bg-{{ $type === 'error' ? 'danger' : $type }} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body"><i class="bi bi-{{ $icon }} me-2"></i>{{ session($type) }}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="{{ __('Close') }}"></button>
                </div>
            </div>
        @endif
    @endforeach
</div>

{{-- Shared confirmation modal (see admin.js: form[data-confirm]) --}}
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <i class="bi bi-exclamation-circle text-warning display-5"></i>
                <p class="mt-3 mb-0" data-confirm-message>{{ __('Are you sure?') }}</p>
            </div>
            <div class="modal-footer justify-content-center border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-danger" data-confirm-accept>{{ __('Yes, continue') }}</button>
            </div>
        </div>
    </div>
</div>

@stack('modals')

@stack('scripts')
</body>
</html>
