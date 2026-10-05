<?php

namespace Tests\Feature\Billing;

use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MercadoPagoSubscriptionCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_cancel_recurring_renewal_and_keep_pro_until_period_end(): void
    {
        config([
            'services.mercadopago.base_url' =>
                'https://api.mercadopago.com',

            'services.mercadopago.access_token' =>
                'TEST_ACCESS_TOKEN',
        ]);

        $user =
            User::factory()->create([
                'email_verified_at' =>
                    now(),
            ]);

        $business =
            Business::factory()->create([
                'user_id' =>
                    $user->id,
            ]);

        $plan =
            Plan::create([
                'name' =>
                    'Pro',

                'slug' =>
                    'pro',

                'description' =>
                    'Plano profissional',

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

        $periodEnd =
            now()
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
                    'mercadopago_subscription',

                'provider_subscription_id' =>
                    'preapproval_test_123',

                'provider_checkout_status' =>
                    'authorized',

                'starts_at' =>
                    now(),

                'current_period_starts_at' =>
                    now(),

                'current_period_ends_at' =>
                    $periodEnd,
            ]);

        Http::fakeSequence(
            'https://api.mercadopago.com/preapproval/preapproval_test_123'
        )
            ->push(
                [
                    'id' =>
                        'preapproval_test_123',

                    'status' =>
                        'authorized',
                ],
                200
            )
            ->push(
                [
                    'id' =>
                        'preapproval_test_123',

                    'status' =>
                        'cancelled',
                ],
                200
            );

        $this
            ->actingAs(
                $user
            )
            ->delete(
                route(
                    'settings.subscription.cancel.mercadopago'
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
            $subscription->billing_status
        );

        $this->assertSame(
            'cancelled',
            $subscription->provider_checkout_status
        );

        $this->assertNotNull(
            $subscription->canceled_at
        );

        $this->assertTrue(
            $subscription
                ->ends_at
                ->isSameSecond(
                    $periodEnd
                )
        );

        Http::assertSent(
            fn ($request) =>
                $request->method() === 'PUT'
                && $request->url()
                    === 'https://api.mercadopago.com/preapproval/preapproval_test_123'
                && $request['status']
                    === 'cancelled'
        );
    }
}
