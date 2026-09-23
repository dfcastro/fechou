<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotePipelineTest extends TestCase
{
    use RefreshDatabase;


    private function userWithBusiness(): array
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

        return [
            $user,
            $business,
            $client,
        ];
    }


    public function test_pipeline_page_shows_operational_columns(): void
    {
        [
            $user,
            $business,
            $client,
        ] = $this->userWithBusiness();


        Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,

            'title' =>
                'Aguardando cliente teste',

            'status' =>
                'sent',

            'sent_at' =>
                now()->subHours(2),
        ]);


        Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,

            'title' =>
                'Visualizada teste',

            'status' =>
                'viewed',

            'first_viewed_at' =>
                now()->subHour(),
        ]);


        Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,

            'title' =>
                'Recebivel teste',

            'status' =>
                'accepted',

            'payment_status' =>
                'pending',

            'execution_status' =>
                'pending',

            'accepted_at' =>
                now()->subMinutes(50),
        ]);


        Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,

            'title' =>
                'Aguardando execucao teste',

            'status' =>
                'accepted',

            'payment_status' =>
                'paid',

            'execution_status' =>
                'pending',

            'paid_at' =>
                now()->subMinutes(40),
        ]);


        Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,

            'title' =>
                'Em execucao teste',

            'status' =>
                'accepted',

            'payment_status' =>
                'pending',

            'execution_status' =>
                'in_progress',

            'execution_started_at' =>
                now()->subMinutes(30),
        ]);


        Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,

            'title' =>
                'Concluida teste',

            'status' =>
                'accepted',

            'payment_status' =>
                'paid',

            'execution_status' =>
                'completed',

            'completed_at' =>
                now()->subMinutes(20),
        ]);


        $this
            ->actingAs($user)
            ->get(
                route('quotes.pipeline')
            )
            ->assertOk()

            ->assertSee(
                'Pipeline'
            )

            ->assertSee(
                'Aguardando cliente'
            )

            ->assertSee(
                'Visualizadas'
            )

            ->assertSee(
                'A receber'
            )

            ->assertSee(
                'Aguardando execução'
            )

            ->assertSee(
                'Em execução'
            )

            ->assertSee(
                'Concluídas'
            )

            ->assertSee(
                'Aguardando cliente teste'
            )

            ->assertSee(
                'Visualizada teste'
            )

            ->assertSee(
                'Recebivel teste'
            )

            ->assertSee(
                'Aguardando execucao teste'
            )

            ->assertSee(
                'Em execucao teste'
            )

            ->assertSee(
                'Concluida teste'
            );
    }


    public function test_pipeline_uses_only_latest_quote_version(): void
    {
        [
            $user,
            $business,
            $client,
        ] = $this->userWithBusiness();


        $v1 = Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,

            'title' =>
                'Versao antiga nao mostrar',

            'version' =>
                1,

            'status' =>
                'accepted',

            'payment_status' =>
                'paid',

            'execution_status' =>
                'completed',
        ]);


        Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,

            'root_quote_id' =>
                $v1->id,

            'title' =>
                'Versao atual mostrar',

            'version' =>
                2,

            'status' =>
                'sent',
        ]);


        $this
            ->actingAs($user)
            ->get(
                route('quotes.pipeline')
            )
            ->assertOk()

            ->assertSee(
                'Versao atual mostrar'
            )

            ->assertDontSee(
                'Versao antiga nao mostrar'
            );
    }
}
