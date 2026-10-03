<?php

namespace Tests\Feature\Billing;

use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class StripeCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_user_can_start_stripe_checkout(): void
    {
        config([
            'services.payment.provider' =>
                'stripe',
        ]);

        $user = User::factory()->create([
            'email_verified_at' =>
                now(),
        ]);

        $business =
            Business::factory()->create([
                'user_id' =>
                    $user->id,

                'name' =>
                    'Empresa Stripe',
            ]);

        $free = Plan::create([
            'name' =>
                'Grátis',

            'slug' =>
                'free',

            'price' =>
                0,

            'billing_interval' =>
                'month',

            'quote_limit' =>
                5,

            'features' =>
                [],

            'is_active' =>
                true,

            'sort_order' =>
                10,
        ]);

        $pro = Plan::create([
            'name' =>
                'Pro',

            'slug' =>
                'pro',

            'price' =>
                29.90,

            'billing_interval' =>
                'month',

            'quote_limit' =>
                null,

            'features' =>
                [],

            'is_active' =>
                true,

            'sort_order' =>
                20,
        ]);

        $subscription =
            Subscription::create([
                'business_id' =>
                    $business->id,

                'plan_id' =>
                    $free->id,

                'status' =>
                    'active',

                'starts_at' =>
                    now(),

                'current_period_starts_at' =>
                    now()->startOfMonth(),

                'current_period_ends_at' =>
                    now()->endOfMonth(),
            ]);

        $stripe =
            Mockery::mock(
                StripeService::class
            );

        $stripe
            ->shouldReceive(
                'createProCheckout'
            )
            ->once()
            ->withArgs(
                function (
                    Business $receivedBusiness,
                    Subscription $receivedSubscription,
                    Plan $receivedPlan
                ) use (
                    $business,
                    $subscription,
                    $pro
                ): bool {
                    return
                        $receivedBusiness->is(
                            $business
                        )
                        && $receivedSubscription->is(
                            $subscription
                        )
                        && $receivedPlan->is(
                            $pro
                        );
                }
            )
            ->andReturn([
                'id' =>
                    'cs_test_123',

                'url' =>
                    'https://checkout.stripe.com/test',

                'status' =>
                    'open',

                'customer_id' =>
                    'cus_test_123',
            ]);

        $this->app->instance(
            StripeService::class,
            $stripe
        );

        $this
            ->actingAs($user)
            ->post(
                route(
                    'settings.subscription.checkout.stripe'
                )
            )
            ->assertRedirect(
                'https://checkout.stripe.com/test'
            );

        $subscription->refresh();

        $this->assertSame(
            'stripe',
            $subscription
                ->payment_provider
        );

        $this->assertSame(
            'cus_test_123',
            $subscription
                ->provider_customer_id
        );

        $this->assertSame(
            'cs_test_123',
            $subscription
                ->provider_checkout_id
        );

        $this->assertSame(
            'open',
            $subscription
                ->provider_checkout_status
        );

        /*
         * Checkout criado ainda não libera Pro.
         */
        $this->assertSame(
            'free',
            $subscription
                ->fresh()
                ->plan
                ->slug
        );
    }
}
