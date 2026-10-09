<?php

namespace Tests\Feature\Settings;

use App\Models\Business;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BusinessPublicProfileServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_can_select_catalog_services_for_public_profile(): void
    {
        $this->seed(
            PlanSeeder::class
        );

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $business = Business::factory()->create([
            'user_id' => $user->id,
            'name' => 'Elétrica Central',
            'city' => 'Almenara',
            'state' => 'MG',
        ]);

        $category = ServiceCategory::create([
            'name' => 'Elétrica',
            'slug' => 'eletrica',
            'search_terms' => 'eletricista elétrica eletrica',
            'sort_order' => 1,
            'active' => true,
        ]);

        $service = Service::create([
            'service_category_id' => $category->id,
            'name' => 'Instalação elétrica',
            'slug' => 'instalacao-eletrica',
            'search_terms' => 'eletricista instalação elétrica',
            'sort_order' => 1,
            'active' => true,
        ]);

        Livewire::actingAs($user)
            ->test('pages::settings.business')
            ->set(
                'publicProfileEnabled',
                true
            )
            ->set(
                'publicServiceCategoryId',
                (string) $category->id
            )
            ->set(
                'publicServiceIds',
                [
                    (string) $service->id,
                ]
            )
            ->call('save')
            ->assertHasNoErrors();

        $business->refresh();

        $this->assertTrue(
            $business->public_profile_enabled
        );

        $this->assertSame(
            'Instalação elétrica',
            $business->public_services
        );

        $this->assertTrue(
            $business
                ->services()
                ->whereKey($service->id)
                ->exists()
        );
    }
}
