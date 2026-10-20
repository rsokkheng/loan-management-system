<x-layouts.guest :title="__('Sign in')">
    <div class="grid min-h-screen lg:grid-cols-2">
        <div class="hidden flex-col justify-between bg-linear-to-br from-brand-700 via-brand-600 to-purple-700 p-12 text-white lg:flex">
            <a href="{{ route('home') }}" class="flex items-center gap-2">
                <span class="flex size-10 items-center justify-center rounded-xl bg-white/15 text-lg font-bold">L</span>
                <span class="text-lg font-semibold">{{ $appSettings['company_name'] }}</span>
            </a>
            <div>
                <h2 class="text-3xl font-bold leading-tight">{{ __('Lending operations, organised.') }}</h2>
                <p class="mt-4 max-w-md text-white/80">{{ __('Track every borrower, loan and payment with real-time portfolio insight.') }}</p>
            </div>
            <p class="text-sm text-white/60">&copy; {{ date('Y') }} {{ $appSettings['company_name'] }}</p>
        </div>

        <div class="flex items-center justify-center px-4 py-12">
            <div class="w-full max-w-sm">
                <x-locale-switch class="mb-8 w-fit" />
                <h1 class="text-2xl font-bold text-slate-900">{{ __('Welcome back') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ __('Sign in with your staff account.') }}</p>

                @if ($errors->any())
                    <div class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="mt-6 flex flex-col gap-5">
                    @csrf
                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-700">{{ __('Email') }}</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                            class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-700">{{ __('Password') }}</label>
                        <input id="password" type="password" name="password" required autocomplete="current-password"
                            class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="remember" class="size-4 rounded border-slate-300 text-brand-600">
                        {{ __('Remember me') }}
                    </label>
                    <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2.5 font-semibold text-white shadow-sm hover:bg-brand-700 focus:ring-2 focus:ring-brand-500/40 focus:outline-none">
                        {{ __('Sign in') }}
                    </button>
                </form>

                @if (app()->isLocal())
                    <div class="mt-8 rounded-lg border border-dashed border-slate-300 bg-white p-4 text-xs text-slate-500">
                        <div class="font-semibold text-slate-700">{{ __('Demo accounts') }} ({{ __('Password') }}: <code>password</code>)</div>
                        <ul class="mt-2 flex flex-col gap-1">
                            <li>admin@loanpro.test · Administrator</li>
                            <li>manager@loanpro.test · Manager</li>
                            <li>officer@loanpro.test · Loan Officer</li>
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.guest>
