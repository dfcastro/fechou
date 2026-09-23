<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardBusinessPerformanceTest extends TestCase
{
    use RefreshDatabase;


    private function eventAt(
        Quote $quote,
        string $type,
        CarbonImmutable $time
    ): void {
        $event = $quote
            ->events()
            ->create([
                'type' => $type,
            ]);

        $event->forceFill([
            'created_at' => $time,
            'updated_at' => $time,
        ])->saveQuietly();
    }


    private function addCycle(
        Quote $quote,
        CarbonImmutable $base,
        int $viewedMinutes,
        int $acceptedMinutes,
        int $paidMinutes,
        int $completedMinutes
    ): void {
        $this->eventAt(
            $quote,
            'created',
            $base
        );

        $this->eventAt(
            $quote,
            'viewed',
            $base->addMinutes(
                $viewedMinutes
            )
        );

        $this->eventAt(
            $quote,
            'accepted',
            $base->addMinutes(
                $acceptedMinutes
            )
        );

        $this->eventAt(
            $quote,
            'payment_received',
            $base->addMinutes(
                $paidMinutes
            )
        );

        $this->eventAt(
            $quote,
            'execution_completed',
            $base->addMinutes(
                $completedMinutes
            )
        );
    }


    public function test_dashboard_shows_average_business_performance(): void
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

        $base = CarbonImmutable::parse(
            '2026-09-21 10:00:00'
        );


        /*
         * V1 antiga.
         *
         * Tempos enormes para garantir que
         * ela NÃO entre na média.
         */
        $v1 = Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,

            'version' => 1,

            'status' => 'accepted',

            'payment_status' => 'paid',

            'execution_status' =>
                'completed',
        ]);

        $this->addCycle(
            $v1,
            $base,
            1000,
            2000,
            3000,
            4000
        );


        /*
         * V2 atual da família.
         */
        $v2 = Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,

            'root_quote_id' =>
                $v1->id,

            'version' => 2,

            'status' => 'accepted',

            'payment_status' => 'paid',

            'execution_status' =>
                'completed',
        ]);

        $this->addCycle(
            $v2,
            $base,
            10,
            30,
            60,
            300
        );


        /*
         * Segunda família.
         */
        $quote2 = Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,

            'version' => 1,

            'status' => 'accepted',

            'payment_status' => 'paid',

            'execution_status' =>
                'completed',
        ]);

        $this->addCycle(
            $quote2,
            $base,
            20,
            60,
            90,
            420
        );


        /*
         * Médias:
         *
         * Visualização:
         * (10 + 20) / 2 = 15 min
         *
         * Aceite:
         * (30 + 60) / 2 = 45 min
         *
         * Pagamento:
         * (30 + 30) / 2 = 30 min
         *
         * Ciclo:
         * (5 h + 7 h) / 2 = 6 h
         */
        $this
            ->actingAs($user)
            ->get(
                route('dashboard')
            )
            ->assertOk()

            ->assertSee(
                'Desempenho do negócio'
            )

            ->assertSee(
                'Tempo médio até visualizar'
            )

            ->assertSee(
                'Tempo médio até aceitar'
            )

            ->assertSee(
                'Tempo médio até pagamento'
            )

            ->assertSee(
                'Ciclo médio do negócio'
            )

            ->assertSee(
                '15 min'
            )

            ->assertSee(
                '45 min'
            )

            ->assertSee(
                '30 min'
            )

            ->assertSee(
                '6 h'
            )

            ->assertSee(
                '2 propostas'
            );
    }


    public function test_dashboard_ignores_missing_stages(): void
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

            'status' => 'sent',
        ]);

        $base = CarbonImmutable::parse(
            '2026-09-21 10:00:00'
        );

        $this->eventAt(
            $quote,
            'created',
            $base
        );


        $this
            ->actingAs($user)
            ->get(
                route('dashboard')
            )
            ->assertOk()

            ->assertSee(
                'Sem dados suficientes'
            );
    }
}
