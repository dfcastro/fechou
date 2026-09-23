<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuotePostAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $business = Business::factory()->create([
            'user_id' => $user->id,
        ]);

        $client = Client::factory()->create([
            'business_id' => $business->id,
        ]);

        $quote = Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,

            'status' => 'accepted',
            'accepted_at' => now(),

            'payment_status' => 'pending',
            'paid_at' => null,

            'execution_status' => 'pending',
            'execution_started_at' => null,
            'completed_at' => null,
        ]);

        return [
            $user,
            $quote,
        ];
    }

    public function test_accepted_quote_can_progress_through_post_acceptance_workflow(): void
    {
        [$user, $quote] = $this->scenario();

        $component = Livewire::actingAs($user)
            ->test('pages::quotes.show', [
                'quote' => $quote->id,
            ]);

        /*
         * Pagamento
         */
        $component->call('markAsPaid');

        $quote->refresh();

        $this->assertSame(
            'paid',
            $quote->payment_status
        );

        $this->assertNotNull(
            $quote->paid_at
        );

        $this->assertDatabaseHas(
            'quote_events',
            [
                'quote_id' => $quote->id,
                'type' => 'payment_received',
            ]
        );

        /*
         * Início da execução
         */
        $component->call('startExecution');

        $quote->refresh();

        $this->assertSame(
            'in_progress',
            $quote->execution_status
        );

        $this->assertNotNull(
            $quote->execution_started_at
        );

        $this->assertDatabaseHas(
            'quote_events',
            [
                'quote_id' => $quote->id,
                'type' => 'execution_started',
            ]
        );

        /*
         * Conclusão
         */
        $component->call('completeExecution');

        $quote->refresh();

        $this->assertSame(
            'completed',
            $quote->execution_status
        );

        $this->assertNotNull(
            $quote->completed_at
        );

        $this->assertDatabaseHas(
            'quote_events',
            [
                'quote_id' => $quote->id,
                'type' => 'execution_completed',
            ]
        );
    }
}