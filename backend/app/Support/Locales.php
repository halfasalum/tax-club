<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * Locale-aware URLs for the public website.
 *
 * Kiswahili pages live at the site root and English pages under "/en".
 * Every public route is registered twice, named "sw.*" and "en.*".
 */
final class Locales
{
    public const DEFAULT = 'sw';

    /** @var array<string, string> */
    public const SUPPORTED = [
        'sw' => 'Kiswahili',
        'en' => 'English',
    ];

    /**
     * URL of a public route in the given (or current) locale.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function route(string $name, array $parameters = [], ?string $locale = null): string
    {
        $locale = self::normalize($locale ?? app()->getLocale());

        return route("{$locale}.{$name}", $parameters);
    }

    /**
     * The same page in every supported locale, keyed by locale.
     * Returns an empty array for routes that are not localized.
     *
     * @return array<string, string>
     */
    public static function alternates(): array
    {
        $route = request()->route();
        $name = $route?->getName();

        if (! $name || ! preg_match('/^(sw|en)\.(.+)$/', $name, $matches)) {
            return [];
        }

        $alternates = [];

        foreach (array_keys(self::SUPPORTED) as $locale) {
            if (Route::has("{$locale}.{$matches[2]}")) {
                $alternates[$locale] = route("{$locale}.{$matches[2]}", $route->parameters());
            }
        }

        return $alternates;
    }

    public static function normalize(?string $locale): string
    {
        return array_key_exists((string) $locale, self::SUPPORTED) ? $locale : self::DEFAULT;
    }
}
