<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardCommercialConversionTest extends TestCase
{
    use RefreshDatabase;


    private function viewed(
        Quote $quote
    ): void {
        $quote
            ->events()
            ->create([
                'type' => 'viewed',
            ]);
    }


    public function test_dashboard_shows_commercial_conversion_rates(): void
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
         * Família A:
         *
         * V1 antiga foi aceita.
         * NÃO pode entrar nos cálculos.
         */
        $v1 = Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,

            'version' => 1,

            'status' => 'accepted',
        ]);

        $this->viewed(
            $v1
        );


        /*
         * V2 atual da mesma família:
         * enviada e ainda sem visualização.
         */
        Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,

            'root_quote_id' =>
                $v1->id,

            'version' => 2,

            'status' => 'sent',
        ]);


        /*
         * Família B:
         * aceita e visualizada.
         */
        $accepted = Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,

            'version' => 1,

            'status' => 'accepted',
        ]);

        $this->viewed(
            $accepted
        );


        /*
         * Família C:
         * recusada e visualizada.
         */
        $rejected = Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,

            'version' => 1,

            'status' => 'rejected',
        ]);

        $this->viewed(
            $rejected
        );


        /*
         * Família D:
         * expirada, mas foi visualizada.
         */
        $expired = Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,

            'version' => 1,

            'status' => 'expired',
        ]);

        $this->viewed(
            $expired
        );


        /*
         * Família E:
         * rascunho.
         *
         * Deve ficar completamente fora
         * das taxas comerciais.
         */
        Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,

            'version' => 1,

            'status' => 'draft',
        ]);


        /*
         * Versões atuais que chegaram ao envio:
         *
         * A V2 sent
         * B accepted
         * C rejected
         * D expired
         *
         * total = 4
         *
         * Visualizadas:
         * B + C + D = 3 / 4 = 75%
         *
         * Decisões:
         * B accepted + C rejected = 2
         *
         * Aceite:
         * 1 / 2 = 50%
         *
         * Conversão:
         * 1 / 4 = 25%
         *
         * Recusa:
         * 1 / 2 = 50%
         */
        $this
            ->actingAs($user)
            ->get(
                route('dashboard')
            )
            ->assertOk()

            ->assertSee(
                'Conversão comercial'
            )

            ->assertSee(
                'Taxa de visualização'
            )

            ->assertSee(
                'Taxa de aceite'
            )

            ->assertSee(
                'Conversão em negócio'
            )

            ->assertSee(
                'Taxa de recusa'
            )

            ->assertSee(
                '75%'
            )

            ->assertSee(
                '25%'
            )

            ->assertSee(
                '50%'
            )

            ->assertSee(
                '3'
            )

            ->assertSee(
                '4'
            )

            ->assertSee(
                'Visualizadas / enviadas'
            )

            ->assertSee(
                'Aceitas / decisões'
            )

            ->assertSee(
                'Aceitas / enviadas'
            )

            ->assertSee(
                'Recusadas / decisões'
            );
    }


    public function test_dashboard_conversion_handles_empty_funnel(): void
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


        Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,

            'status' => 'draft',
        ]);


        $this
            ->actingAs($user)
            ->get(
                route('dashboard')
            )
            ->assertOk()

            ->assertSee(
                'Conversão comercial'
            )

            ->assertSee(
                'Sem dados suficientes'
            );
    }
}
