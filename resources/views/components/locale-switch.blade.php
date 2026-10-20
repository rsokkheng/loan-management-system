{{-- Tailwind language toggle for public pages. --}}
<div {{ $attributes->class(['flex items-center gap-1 rounded-lg border border-slate-200 bg-white p-1 text-xs font-medium']) }}>
    @foreach (\App\Http\Middleware\SetLocale::LOCALES as $code => $language)
        <form method="POST" action="{{ route('locale.switch', $code) }}">
            @csrf
            <button type="submit" @class([
                'rounded-md px-2.5 py-1 transition',
                'bg-brand-600 text-white' => app()->getLocale() === $code,
                'text-slate-600 hover:bg-slate-100' => app()->getLocale() !== $code,
            ])>{{ $language }}</button>
        </form>
    @endforeach
</div>
