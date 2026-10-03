<?php

namespace Tests\Feature\Billing;

use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class StripeCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_cancel_stripe_renewal_and_keep_access_until_period_end(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $business = Business::factory()->create([
            'user_id' => $user->id,
        ]);

        $plan = Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'description' => 'Plano profissional',
            'price' => 29.90,
            'billing_interval' => 'month',
            'quote_limit' => null,
            'features' => [],
            'is_active' => true,
            'sort_order' => 20,
        ]);

        $periodEnd = now()
            ->addDays(20)
            ->startOfSecond();

        $subscription =
            Subscription::create([
                'business_id' =>
                    $business->id,

                'plan_id' =>
                    $plan->id,

                'status' =>
                    'active',

                'billing_status' =>
                    'current',

                'payment_provider' =>
                    'stripe',

                'provider_subscription_id' =>
                    'sub_test_123',

                'starts_at' =>
                    now(),

                'current_period_starts_at' =>
                    now(),

                'current_period_ends_at' =>
                    $periodEnd,
            ]);

        $this->mock(
            StripeService::class,
            function (
                MockInterface $mock
            ) use (
                $subscription
            ): void {
                $mock
                    ->shouldReceive(
                        'scheduleCancellation'
                    )
                    ->once()
                    ->withArgs(
                        fn ($argument) =>
                            $argument->is(
                                $subscription
                            )
                    );
            }
        );

        $this
            ->actingAs($user)
            ->delete(
                route(
                    'settings.subscription.cancel.stripe'
                )
            )
            ->assertRedirect();

        $subscription->refresh();

        $this->assertSame(
            'active',
            $subscription->status
        );

        $this->assertSame(
            'canceling',
            $subscription
                ->billing_status
        );

        $this->assertNotNull(
            $subscription
                ->canceled_at
        );

        $this->assertTrue(
            $subscription
                ->ends_at
                ->isSameSecond(
                    $periodEnd
                )
        );
    }

    public function test_stripe_pro_page_shows_cancel_option(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $business = Business::factory()->create([
            'user_id' => $user->id,
        ]);

        $plan = Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'description' => 'Plano profissional',
            'price' => 29.90,
            'billing_interval' => 'month',
            'quote_limit' => null,
            'features' => [],
            'is_active' => true,
            'sort_order' => 20,
        ]);

        Subscription::create([
            'business_id' =>
                $business->id,

            'plan_id' =>
                $plan->id,

            'status' =>
                'active',

            'billing_status' =>
                'current',

            'payment_provider' =>
                'stripe',

            'provider_subscription_id' =>
                'sub_test_123',

            'starts_at' =>
                now(),

            'current_period_starts_at' =>
                now(),

            'current_period_ends_at' =>
                now()->addMonth(),
        ]);

        $this
            ->actingAs($user)
            ->get(
                route(
                    'settings.subscription'
                )
            )
            ->assertOk()
            ->assertSee(
                'Cancelar assinatura'
            );
    }
}
