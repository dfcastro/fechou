<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardFirstRunTest extends TestCase
{
    use RefreshDatabase;

    private function completedAccount(): array
    {
        $user = User::factory()->create();

        $business = Business::factory()->create([
            'user_id' => $user->id,
            'onboarding_completed_at' => now(),
        ]);

        return [
            $user,
            $business,
        ];
    }

    public function test_empty_dashboard_guides_user_to_first_quote(): void
    {
        [
            $user,
            $business,
        ] = $this->completedAccount();

        $this
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tudo pronto para começar')
            ->assertSee('Crie sua primeira proposta')
            ->assertSee('Criar primeira proposta')
            ->assertSee('Você pode cadastrar o cliente');
    }

    public function test_first_run_card_disappears_after_first_quote(): void
    {
        [
            $user,
            $business,
        ] = $this->completedAccount();

        $client = Client::factory()->create([
            'business_id' => $business->id,
        ]);

        Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,
        ]);

        $this
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Crie sua primeira proposta')
            ->assertSee('Propostas recentes');
    }
}
