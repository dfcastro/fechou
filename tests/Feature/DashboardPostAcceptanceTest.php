<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPostAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_post_acceptance_summary(): void
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
         * Aceita e ainda totalmente pendente.
         */
        Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,
            'status' => 'accepted',
            'accepted_at' => now(),

            'total' => 1000,

            'payment_status' => 'pending',
            'paid_at' => null,

            'execution_status' => 'pending',
            'execution_started_at' => null,
            'completed_at' => null,
        ]);

        /*
         * Pago e em execução.
         */
        Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,
            'status' => 'accepted',
            'accepted_at' => now(),

            'total' => 2000,

            'payment_status' => 'paid',
            'paid_at' => now(),

            'execution_status' => 'in_progress',
            'execution_started_at' => now(),
            'completed_at' => null,
        ]);

        /*
         * Pago e concluído.
         */
        Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,
            'status' => 'accepted',
            'accepted_at' => now(),

            'total' => 3000,

            'payment_status' => 'paid',
            'paid_at' => now(),

            'execution_status' => 'completed',
            'execution_started_at' => now()
                ->subDay(),
            'completed_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pós-aceite')
            ->assertSee('Negócios fechados')
            ->assertSee('A receber')
            ->assertSee('Aguardando execução')
            ->assertSee('Em execução')
            ->assertSee('Concluídos')
            ->assertSee('R$ 6.000,00')
            ->assertSee('R$ 1.000,00')
            ->assertSee('Em acompanhamento');
    }
}
