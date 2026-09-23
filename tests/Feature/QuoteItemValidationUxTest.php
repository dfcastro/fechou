<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuoteItemValidationUxTest extends TestCase
{
    use RefreshDatabase;

    private function userWithAccount(): User
    {
        $user = User::factory()->create();

        $business = Business::factory()->create([
            'user_id' => $user->id,
            'onboarding_completed_at' => now(),
        ]);

        $plan = Plan::create([
            'name' => 'Grátis',
            'slug' => 'free',
            'description' => 'Plano gratuito',
            'price' => 0,
            'billing_interval' => 'month',
            'quote_limit' => 5,
            'features' => [],
            'is_active' => true,
            'sort_order' => 10,
        ]);

        Subscription::create([
            'business_id' => $business->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'current_period_starts_at' => now()->startOfMonth(),
            'current_period_ends_at' => now()->endOfMonth(),
        ]);

        return $user;
    }

    public function test_empty_unit_price_shows_validation_message(): void
    {
        $user = $this->userWithAccount();

        Livewire::actingAs($user)
            ->test('pages::quotes.create')
            ->call('newItem', 'service')
            ->set('itemDescription', 'Instalação')
            ->set('itemQuantity', '1')
            ->set('itemUnit', 'serviço')
            ->set('itemUnitPrice', '')
            ->call('saveItem')
            ->assertHasErrors([
                'itemUnitPrice' => 'required',
            ])
            ->assertSee('Informe o valor unitário.');
    }

    public function test_invalid_quantity_shows_validation_message(): void
    {
        $user = $this->userWithAccount();

        Livewire::actingAs($user)
            ->test('pages::quotes.create')
            ->call('newItem', 'service')
            ->set('itemDescription', 'Instalação')
            ->set('itemQuantity', '0')
            ->set('itemUnit', 'serviço')
            ->set('itemUnitPrice', '100')
            ->call('saveItem')
            ->assertHasErrors([
                'itemQuantity' => 'gt',
            ])
            ->assertSee('A quantidade deve ser maior que zero.');
    }
}
