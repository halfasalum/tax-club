<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\Event;
use App\Support\Locales;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * XML sitemap with Kiswahili/English alternates for every public page.
     */
    public function sitemap(): Response
    {
        $entries = collect([
            ['route' => 'home', 'parameters' => [], 'updated' => null],
            ['route' => 'learn.index', 'parameters' => [], 'updated' => null],
            ['route' => 'events.index', 'parameters' => [], 'updated' => null],
            ['route' => 'about', 'parameters' => [], 'updated' => null],
        ]);

        Content::publiclyVisible()->select(['content_id', 'slug', 'published_at'])->each(function (Content $lesson) use ($entries) {
            $entries->push(['route' => 'learn.show', 'parameters' => ['slug' => $lesson->slug], 'updated' => $lesson->published_at]);
        });

        Event::active()->whereNotNull('slug')->select(['event_id', 'slug', 'updated_at'])->each(function (Event $event) use ($entries) {
            $entries->push(['route' => 'events.show', 'parameters' => ['slug' => $event->slug], 'updated' => $event->updated_at]);
        });

        $urls = $entries->map(fn (array $entry) => [
            'alternates' => collect(array_keys(Locales::SUPPORTED))
                ->mapWithKeys(fn (string $locale) => [$locale => Locales::route($entry['route'], $entry['parameters'], $locale)])
                ->all(),
            'updated' => $entry['updated'],
        ]);

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        $lines = app()->isProduction()
            ? ['User-agent: *', 'Disallow: /admin', 'Disallow: /app', 'Disallow: /api/', 'Disallow: /login', 'Disallow: /register', '', 'Sitemap: '.route('sitemap')]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
