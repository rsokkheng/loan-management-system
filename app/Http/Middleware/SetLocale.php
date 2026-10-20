<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Supported interface languages.
     *
     * @var array<string, string>
     */
    public const LOCALES = ['en' => 'English', 'km' => 'ខ្មែរ'];

    /**
     * Use the signed-in user's language, then the session's, then the app default.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale ?? $request->session()->get('locale', config('app.locale'));

        if (! array_key_exists($locale, self::LOCALES)) {
            $locale = config('app.fallback_locale');
        }

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
