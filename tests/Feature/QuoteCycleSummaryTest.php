<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteCycleSummaryTest extends TestCase
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


    public function test_quote_shows_cycle_summary_with_real_durations(): void
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

            'payment_status' => 'paid',

            'execution_status' =>
                'completed',
        ]);


        $base = CarbonImmutable::parse(
            '2026-09-21 10:00:00'
        );

        $this->eventAt(
            $quote,
            'created',
            $base
        );

        $this->eventAt(
            $quote,
            'viewed',
            $base->addMinutes(2)
        );

        $this->eventAt(
            $quote,
            'accepted',
            $base->addMinutes(5)
        );

        $this->eventAt(
            $quote,
            'payment_received',
            $base->addMinutes(8)
        );

        $this->eventAt(
            $quote,
            'execution_started',
            $base->addMinutes(10)
        );

        $this->eventAt(
            $quote,
            'execution_completed',
            $base->addMinutes(20)
        );


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
                'Resumo do ciclo'
            )

            ->assertSee(
                'Até visualizar'
            )

            ->assertSee(
                'Até aceitar'
            )

            ->assertSee(
                'Até pagamento'
            )

            ->assertSee(
                'Ciclo completo'
            )

            ->assertSee(
                '2 min'
            )

            ->assertSee(
                '5 min'
            )

            ->assertSee(
                '3 min'
            )

            ->assertSee(
                '20 min'
            )

            ->assertSee(
                'Criação → visualização'
            )

            ->assertSee(
                'Aceite → pagamento'
            )

            ->assertSee(
                'Criação → conclusão'
            );
    }


    public function test_incomplete_cycle_shows_pending_states(): void
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

            'payment_status' =>
                'pending',

            'execution_status' =>
                'pending',
        ]);


        $base = CarbonImmutable::parse(
            '2026-09-21 10:00:00'
        );

        $this->eventAt(
            $quote,
            'created',
            $base
        );

        $this->eventAt(
            $quote,
            'accepted',
            $base->addMinutes(5)
        );


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
                'Pendente'
            )

            ->assertSee(
                'Em andamento'
            );
    }
}
