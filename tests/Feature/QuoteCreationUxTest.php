<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuoteCreationUxTest extends TestCase
{
    use RefreshDatabase;

    private function account(
        bool $verified = true
    ): array {
        $user = User::factory()->create([
            'email_verified_at' =>
                $verified ? now() : null,
        ]);

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
            'current_period_starts_at' =>
                now()->startOfMonth(),
            'current_period_ends_at' =>
                now()->endOfMonth(),
        ]);

        return [
            $user,
            $business,
        ];
    }

    public function test_create_form_shows_friendly_validation_summary(): void
    {
        [$user] = $this->account();

        Livewire::actingAs($user)
            ->test('pages::quotes.create')
            ->set(
                'validUntil',
                now()->subDay()->format('Y-m-d')
            )
            ->call('save')
            ->assertHasErrors([
                'clientId',
                'title',
                'validUntil',
                'items',
            ])
            ->assertSee(
                'Revise os campos abaixo'
            )
            ->assertSee(
                'Selecione um cliente.'
            )
            ->assertSee(
                'Informe o título da proposta.'
            )
            ->assertSee(
                'A validade não pode estar no passado.'
            )
            ->assertSee(
                'Adicione pelo menos um item.'
            );
    }

    public function test_created_quote_shows_next_step_card(): void
    {
        [$user, $business] = $this->account();

        $client = Client::factory()->create([
            'business_id' => $business->id,
        ]);

        $quote = Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        $this
            ->actingAs($user)
            ->withSession([
                'quote_created' => true,
                'success' =>
                    'Proposta criada com sucesso.',
            ])
            ->get(
                route(
                    'quotes.show',
                    $quote->id
                )
            )
            ->assertOk()
            ->assertSee('Proposta criada')
            ->assertSee('Revisar PDF')
            ->assertSee('Copiar link')
            ->assertSee('Enviar por WhatsApp');
    }

    public function test_unverified_user_gets_verification_step_after_creation(): void
    {
        [$user, $business] = $this->account(false);

        $client = Client::factory()->create([
            'business_id' => $business->id,
        ]);

        $quote = Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        $this
            ->actingAs($user)
            ->withSession([
                'quote_created' => true,
                'success' =>
                    'Proposta criada com sucesso.',
            ])
            ->get(
                route(
                    'quotes.show',
                    $quote->id
                )
            )
            ->assertOk()
            ->assertSee(
                'Confirmar e-mail para compartilhar'
            )
            ->assertDontSee(
                'Enviar por WhatsApp'
            );
    }
}
