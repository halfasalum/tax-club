<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicWebsiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_is_served_in_kiswahili_at_the_root_with_hreflang_alternates(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="sw">', false)
            ->assertSee('Kodi hujenga shule, barabara na hospitali zetu.')
            ->assertSee('<link rel="canonical" href="'.url('/').'">', false)
            ->assertSee('hreflang="en" href="'.url('/en').'"', false)
            ->assertSee('hreflang="x-default" href="'.url('/').'"', false)
            ->assertSee('application/ld+json', false);
    }

    public function test_english_pages_are_served_under_the_en_prefix(): void
    {
        $this->get('/en/learn')
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('Tax lessons')
            ->assertSee('hreflang="sw" href="'.url('/learn').'"', false);
    }

    public function test_public_lesson_page_renders_sanitized_body_and_article_schema(): void
    {
        $lesson = Content::factory()->create([
            'title' => 'Why we ask for receipts',
            'body_text' => '<p>Receipts matter.</p><script>alert(1)</script><a href="javascript:alert(1)">x</a>',
        ]);

        $this->get('/en/learn/'.$lesson->slug)
            ->assertOk()
            ->assertSee('<title>Why we ask for receipts | TRA Tax Club</title>', false)
            ->assertSee('<p>Receipts matter.</p>', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('javascript:alert', false)
            ->assertSee('"@type":"Article"', false);
    }

    public function test_members_only_and_unpublished_lessons_are_not_public(): void
    {
        $membersOnly = Content::factory()->membersOnly()->create();
        $scheduled = Content::factory()->scheduled()->create();

        $this->get('/learn/'.$membersOnly->slug)->assertNotFound();
        $this->get('/learn/'.$scheduled->slug)->assertNotFound();
        $this->get('/learn')->assertDontSee($membersOnly->title)->assertDontSee($scheduled->title);
    }

    public function test_event_page_includes_event_schema(): void
    {
        $event = Event::factory()->create(['title' => 'National Tax Quiz']);

        $this->get('/en/events/'.$event->slug)
            ->assertOk()
            ->assertSee('National Tax Quiz')
            ->assertSee('"@type":"Event"', false);
    }

    public function test_sitemap_lists_both_locales_for_public_pages_only(): void
    {
        $lesson = Content::factory()->create();
        $hidden = Content::factory()->membersOnly()->create();

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>'.url('/learn/'.$lesson->slug).'</loc>', false)
            ->assertSee('<loc>'.url('/en/learn/'.$lesson->slug).'</loc>', false)
            ->assertDontSee($hidden->slug);
    }

    public function test_robots_blocks_crawlers_outside_production(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /');
    }

    public function test_robots_allows_public_pages_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /admin')
            ->assertSee('Sitemap: '.route('sitemap'));
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('/')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }
}
