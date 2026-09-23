<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotePostAcceptanceBadgeTest extends TestCase
{
    use RefreshDatabase;


    public function test_accepted_quotes_show_post_acceptance_badges(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $business = Business::factory()->create([
            'user_id' => $user->id,
            'onboarding_completed_at' => now(),
        ]);

        $client = Client::factory()->create([
            'business_id' => $business->id,
        ]);


        /*
         * Ainda não pago e ainda não iniciado.
         *
         * Deve exibir dois badges:
         * - Pagamento pendente
         * - Aguardando execução
         */
        Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,

            'title' =>
                'Proposta aguardando execução',

            'status' => 'accepted',
            'accepted_at' => now(),

            'payment_status' => 'pending',

            'execution_status' => 'pending',
        ]);


        /*
         * Pago e em execução.
         */
        Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,

            'title' =>
                'Proposta em execução',

            'status' => 'accepted',
            'accepted_at' => now(),

            'payment_status' => 'paid',
            'paid_at' => now(),

            'execution_status' =>
                'in_progress',

            'execution_started_at' =>
                now(),
        ]);


        /*
         * Pago e concluído.
         */
        Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,

            'title' =>
                'Proposta concluída',

            'status' => 'accepted',
            'accepted_at' => now(),

            'payment_status' => 'paid',
            'paid_at' => now(),

            'execution_status' =>
                'completed',

            'execution_started_at' =>
                now()->subDay(),

            'completed_at' =>
                now(),
        ]);


        $this
            ->actingAs($user)
            ->get(
                route('quotes.index')
            )
            ->assertOk()

            ->assertSee(
                'Pagamento pendente'
            )

            ->assertSee(
                'Aguardando execução'
            )

            ->assertSee(
                'Em execução'
            )

            ->assertSee(
                'Concluído'
            )

            ->assertSee(
                'Proposta aguardando execução'
            )

            ->assertSee(
                'Proposta em execução'
            )

            ->assertSee(
                'Proposta concluída'
            );
    }
}
