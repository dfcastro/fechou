<?php

namespace Tests\Feature\Billing;

use App\Enums\PlanFeature;
use App\Models\Business;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AsaasPaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $token =
        'webhook-test-token-with-at-least-32-characters';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.asaas.webhook_token' =>
                $this->token,
        ]);
    }

    private function createAccount(): array
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $business = Business::factory()->create([
            'user_id' => $user->id,
        ]);

        $free = Plan::create([
            'name' => 'Grátis',
            'slug' => 'free',
            'price' => 0,
            'billing_interval' => 'month',
            'quote_limit' => 5,
            'features' => [
                PlanFeature::CLIENT_MANAGEMENT->value,
                PlanFeature::PUBLIC_QUOTE_LINK->value,
                PlanFeature::PDF_EXPORT->value,
                PlanFeature::WHATSAPP_SHARING->value,
            ],
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $pro = Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price' => 29.90,
            'billing_interval' => 'month',
            'quote_limit' => null,
            'features' => [
                PlanFeature::CLIENT_MANAGEMENT->value,
                PlanFeature::PUBLIC_QUOTE_LINK->value,
                PlanFeature::PDF_EXPORT->value,
                PlanFeature::WHATSAPP_SHARING->value,
                PlanFeature::QUOTE_VERSIONING->value,
                PlanFeature::FOLLOW_UP->value,
                PlanFeature::NOTIFICATIONS->value,
                PlanFeature::CUSTOM_BRANDING->value,
            ],
            'is_active' => true,
            'sort_order' => 20,
        ]);

        $subscription = Subscription::create([
            'business_id' => $business->id,
            'plan_id' => $pro->id,
            'status' => 'active',
            'billing_status' => 'current',
            'starts_at' => now(),
            'current_period_starts_at' =>
                now()->startOfMonth(),
            'current_period_ends_at' =>
                now()->endOfMonth(),
            'payment_provider' => 'asaas',
            'provider_customer_id' =>
                'cus_test_123',
            'provider_subscription_id' =>
                'sub_test_123',
        ]);

        return [
            $user,
            $business,
            $subscription,
            $free,
            $pro,
        ];
    }

    private function postPayment(
        string $event,
        array $payment = []
    ) {
        static $sequence = 0;
        $sequence++;

        return $this->postJson(
            route('webhooks.asaas'),
            [
                'id' =>
                    'evt_payment_'
                    . $sequence,

                'event' =>
                    $event,

                'payment' =>
                    array_merge(
                        [
                            'id' =>
                                'pay_test_'
                                . $sequence,

                            'customer' =>
                                'cus_test_123',

                            'subscription' =>
                                'sub_test_123',

                            'status' =>
                                str_replace(
                                    'PAYMENT_',
                                    '',
                                    $event
                                ),

                            'dueDate' =>
                                '2026-10-09',
                        ],
                        $payment
                    ),
            ],
            [
                'asaas-access-token' =>
                    $this->token,
            ]
        );
    }

    public function test_payment_overdue_starts_three_day_grace_period(): void
    {
        Carbon::setTestNow(
            '2026-10-09 10:00:00'
        );

        [
            ,
            $business,
            $subscription,
        ] = $this->createAccount();

        $this
            ->postPayment(
                'PAYMENT_OVERDUE'
            )
            ->assertOk();

        $subscription->refresh();

        $this->assertSame(
            'past_due',
            $subscription->status
        );

        $this->assertSame(
            'overdue',
            $subscription->billing_status
        );

        $this->assertTrue(
            $subscription
                ->grace_ends_at
                ->equalTo(
                    now()->addDays(3)
                )
        );

        /*
         * Durante a tolerância, Pro continua liberado.
         */
        $this->assertTrue(
            app(
                SubscriptionService::class
            )->hasFeature(
                $business,
                PlanFeature::FOLLOW_UP
            )
        );

        Carbon::setTestNow();
    }

    public function test_after_grace_access_falls_back_to_free_plan(): void
    {
        Carbon::setTestNow(
            '2026-10-13 10:00:00'
        );

        [
            ,
            $business,
            $subscription,
        ] = $this->createAccount();

        $subscription->update([
            'status' => 'past_due',
            'billing_status' => 'overdue',
            'past_due_at' =>
                now()->subDays(4),
            'grace_ends_at' =>
                now()->subDay(),
        ]);

        $service = app(
            SubscriptionService::class
        );

        $this->assertFalse(
            $service->hasFeature(
                $business,
                PlanFeature::FOLLOW_UP
            )
        );

        $this->assertTrue(
            $service->hasFeature(
                $business,
                PlanFeature::PDF_EXPORT
            )
        );

        $this->assertSame(
            'free',
            $service
                ->accessPlan($business)
                ?->slug
        );

        Carbon::setTestNow();
    }

    public function test_payment_confirmed_recovers_access_and_renews_period(): void
    {
        Carbon::setTestNow(
            '2026-10-10 12:00:00'
        );

        [
            ,
            $business,
            $subscription,
        ] = $this->createAccount();

        $subscription->update([
            'status' => 'past_due',
            'billing_status' => 'overdue',
            'past_due_at' =>
                now()->subDay(),
            'grace_ends_at' =>
                now()->addDays(2),
        ]);

        $this
            ->postPayment(
                'PAYMENT_CONFIRMED',
                [
                    'status' =>
                        'CONFIRMED',
                    'dueDate' =>
                        '2026-10-09',
                ]
            )
            ->assertOk();

        $subscription->refresh();

        $this->assertSame(
            'active',
            $subscription->status
        );

        $this->assertSame(
            'current',
            $subscription->billing_status
        );

        $this->assertNull(
            $subscription->past_due_at
        );

        $this->assertNull(
            $subscription->grace_ends_at
        );

        $this->assertNull(
            $subscription
                ->access_suspended_at
        );

        $this->assertSame(
            '2026-10-09',
            $subscription
                ->current_period_starts_at
                ->toDateString()
        );

        $this->assertSame(
            '2026-11-08',
            $subscription
                ->current_period_ends_at
                ->toDateString()
        );

        $this->assertTrue(
            app(
                SubscriptionService::class
            )->hasFeature(
                $business,
                PlanFeature::FOLLOW_UP
            )
        );

        Carbon::setTestNow();
    }

    public function test_refund_blocks_pro_immediately_but_keeps_data(): void
    {
        [
            ,
            $business,
            $subscription,
        ] = $this->createAccount();

        $this
            ->postPayment(
                'PAYMENT_REFUNDED',
                [
                    'status' =>
                        'REFUNDED',
                ]
            )
            ->assertOk();

        $subscription->refresh();

        $this->assertSame(
            'past_due',
            $subscription->status
        );

        $this->assertSame(
            'refunded',
            $subscription->billing_status
        );

        $this->assertNotNull(
            $subscription
                ->access_suspended_at
        );

        $this->assertSame(
            'pro',
            $subscription
                ->plan
                ->slug
        );

        $this->assertFalse(
            app(
                SubscriptionService::class
            )->hasFeature(
                $business,
                PlanFeature::FOLLOW_UP
            )
        );
    }

    public function test_unrelated_payment_without_subscription_is_ignored(): void
    {
        $this->createAccount();

        $this
            ->postPayment(
                'PAYMENT_CONFIRMED',
                [
                    'subscription' =>
                        null,
                ]
            )
            ->assertOk();
    }
}
