<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\Business;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPlanFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function createSubscription(
        Business $business,
        array $features,
        ?int $quoteLimit
    ): Subscription {
        $plan = Plan::create([
            'name' => $quoteLimit === null ? 'Pro' : 'Grátis',
            'slug' => $quoteLimit === null ? 'pro' : 'free',
            'description' => 'Plano de teste',
            'price' => $quoteLimit === null ? 29.90 : 0,
            'billing_interval' => 'month',
            'quote_limit' => $quoteLimit,
            'features' => $features,
            'is_active' => true,
            'sort_order' => 10,
        ]);

        return Subscription::create([
            'business_id' => $business->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'current_period_starts_at' => now()->startOfMonth(),
            'current_period_ends_at' => now()->endOfMonth(),
        ]);
    }

    private function createQuote(
        Business $business
    ): Quote {
        $client = Client::factory()->create([
            'business_id' => $business->id,
        ]);

        return Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,
        ]);
    }

    public function test_free_plan_sees_follow_up_upgrade_on_dashboard(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $business = Business::factory()->create([
            'user_id' => $user->id,
            'onboarding_completed_at' => now(),
        ]);

        $this->createSubscription(
            $business,
            [
                PlanFeature::CLIENT_MANAGEMENT->value,
                PlanFeature::PUBLIC_QUOTE_LINK->value,
                PlanFeature::PDF_EXPORT->value,
                PlanFeature::WHATSAPP_SHARING->value,
            ],
            5
        );

        $this->createQuote($business);

        $this
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Follow-up inteligente')
            ->assertSee('Veja propostas que precisam de atenção →');
    }

    public function test_pro_plan_sees_follow_up_dashboard(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $business = Business::factory()->create([
            'user_id' => $user->id,
            'onboarding_completed_at' => now(),
        ]);

        $this->createSubscription(
            $business,
            [
                PlanFeature::CLIENT_MANAGEMENT->value,
                PlanFeature::PUBLIC_QUOTE_LINK->value,
                PlanFeature::PDF_EXPORT->value,
                PlanFeature::WHATSAPP_SHARING->value,
                PlanFeature::FOLLOW_UP->value,
                PlanFeature::NOTIFICATIONS->value,
                PlanFeature::CUSTOM_BRANDING->value,
            ],
            null
        );

        $this->createQuote($business);

        $this
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Precisam de atenção')
            ->assertSee('Tudo em dia')
            ->assertDontSee('Veja propostas que precisam de atenção →');
    }
}
