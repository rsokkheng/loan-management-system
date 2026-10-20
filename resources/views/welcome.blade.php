<x-layouts.guest>
    <header class="border-b border-slate-200 bg-white/80 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
            <a href="{{ route('home') }}" class="flex items-center gap-2">
                <span class="flex size-9 items-center justify-center rounded-xl bg-linear-to-br from-brand-500 to-purple-600 font-bold text-white">L</span>
                <span class="font-semibold text-slate-900">{{ $appSettings['company_name'] }}</span>
            </a>
            <div class="flex items-center gap-3">
                <x-locale-switch />
                @auth
                <a href="{{ route('dashboard') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Open Dashboard') }}</a>
            @else
                <a href="{{ route('login') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Staff Login') }}</a>
            @endauth
            </div>
        </div>
    </header>

    <main>
        <section class="mx-auto grid max-w-6xl items-center gap-12 px-4 py-16 md:grid-cols-2 md:py-24">
            <div>
                <span class="inline-flex items-center gap-2 rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700">
                    <span class="size-1.5 rounded-full bg-brand-500"></span> {{ __('Loan Management System') }}
                </span>
                <h1 class="mt-5 text-4xl font-bold tracking-tight text-slate-900 md:text-5xl">
                    {{ __('Run your lending business from one dashboard.') }}
                </h1>
                <p class="mt-5 text-lg leading-relaxed text-slate-600">
                    Register borrowers, configure loan products, approve and disburse loans, generate repayment schedules,
                    collect payments and track arrears, all in one place.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('login') }}" class="rounded-lg bg-brand-600 px-5 py-3 font-semibold text-white shadow-sm hover:bg-brand-700">{{ __('Sign in to get started') }}</a>
                    <a href="#features" class="rounded-lg border border-slate-300 bg-white px-5 py-3 font-semibold text-slate-700 hover:bg-slate-50">{{ __('See features') }}</a>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/60">
                <div class="grid grid-cols-2 gap-4">
                    @foreach ([
                        ['Portfolio', '$1.24M', 'text-brand-600'],
                        ['Active loans', '1,284', 'text-emerald-600'],
                        ['Collected (MTD)', '$186K', 'text-purple-600'],
                        ['PAR > 30', '3.2%', 'text-amber-600'],
                    ] as [$label, $value, $color])
                        <div class="rounded-xl bg-slate-50 p-4">
                            <div class="text-xs font-medium tracking-wide text-slate-500 uppercase">{{ __($label) }}</div>
                            <div class="mt-1 text-2xl font-bold {{ $color }}">{{ $value }}</div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-5 flex h-32 items-end gap-2">
                    @foreach ([40, 55, 48, 70, 62, 80, 74, 92, 85, 96, 88, 100] as $height)
                        <div class="flex-1 rounded-t bg-linear-to-t from-brand-500 to-brand-100" style="height: {{ $height }}%"></div>
                    @endforeach
                </div>
                <p class="mt-3 text-center text-xs text-slate-400">{{ __('Sample figures for illustration') }}</p>
            </div>
        </section>

        <section id="features" class="border-t border-slate-200 bg-white py-16">
            <div class="mx-auto max-w-6xl px-4">
                <h2 class="text-center text-3xl font-bold text-slate-900">{{ __('Everything a lender needs') }}</h2>
                <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        ['Borrower KYC', 'Profiles, photos, ID numbers and supporting documents for every client.'],
                        ['Flexible products', 'Flat or reducing-balance interest, daily to monthly repayments, fees and penalties.'],
                        ['Approval workflow', 'Applications move from pending to approved to disbursed with a full audit trail.'],
                        ['Automatic schedules', 'Repayment schedules are generated at disbursement and printable for the borrower.'],
                        ['Smart collections', 'Payments are allocated to penalty, interest and principal automatically, with receipts.'],
                        ['Reports & aging', 'Collections, disbursements, portfolio and arrears aging with CSV export.'],
                    ] as [$title, $text])
                        <div class="rounded-xl border border-slate-200 p-6">
                            <h3 class="font-semibold text-slate-900">{{ __($title) }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ __($text) }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    </main>

    <footer class="py-8 text-center text-sm text-slate-500">
        &copy; {{ date('Y') }} {{ $appSettings['company_name'] }}
    </footer>
</x-layouts.guest>
