<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteBusinessTimelineTest extends TestCase
{
    use RefreshDatabase;


    public function test_quote_page_shows_business_timeline(): void
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

        $quote = Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,

            'status' => 'accepted',
            'accepted_at' => now(),

            'payment_status' => 'paid',
            'paid_at' => now(),

            'execution_status' =>
                'in_progress',

            'execution_started_at' =>
                now(),
        ]);


        $quote->events()->create([
            'type' => 'sent',
        ]);

        $quote->events()->create([
            'type' => 'viewed',
        ]);

        $quote->events()->create([
            'type' => 'accepted',
        ]);

        $quote->events()->create([
            'type' => 'payment_received',
        ]);

        $quote->events()->create([
            'type' => 'execution_started',
        ]);


        $this
            ->actingAs($user)
            ->get(
                route(
                    'quotes.show',
                    $quote->id
                )
            )
            ->assertOk()

            ->assertSee(
                'Linha do tempo'
            )

            ->assertSee(
                'Histórico comercial e operacional'
            )

            ->assertSee(
                'Proposta enviada'
            )

            ->assertSee(
                'Cliente visualizou'
            )

            ->assertSee(
                'Cliente aceitou'
            )

            ->assertSee(
                'Pagamento recebido'
            )

            ->assertSee(
                'Execução iniciada'
            )

            ->assertSee(
                'Comercial'
            )

            ->assertSee(
                'Cliente'
            )

            ->assertSee(
                'Pagamento'
            )

            ->assertSee(
                'Execução'
            );
    }
}
