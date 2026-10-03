<?php

namespace Tests\Feature\Billing;

use App\Models\Business;
use App\Models\GatewayWebhookEvent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET =
        'whsec_test_negozia';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.stripe.webhook_secret' =>
                self::SECRET,

            'services.stripe.price_pro' =>
                'price_test_negozia_pro',
        ]);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $event = [
            'id' =>
                'evt_invalid',

            'type' =>
                'checkout.session.completed',

            'data' => [
                'object' => [
                    'id' =>
                        'cs_test_invalid',
                ],
            ],
        ];

        $this
            ->postStripeEvent(
                $event,
                'invalid'
            )
            ->assertStatus(400);

        $this->assertDatabaseCount(
            'gateway_webhook_events',
            0
        );
    }

    public function test_checkout_links_stripe_ids_but_does_not_activate_pro(): void
    {
        [
            ,
            ,
            $subscription,
        ] = $this->createFreeAccount();

        $subscription->update([
            'payment_provider' =>
                'stripe',

            'provider_checkout_id' =>
                'cs_test_123',
        ]);

        $event = [
            'id' =>
                'evt_checkout_123',

            'type' =>
                'checkout.session.completed',

            'data' => [
                'object' => [
                    'id' =>
                        'cs_test_123',

                    'object' =>
                        'checkout.session',

                    'status' =>
                        'complete',

                    'payment_status' =>
                        'paid',

                    'customer' =>
                        'cus_test_123',

                    'subscription' =>
                        'sub_test_123',

                    'metadata' => [
                        'local_subscription_id' =>
                            (string) $subscription->id,
                    ],
                ],
            ],
        ];

        $this
            ->postStripeEvent(
                $event
            )
            ->assertOk()
            ->assertJson([
                'received' =>
                    true,
            ]);

        $subscription->refresh();

        $this->assertSame(
            'stripe',
            $subscription->payment_provider
        );

        $this->assertSame(
            'cus_test_123',
            $subscription
                ->provider_customer_id
        );

        $this->assertSame(
            'sub_test_123',
            $subscription
                ->provider_subscription_id
        );

        $this->assertSame(
            'complete',
            $subscription
                ->provider_checkout_status
        );

        $this->assertSame(
            'free',
            $subscription
                ->plan
                ->slug
        );
    }

    public function test_invoice_paid_activates_pro(): void
    {
        Carbon::setTestNow(
            Carbon::parse(
                '2026-10-02 20:00:00'
            )
        );

        [
            ,
            ,
            $subscription,
        ] = $this->createFreeAccount();

        $subscription->update([
            'payment_provider' =>
                'stripe',

            'provider_customer_id' =>
                'cus_test_123',

            'provider_subscription_id' =>
                'sub_test_123',
        ]);

        $periodStart =
            Carbon::parse(
                '2026-10-02 19:30:00'
            );

        $periodEnd =
            Carbon::parse(
                '2026-11-02 19:30:00'
            );

        $event = [
            'id' =>
                'evt_invoice_paid_123',

            'type' =>
                'invoice.paid',

            'data' => [
                'object' => [
                    'id' =>
                        'in_test_123',

                    'object' =>
                        'invoice',

                    'status' =>
                        'paid',

                    'customer' =>
                        'cus_test_123',

                    'parent' => [
                        'type' =>
                            'subscription_details',

                        'subscription_details' => [
                            'subscription' =>
                                'sub_test_123',

                            'metadata' => [
                                'local_subscription_id' =>
                                    (string)
                                    $subscription->id,
                            ],
                        ],
                    ],

                    'lines' => [
                        'data' => [
                            [
                                'metadata' => [
                                    'local_subscription_id' =>
                                        (string)
                                        $subscription->id,
                                ],

                                'period' => [
                                    'start' =>
                                        $periodStart
                                            ->timestamp,

                                    'end' =>
                                        $periodEnd
                                            ->timestamp,
                                ],

                                'pricing' => [
                                    'type' =>
                                        'price_details',

                                    'price_details' => [
                                        'price' =>
                                            'price_test_negozia_pro',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this
            ->postStripeEvent(
                $event
            )
            ->assertOk();

        $subscription->refresh();

        $this->assertSame(
            'pro',
            $subscription
                ->plan
                ->slug
        );

        $this->assertSame(
            'active',
            $subscription->status
        );

        $this->assertSame(
            'current',
            $subscription
                ->billing_status
        );

        $this->assertSame(
            'in_test_123',
            $subscription
                ->provider_payment_id
        );

        $this->assertTrue(
            $subscription
                ->current_period_starts_at
                ->equalTo(
                    $periodStart
                )
        );

        $this->assertTrue(
            $subscription
                ->current_period_ends_at
                ->equalTo(
                    $periodEnd
                        ->copy()
                        ->subSecond()
                )
        );

        $this->assertNotNull(
            $subscription
                ->last_payment_confirmed_at
        );
    }

    public function test_duplicate_event_is_processed_only_once(): void
    {
        [
            ,
            ,
            $subscription,
        ] = $this->createFreeAccount();

        $subscription->update([
            'payment_provider' =>
                'stripe',

            'provider_checkout_id' =>
                'cs_duplicate',
        ]);

        $event = [
            'id' =>
                'evt_duplicate',

            'type' =>
                'checkout.session.completed',

            'data' => [
                'object' => [
                    'id' =>
                        'cs_duplicate',

                    'status' =>
                        'complete',

                    'customer' =>
                        'cus_duplicate',

                    'subscription' =>
                        'sub_duplicate',

                    'metadata' => [
                        'local_subscription_id' =>
                            (string)
                            $subscription->id,
                    ],
                ],
            ],
        ];

        $this
            ->postStripeEvent(
                $event
            )
            ->assertOk();

        $this
            ->postStripeEvent(
                $event
            )
            ->assertOk()
            ->assertJson([
                'received' =>
                    true,

                'duplicate' =>
                    true,
            ]);

        $this->assertSame(
            1,
            GatewayWebhookEvent::query()
                ->where(
                    'provider',
                    'stripe'
                )
                ->where(
                    'provider_event_id',
                    'evt_duplicate'
                )
                ->count()
        );
    }

    public function test_failed_renewal_enters_three_day_grace_period(): void
    {
        Carbon::setTestNow(
            Carbon::parse(
                '2026-10-02 20:00:00'
            )
        );

        [
            ,
            ,
            $subscription,
        ] = $this->createFreeAccount();

        $pro = Plan::query()
            ->where(
                'slug',
                'pro'
            )
            ->firstOrFail();

        $subscription->update([
            'plan_id' =>
                $pro->id,

            'status' =>
                'active',

            'billing_status' =>
                'current',

            'payment_provider' =>
                'stripe',

            'provider_customer_id' =>
                'cus_test_failed',

            'provider_subscription_id' =>
                'sub_test_failed',
        ]);

        $event = [
            'id' =>
                'evt_invoice_failed',

            'type' =>
                'invoice.payment_failed',

            'data' => [
                'object' => [
                    'id' =>
                        'in_test_failed',

                    'status' =>
                        'open',

                    'customer' =>
                        'cus_test_failed',

                    'parent' => [
                        'subscription_details' => [
                            'subscription' =>
                                'sub_test_failed',

                            'metadata' => [
                                'local_subscription_id' =>
                                    (string)
                                    $subscription->id,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this
            ->postStripeEvent(
                $event
            )
            ->assertOk();

        $subscription->refresh();

        $this->assertSame(
            'past_due',
            $subscription->status
        );

        $this->assertSame(
            'overdue',
            $subscription
                ->billing_status
        );

        $this->assertTrue(
            $subscription
                ->grace_ends_at
                ->equalTo(
                    now()->addDays(3)
                )
        );

        $this->assertNull(
            $subscription
                ->access_suspended_at
        );
    }

    private function postStripeEvent(
        array $event,
        ?string $signature = null
    ) {
        $payload = json_encode(
            $event,
            JSON_UNESCAPED_SLASHES
        );

        $timestamp = time();

        $signature ??=
            't='
            . $timestamp
            . ',v1='
            . hash_hmac(
                'sha256',
                $timestamp
                . '.'
                . $payload,
                self::SECRET
            );

        return $this->call(
            'POST',
            route(
                'webhooks.stripe'
            ),
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' =>
                    $signature,

                'CONTENT_TYPE' =>
                    'application/json',

                'HTTP_ACCEPT' =>
                    'application/json',
            ],
            $payload
        );
    }

    private function createFreeAccount(): array
    {
        $user =
            User::factory()
                ->create([
                    'email_verified_at' =>
                        now(),
                ]);

        $business =
            Business::factory()
                ->create([
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
                    $free->id,

                'status' =>
                    'active',

                'starts_at' =>
                    now(),

                'current_period_starts_at' =>
                    now()
                        ->startOfMonth(),

                'current_period_ends_at' =>
                    now()
                        ->endOfMonth(),
            ]);

        return [
            $user,
            $business,
            $subscription,
        ];
    }
}
