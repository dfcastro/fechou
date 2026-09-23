<?php

namespace Tests\Feature\Settings;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function createFreePlan(): Plan
    {
        return Plan::create([
            'name' => 'Grátis',
            'slug' => 'free',
            'description' =>
                'Plano gratuito para começar a usar o Fechou.',
            'price' => 0,
            'billing_interval' => 'month',
            'quote_limit' => null,
            'is_active' => true,
            'sort_order' => 10,
        ]);
    }

    public function test_opening_business_settings_creates_business_and_free_subscription(): void
    {
        $plan = $this->createFreePlan();

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->assertNull(
            $user->business
        );

        $response = $this
            ->actingAs($user)
            ->get(route('settings.business'));

        $response->assertOk();

        $user->refresh();

        $this->assertNotNull(
            $user->business
        );

        $this->assertDatabaseHas(
            'subscriptions',
            [
                'business_id' =>
                    $user->business->id,

                'plan_id' =>
                    $plan->id,

                'status' =>
                    'active',
            ]
        );

        $subscription = $user
            ->business
            ->currentSubscription;

        $this->assertNotNull(
            $subscription
        );

        $this->assertSame(
            'free',
            $subscription->plan->slug
        );
    }

    public function test_visiting_business_settings_again_does_not_duplicate_subscription(): void
    {
        $this->createFreePlan();

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->get(route('settings.business'))
            ->assertOk();

        $this
            ->actingAs($user)
            ->get(route('settings.business'))
            ->assertOk();

        $user->refresh();

        $this->assertDatabaseCount(
            'businesses',
            1
        );

        $this->assertDatabaseCount(
            'subscriptions',
            1
        );
    }
}