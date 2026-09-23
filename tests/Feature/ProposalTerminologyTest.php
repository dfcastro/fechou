<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProposalTerminologyTest extends TestCase
{
    use RefreshDatabase;

    private function account(): array
    {
        $user = User::factory()->create();

        $business = Business::factory()->create([
            'user_id' => $user->id,
            'onboarding_completed_at' => now(),
        ]);

        $plan = Plan::create([
            'name' => 'Grátis',
            'slug' => 'free',
            'description' => 'Plano gratuito',
            'price' => 0,
            'billing_interval' => 'month',
            'quote_limit' => 5,
            'features' => [],
            'is_active' => true,
            'sort_order' => 10,
        ]);

        Subscription::create([
            'business_id' => $business->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'current_period_starts_at' => now()->startOfMonth(),
            'current_period_ends_at' => now()->endOfMonth(),
        ]);

        return [$user, $business];
    }

    public function test_first_proposal_returns_to_dashboard(): void
    {
        [$user] = $this->account();

        $this
            ->actingAs($user)
            ->get(route('quotes.create'))
            ->assertOk()
            ->assertSee('Nova proposta')
            ->assertSee('Criar proposta')
            ->assertSee('Voltar ao Dashboard')
            ->assertDontSee('Voltar para propostas');
    }

    public function test_later_proposals_return_to_proposals_list(): void
    {
        [$user, $business] = $this->account();

        $client = Client::factory()->create([
            'business_id' => $business->id,
        ]);

        Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,
        ]);

        $this
            ->actingAs($user)
            ->get(route('quotes.create'))
            ->assertOk()
            ->assertSee('Nova proposta')
            ->assertSee('Voltar para propostas')
            ->assertDontSee('Voltar ao Dashboard');
    }

    public function test_proposals_index_uses_proposal_terminology(): void
    {
        [$user] = $this->account();

        $this
            ->actingAs($user)
            ->get(route('quotes.index'))
            ->assertOk()
            ->assertSee('Propostas')
            ->assertSee('Nova proposta');
    }
}
