<?php

namespace Tests\Feature;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketplace_home_has_canonical_social_metadata_and_brand_icon(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('rel="canonical" href="'.route('home').'"', false)
            ->assertSee('property="og:site_name" content="NegoZia"', false)
            ->assertSee('name="twitter:card" content="summary"', false)
            ->assertSee('rel="icon" href="'.asset('favicon.svg').'"', false)
            ->assertSee('application/ld+json', false);
    }

    public function test_business_landing_has_search_focused_metadata(): void
    {
        $this->get(route('for-businesses'))
            ->assertOk()
            ->assertSee('Sistema de Orçamentos e Propostas Comerciais | NegoZia')
            ->assertSee('rel="canonical" href="'.route('for-businesses').'"', false)
            ->assertSee('name="description"', false);
    }

    public function test_public_profile_uses_its_own_canonical_url(): void
    {
        $business = Business::factory()->create([
            'name' => 'Oficina Central',
            'public_profile_enabled' => true,
            'public_slug' => 'oficina-central',
            'city' => 'Almenara',
            'public_services' => 'Manutenção',
        ]);

        $this->get(route('marketplace.show', $business->public_slug))
            ->assertOk()
            ->assertSee('rel="canonical" href="'.route('marketplace.show', $business->public_slug).'"', false);
    }

    public function test_brand_assets_are_present(): void
    {
        $this->assertFileExists(public_path('favicon.svg'));
        $this->assertFileExists(public_path('favicon.ico'));
        $this->assertFileExists(public_path('apple-touch-icon.png'));
        $this->assertFileExists(public_path('icon-192.png'));
        $this->assertFileExists(public_path('site.webmanifest'));
        $this->assertSame(
            pack('C8', 137, 80, 78, 71, 13, 10, 26, 10),
            substr(file_get_contents(public_path('icon-192.png')), 0, 8)
        );
        $this->assertIsArray(
            json_decode(file_get_contents(public_path('site.webmanifest')), true)
        );
        $this->assertStringStartsWith(
            pack('C4', 0, 0, 1, 0),
            file_get_contents(public_path('favicon.ico'))
        );
    }
}
