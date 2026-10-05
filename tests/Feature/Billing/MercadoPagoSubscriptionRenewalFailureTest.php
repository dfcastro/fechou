<?php

namespace Tests\Feature\Billing;

use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\MercadoPagoService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MercadoPagoSubscriptionRenewalFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejected_renewal_enters_three_day_grace_period(): void
    {
        [$subscription] =
            $this->createProSubscription();

        $invoice = [
            'id' =>
                7000000001,

            'preapproval_id' =>
                'preapproval_test_123',

            'external_reference' =>
                'negozia-subscription-'
                .$subscription->id,

            'transaction_amount' =>
                29.90,

            'status' =>
                'recycling',

            'payment' => [
                'id' =>
                    180000000001,

                'status' =>
                    'rejected',

                'status_detail' =>
                    'cc_rejected_insufficient_amount',
            ],
        ];

        $preapproval = [
            'id' =>
                'preapproval_test_123',

            'status' =>
                'authorized',

            'external_reference' =>
                'negozia-subscription-'
                .$subscription->id,

            'next_payment_date' =>
                now()
                    ->addMonth()
                    ->toIso8601String(),
        ];

        app(
            MercadoPagoService::class
        )->syncSubscriptionAuthorizedPayment(
            $subscription,
            $invoice,
            $preapproval
        );

        $subscription->refresh();

        $this->assertSame(
            'past_due',
            $subscription->status
        );

        $this->assertSame(
            'overdue',
            $subscription->billing_status
        );

        $this->assertSame(
            '180000000001',
            $subscription->provider_payment_id
        );

        $this->assertSame(
            'rejected/cc_rejected_insufficient_amount',
            $subscription->provider_payment_status
        );

        $this->assertNotNull(
            $subscription->past_due_at
        );

        $this->assertNotNull(
            $subscription->grace_ends_at
        );

        $this->assertTrue(
            $subscription
                ->grace_ends_at
                ->isSameSecond(
                    $subscription
                        ->past_due_at
                        ->copy()
                        ->addDays(3)
                )
        );

        $this->assertTrue(
            $subscription->isInGracePeriod()
        );

        $this->assertNull(
            $subscription->access_suspended_at
        );
    }


    public function test_pro_remains_available_during_grace_period(): void
    {
        [$subscription, $business] =
            $this->createProSubscription();

        $subscription->update([
            'status' =>
                'past_due',

            'billing_status' =>
                'overdue',

            'past_due_at' =>
                now(),

            'grace_ends_at' =>
                now()->addDays(3),

            'access_suspended_at' =>
                null,
        ]);

        $plan =
            app(
                SubscriptionService::class
            )->accessPlan(
                $business
            );

        $this->assertSame(
            'pro',
            $plan?->slug
        );
    }


    public function test_suspended_mercado_pago_subscription_falls_back_to_free(): void
    {
        [$subscription, $business] =
            $this->createProSubscription();

        $subscription->update([
            'status' =>
                'past_due',

            'billing_status' =>
                'overdue',

            'past_due_at' =>
                now()->subDays(4),

            'grace_ends_at' =>
                now()->subDay(),

            'access_suspended_at' =>
                now(),
        ]);

        $plan =
            app(
                SubscriptionService::class
            )->accessPlan(
                $business
            );

        $this->assertSame(
            'free',
            $plan?->slug
        );
    }


    public function test_approved_retry_recovers_subscription_from_grace_period(): void
    {
        [$subscription] =
            $this->createProSubscription();

        $service =
            app(
                MercadoPagoService::class
            );

        $preapproval = [
            'id' =>
                'preapproval_test_123',

            'status' =>
                'authorized',

            'external_reference' =>
                'negozia-subscription-'
                .$subscription->id,

            'next_payment_date' =>
                now()
                    ->addMonth()
                    ->toIso8601String(),
        ];

        /*
         * Primeira tentativa: recusada.
         */
        $service
            ->syncSubscriptionAuthorizedPayment(
                $subscription,
                [
                    'id' =>
                        7000000001,

                    'preapproval_id' =>
                        'preapproval_test_123',

                    'external_reference' =>
                        'negozia-subscription-'
                        .$subscription->id,

                    'transaction_amount' =>
                        29.90,

                    'status' =>
                        'recycling',

                    'payment' => [
                        'id' =>
                            180000000001,

                        'status' =>
                            'rejected',

                        'status_detail' =>
                            'cc_rejected_insufficient_amount',
                    ],
                ],
                $preapproval
            );

        $subscription->refresh();

        $this->assertSame(
            'past_due',
            $subscription->status
        );

        $this->assertSame(
            'overdue',
            $subscription->billing_status
        );

        $this->assertNotNull(
            $subscription->grace_ends_at
        );

        /*
         * Nova tentativa da mesma renovação:
         * pagamento aprovado.
         */
        $service
            ->syncSubscriptionAuthorizedPayment(
                $subscription,
                [
                    'id' =>
                        7000000001,

                    'preapproval_id' =>
                        'preapproval_test_123',

                    'external_reference' =>
                        'negozia-subscription-'
                        .$subscription->id,

                    'transaction_amount' =>
                        29.90,

                    'debit_date' =>
                        now()->toIso8601String(),

                    'status' =>
                        'processed',

                    'payment' => [
                        'id' =>
                            180000000002,

                        'status' =>
                            'approved',

                        'status_detail' =>
                            'accredited',
                    ],
                ],
                $preapproval
            );

        $subscription->refresh();

        $this->assertSame(
            'pro',
            $subscription->plan->slug
        );

        $this->assertSame(
            'active',
            $subscription->status
        );

        $this->assertSame(
            'current',
            $subscription->billing_status
        );

        $this->assertSame(
            '180000000002',
            $subscription->provider_payment_id
        );

        $this->assertSame(
            'approved/accredited',
            $subscription->provider_payment_status
        );

        $this->assertNull(
            $subscription->past_due_at
        );

        $this->assertNull(
            $subscription->grace_ends_at
        );

        $this->assertNull(
            $subscription->access_suspended_at
        );
    }


    public function test_approved_monthly_renewal_advances_billing_period(): void
    {
        [$subscription] =
            $this->createProSubscription();

        $subscription->update([
            'provider_payment_id' =>
                'previous_payment',

            'provider_payment_status' =>
                'approved/accredited',

            'current_period_starts_at' =>
                \Illuminate\Support\Carbon::parse(
                    '2026-09-05 18:30:42'
                ),

            'current_period_ends_at' =>
                \Illuminate\Support\Carbon::parse(
                    '2026-10-05 18:30:41'
                ),
        ]);

        $service =
            app(
                MercadoPagoService::class
            );

        $service
            ->syncSubscriptionAuthorizedPayment(
                $subscription,
                [
                    'id' =>
                        7000000002,

                    'preapproval_id' =>
                        'preapproval_test_123',

                    'external_reference' =>
                        'negozia-subscription-'
                        .$subscription->id,

                    'transaction_amount' =>
                        29.90,

                    'debit_date' =>
                        '2026-10-05T18:30:42-04:00',

                    'status' =>
                        'processed',

                    'payment' => [
                        'id' =>
                            180000000003,

                        'status' =>
                            'approved',

                        'status_detail' =>
                            'accredited',
                    ],
                ],
                [
                    'id' =>
                        'preapproval_test_123',

                    'status' =>
                        'authorized',

                    'external_reference' =>
                        'negozia-subscription-'
                        .$subscription->id,

                    'next_payment_date' =>
                        '2026-11-05T18:30:42-04:00',
                ]
            );

        $subscription->refresh();

        $this->assertSame(
            'current',
            $subscription->billing_status
        );

        $this->assertSame(
            '180000000003',
            $subscription->provider_payment_id
        );

        $this->assertSame(
            'approved/accredited',
            $subscription->provider_payment_status
        );

        $this->assertSame(
            '2026-10-05 22:30:42',
            $subscription
                ->current_period_starts_at
                ->utc()
                ->format('Y-m-d H:i:s')
        );

        $this->assertSame(
            '2026-11-05 22:30:41',
            $subscription
                ->current_period_ends_at
                ->utc()
                ->format('Y-m-d H:i:s')
        );

        $this->assertNull(
            $subscription->past_due_at
        );

        $this->assertNull(
            $subscription->grace_ends_at
        );

        $this->assertNull(
            $subscription->access_suspended_at
        );
    }


    private function createProSubscription(): array
    {
        $user =
            User::factory()->create();

        $business =
            Business::factory()->create([
                'user_id' =>
                    $user->id,
            ]);

        $free =
            Plan::create([
                'name' =>
                    'Grátis',

                'slug' =>
                    'free',

                'description' =>
                    'Plano gratuito',

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

        $pro =
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

        $subscription =
            Subscription::create([
                'business_id' =>
                    $business->id,

                'plan_id' =>
                    $pro->id,

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

                'provider_payment_id' =>
                    'previous_payment',

                'provider_payment_status' =>
                    'approved/accredited',

                'starts_at' =>
                    now()->subMonth(),

                'current_period_starts_at' =>
                    now()->subMonth(),

                'current_period_ends_at' =>
                    now(),
            ]);

        return [
            $subscription,
            $business,
            $free,
            $pro,
        ];
    }
}
