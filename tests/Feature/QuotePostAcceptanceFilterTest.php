<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotePostAcceptanceFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_acceptance_filters_from_dashboard(): void
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


        $receivable = Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,

            'title' => 'Pagamento pendente teste',

            'status' => 'accepted',
            'accepted_at' => now(),

            'payment_status' => 'pending',

            'execution_status' => 'pending',
        ]);


        $awaiting = Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,

            'title' => 'Execução aguardando teste',

            'status' => 'accepted',
            'accepted_at' => now(),

            'payment_status' => 'paid',
            'paid_at' => now(),

            'execution_status' => 'pending',
        ]);


        $inProgress = Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,

            'title' => 'Execução andamento teste',

            'status' => 'accepted',
            'accepted_at' => now(),

            'payment_status' => 'paid',
            'paid_at' => now(),

            'execution_status' => 'in_progress',
            'execution_started_at' => now(),
        ]);


        $completed = Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,

            'title' => 'Execução concluída teste',

            'status' => 'accepted',
            'accepted_at' => now(),

            'payment_status' => 'paid',
            'paid_at' => now(),

            'execution_status' => 'completed',
            'execution_started_at' => now()
                ->subDay(),
            'completed_at' => now(),
        ]);


        /*
         * A receber.
         */
        $this
            ->actingAs($user)
            ->get(
                route(
                    'quotes.index',
                    [
                        'post' => 'receivable',
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                $receivable->title
            )
            ->assertDontSee(
                $inProgress->title
            )
            ->assertDontSee(
                $completed->title
            );


        /*
         * Aguardando execução.
         *
         * Inclui tanto pagamento pendente
         * quanto pago, desde que a execução
         * ainda não tenha começado.
         */
        $this
            ->actingAs($user)
            ->get(
                route(
                    'quotes.index',
                    [
                        'post' => 'awaiting',
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                $receivable->title
            )
            ->assertSee(
                $awaiting->title
            )
            ->assertDontSee(
                $inProgress->title
            )
            ->assertDontSee(
                $completed->title
            );


        /*
         * Em execução.
         */
        $this
            ->actingAs($user)
            ->get(
                route(
                    'quotes.index',
                    [
                        'post' => 'in_progress',
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                $inProgress->title
            )
            ->assertDontSee(
                $completed->title
            );


        /*
         * Concluídos.
         */
        $this
            ->actingAs($user)
            ->get(
                route(
                    'quotes.index',
                    [
                        'post' => 'completed',
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                $completed->title
            )
            ->assertDontSee(
                $inProgress->title
            );


        /*
         * Todos os negócios fechados.
         */
        $this
            ->actingAs($user)
            ->get(
                route(
                    'quotes.index',
                    [
                        'post' => 'closed',
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                $receivable->title
            )
            ->assertSee(
                $awaiting->title
            )
            ->assertSee(
                $inProgress->title
            )
            ->assertSee(
                $completed->title
            );
    }
}
