<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Use the locale fixed by a public route ("locale:en"), then an explicit
     * "?lang=" choice, then the visitor's last locale from the session (or,
     * for the mobile app's API calls, the Accept-Language header),
     * otherwise Kiswahili.
     */
    public function handle(Request $request, Closure $next, ?string $locale = null): Response
    {
        $requested = $request->query('lang');

        $locale = Locales::normalize(
            $locale
            ?? (is_string($requested) && array_key_exists($requested, Locales::SUPPORTED) ? $requested : null)
            ?? ($request->hasSession() ? $request->session()->get('locale') : null)
            ?? ($request->is('api/*') ? $this->fromHeader($request) : null)
        );

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        if ($request->hasSession()) {
            $request->session()->put('locale', $locale);
        }

        return $next($request);
    }

    /**
     * The app sends "Accept-Language: sw" or "en" with every request.
     */
    private function fromHeader(Request $request): ?string
    {
        $preferred = $request->getPreferredLanguage(array_keys(Locales::SUPPORTED));

        return $request->headers->has('Accept-Language') ? $preferred : null;
    }
}
