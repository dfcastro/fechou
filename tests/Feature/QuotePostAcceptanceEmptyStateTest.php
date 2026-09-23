<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotePostAcceptanceEmptyStateTest extends TestCase
{
    use RefreshDatabase;


    private function account(): array
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $business = Business::factory()->create([
            'user_id' => $user->id,
            'onboarding_completed_at' => now(),
        ]);

        return [
            $user,
            $business,
        ];
    }


    public function test_post_acceptance_empty_state_is_contextual(): void
    {
        [
            $user,
            $business,
        ] = $this->account();

        $client = Client::factory()->create([
            'business_id' => $business->id,
        ]);

        /*
         * Existe um negócio fechado,
         * mas ele já foi concluído.
         *
         * Portanto o filtro Em execução
         * deve retornar vazio.
         */
        Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,

            'status' => 'accepted',
            'accepted_at' => now(),

            'payment_status' => 'paid',
            'paid_at' => now(),

            'execution_status' => 'completed',
            'execution_started_at' => now()
                ->subDay(),

            'completed_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->get(
                route(
                    'quotes.index',
                    [
                        'post' =>
                            'in_progress',
                    ]
                )
            )
            ->assertOk()

            ->assertSee(
                'Nenhuma proposta em execução'
            )

            ->assertSee(
                'Não há serviços ou pedidos'
            )

            ->assertSee(
                'em execução no momento.'
            )

            ->assertSee(
                'Limpar filtro'
            )

            ->assertDontSee(
                'Crie sua primeira proposta'
            );
    }


    public function test_real_empty_account_keeps_onboarding_state(): void
    {
        [
            $user,
        ] = $this->account();

        $this
            ->actingAs($user)
            ->get(
                route('quotes.index')
            )
            ->assertOk()

            ->assertSee(
                'Nenhuma proposta ainda'
            )

            ->assertSee(
                'Crie sua primeira proposta'
            )

            ->assertSee(
                'Criar proposta'
            )

            ->assertDontSee(
                'Limpar filtro'
            );
    }
}
