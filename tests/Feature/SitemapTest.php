<?php

namespace Tests\Feature;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('seo:sitemap:v1');
    }

    public function test_sitemap_lists_the_main_public_pages_as_xml(): void
    {
        $response = $this->get(route('sitemap'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<?xml version="1.0" encoding="UTF-8"?>', false)
            ->assertSee('<loc>'.route('home').'</loc>', false)
            ->assertSee('<loc>'.route('for-businesses').'</loc>', false)
            ->assertSee('<loc>'.route('marketplace.index').'</loc>', false);

        $this->assertSame(3, substr_count($response->getContent(), '<url>'));
        $response->assertDontSee('/login', false);
        $response->assertDontSee('/register', false);
    }

    public function test_sitemap_includes_only_visible_public_company_profiles(): void
    {
        $visible = Business::factory()->create([
            'name' => 'Empresa Pública',
            'public_slug' => 'empresa-publica',
            'public_profile_enabled' => true,
            'city' => 'Almenara',
            'public_services' => 'Elétrica',
        ]);

        $hidden = Business::factory()->create([
            'name' => 'Empresa Privada',
            'public_slug' => 'empresa-privada',
            'public_profile_enabled' => false,
            'city' => 'Almenara',
            'public_services' => 'Elétrica',
        ]);

        $unlisted = Business::factory()->create([
            'name' => 'Empresa sem cidade',
            'public_slug' => 'empresa-sem-cidade',
            'public_profile_enabled' => true,
            'city' => null,
            'public_services' => 'Elétrica',
        ]);

        $response = $this->get(route('sitemap'));

        $response->assertOk()
            ->assertSee('<loc>'.route('marketplace.show', $visible->public_slug).'</loc>', false)
            ->assertDontSee(route('marketplace.show', $hidden->public_slug), false)
            ->assertDontSee(route('marketplace.show', $unlisted->public_slug), false);

        $this->assertSame(4, substr_count($response->getContent(), '<url>'));
    }

    public function test_robots_txt_advertises_the_sitemap(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString(
            'Sitemap: https://negozia.com.br/sitemap.xml',
            $robots
        );
    }
}
