<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Built assets are not committed; pages render without the Vite manifest.
        $this->withoutVite();

        // Component tests assert English copy; public routes set their own locale from the URL.
        app()->setLocale('en');
    }
}
