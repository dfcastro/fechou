<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteQuickActionsTest extends TestCase
{
    use RefreshDatabase;


    private function account(): array
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


    public function test_pending_accepted_quote_shows_payment_and_start_actions(): void
    {
        [
            $user,
            $business,
            $client,
        ] = $this->account();

        Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,

            'status' => 'accepted',
            'accepted_at' => now(),

            'payment_status' => 'pending',
            'execution_status' => 'pending',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(
                route('quotes.index')
            );

        $response
            ->assertOk()
            ->assertSee(
                'data-quick-action="payment"',
                false
            )
            ->assertSee(
                'data-quick-action="start"',
                false
            )
            ->assertDontSee(
                'data-quick-action="complete"',
                false
            );
    }


    public function test_in_progress_paid_quote_only_shows_complete_action(): void
    {
        [
            $user,
            $business,
            $client,
        ] = $this->account();

        Quote::factory()->create([
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

        $response = $this
            ->actingAs($user)
            ->get(
                route('quotes.index')
            );

        $response
            ->assertOk()
            ->assertDontSee(
                'data-quick-action="payment"',
                false
            )
            ->assertDontSee(
                'data-quick-action="start"',
                false
            )
            ->assertSee(
                'data-quick-action="complete"',
                false
            );
    }


    public function test_fully_completed_quote_has_no_quick_actions(): void
    {
        [
            $user,
            $business,
            $client,
        ] = $this->account();

        Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,

            'status' => 'accepted',
            'accepted_at' => now(),

            'payment_status' => 'paid',
            'paid_at' => now(),

            'execution_status' =>
                'completed',

            'execution_started_at' =>
                now()->subDay(),

            'completed_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->get(
                route('quotes.index')
            );

        $response
            ->assertOk()
            ->assertDontSee(
                'data-quick-action="payment"',
                false
            )
            ->assertDontSee(
                'data-quick-action="start"',
                false
            )
            ->assertDontSee(
                'data-quick-action="complete"',
                false
            );
    }
}
