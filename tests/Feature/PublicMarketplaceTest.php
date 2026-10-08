<?php

namespace Tests\Feature;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_marketplace_home_loads(): void
    {
        $this
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Encontre quem faz')
            ->assertSee('O que você precisa hoje?')
            ->assertSee('Busca local')
            ->assertSee('Contato direto');
    }

    public function test_search_returns_only_publicly_listed_businesses(): void
    {
        $visible = Business::factory()->create([
            'name' => 'Elétrica Almenara',
            'city' => 'Almenara',
            'state' => 'MG',
            'public_profile_enabled' => true,
            'public_slug' => 'eletrica-almenara-1',
            'public_services' =>
                'Eletricista, instalação elétrica',
        ]);

        $hidden = Business::factory()->create([
            'name' => 'Empresa Oculta',
            'city' => 'Almenara',
            'state' => 'MG',
            'public_profile_enabled' => false,
            'public_slug' => 'empresa-oculta-2',
            'public_services' => 'Eletricista',
        ]);

        $this
            ->get(route('marketplace.index', [
                'servico' => 'Eletricista',
                'cidade' => 'Almenara',
            ]))
            ->assertOk()
            ->assertSee($visible->name)
            ->assertDontSee($hidden->name);
    }

    public function test_enabled_public_profile_can_be_viewed(): void
    {
        $business = Business::factory()->create([
            'name' => 'Fotografia Central',
            'city' => 'Almenara',
            'state' => 'MG',
            'public_profile_enabled' => true,
            'public_slug' => 'fotografia-central-1',
            'public_services' => 'Fotografia, eventos',
        ]);

        $this
            ->get(route(
                'marketplace.show',
                $business->public_slug
            ))
            ->assertOk()
            ->assertSee($business->name);
    }

    public function test_disabled_public_profile_returns_404(): void
    {
        $business = Business::factory()->create([
            'name' => 'Empresa Privada',
            'city' => 'Almenara',
            'state' => 'MG',
            'public_profile_enabled' => false,
            'public_slug' => 'empresa-privada-1',
            'public_services' => 'Manutenção',
        ]);

        $this
            ->get(route(
                'marketplace.show',
                $business->public_slug
            ))
            ->assertNotFound();
    }

    public function test_business_landing_page_is_available(): void
    {
        $this
            ->get(route('for-businesses'))
            ->assertOk()
            ->assertSee(
                'Envie propostas profissionais'
            )
            ->assertSee(
                'Encontrar serviços'
            );
    }

}
