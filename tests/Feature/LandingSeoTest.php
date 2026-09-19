<?php

namespace Tests\Feature;

use Tests\TestCase;

class LandingSeoTest extends TestCase
{
    public function test_the_landing_page_renders_seo_tags_in_the_html(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<title inertia>Digital Signature Software for Small Business - EZSign</title>', false);
        $response->assertSee('<meta name="description" content="Get contracts signed online', false);
        $response->assertSee('<link rel="canonical" href="'.rtrim(config('app.url'), '/').'/">', false);
        $response->assertSee('property="og:title"', false);
        $response->assertSee('application/ld+json', false);
        $response->assertSee('"@type":"SoftwareApplication"', false);
        $response->assertDontSee('name="robots"', false);
    }

    public function test_pages_without_meta_are_noindex(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $response->assertDontSee('name="description"', false);
    }

    public function test_the_sitemap_lists_the_landing_page(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertSee('<loc>'.rtrim(config('app.url'), '/').'/</loc>', false);
    }
}
