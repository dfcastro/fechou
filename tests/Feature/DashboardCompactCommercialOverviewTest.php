<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardCompactCommercialOverviewTest extends TestCase
{
    use RefreshDatabase;


    public function test_dashboard_prioritizes_recent_quotes_before_analytics(): void
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
        ]);


        $response =
            $this
                ->actingAs($user)
                ->get(
                    route('dashboard')
                );


        $response
            ->assertOk()

            ->assertSee(
                'Propostas recentes'
            )

            ->assertSee(
                'Desempenho comercial'
            )

            ->assertSee(
                'Ver detalhes'
            )

            ->assertSee(
                'Conversão em negócio'
            )

            ->assertSee(
                'Taxa de visualização'
            )

            ->assertSee(
                'Tempo médio até aceitar'
            )

            ->assertSee(
                'Ciclo médio do negócio'
            )

            ->assertSee(
                'Conversão comercial'
            )

            ->assertSee(
                'Desempenho do negócio'
            );


        $html =
            $response->getContent();


        $recentPosition =
            strpos(
                $html,
                'Propostas recentes'
            );

        $performancePosition =
            strpos(
                $html,
                'Desempenho comercial'
            );


        $this->assertNotFalse(
            $recentPosition
        );

        $this->assertNotFalse(
            $performancePosition
        );


        $this->assertLessThan(
            $performancePosition,
            $recentPosition,
            'Propostas recentes deve aparecer '
            . 'antes do bloco analítico.'
        );
    }
}
