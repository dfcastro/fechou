<?php

namespace Tests\Feature\Billing;

use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MercadoPagoSubscriptionWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.mercadopago.base_url' =>
                'https://api.mercadopago.com',

            'services.mercadopago.access_token' =>
                'TEST_ACCESS_TOKEN',

            'services.mercadopago.webhook_secret' =>
                'TEST_WEBHOOK_SECRET',
        ]);
    }

    public function test_approved_recurring_payment_activates_pro(): void
    {
        $subscription =
            $this->createSubscription();

        $invoiceId =
            '7032633463';

        $preapprovalId =
            '9f8d4e3a9b0b4efd93ea1e669ab5e000';

        Http::fake([
            'https://api.mercadopago.com/authorized_payments/'
                .$invoiceId => Http::response(
                    [
                        'id' =>
                            7032633463,

                        'preapproval_id' =>
                            $preapprovalId,

                        'external_reference' =>
                            'negozia-subscription-'
                            .$subscription->id,

                        'transaction_amount' =>
                            29.90,

                        'debit_date' =>
                            '2026-10-05T18:30:42.000-04:00',

                        'status' =>
                            'processed',

                        'payment' => [
                            'id' =>
                                182584944406,

                            'status' =>
                                'approved',

                            'status_detail' =>
                                'accredited',
                        ],
                    ],
                    200
                ),

            'https://api.mercadopago.com/preapproval/'
                .$preapprovalId => Http::response(
                    [
                        'id' =>
                            $preapprovalId,

                        'payer_id' =>
                            123456789,

                        'external_reference' =>
                            'negozia-subscription-'
                            .$subscription->id,

                        'status' =>
                            'authorized',

                        'next_payment_date' =>
                            '2026-11-05T18:30:42.000-04:00',
                    ],
                    200
                ),
        ]);

        $requestId =
            'request-subscription-payment';

        $timestamp =
            '1791230000';

        $manifest =
            'id:'
            .$invoiceId
            .';request-id:'
            .$requestId
            .';ts:'
            .$timestamp
            .';';

        $signature =
            'ts='
            .$timestamp
            .',v1='
            .hash_hmac(
                'sha256',
                $manifest,
                'TEST_WEBHOOK_SECRET'
            );

        $this
            ->postJson(
                route('webhooks.mercadopago')
                    .'?data.id='
                    .$invoiceId,
                [
                    'action' =>
                        'created',

                    'type' =>
                        'subscription_authorized_payment',

                    'data' => [
                        'id' =>
                            $invoiceId,
                    ],
                ],
                [
                    'X-Signature' =>
                        $signature,

                    'X-Request-Id' =>
                        $requestId,
                ]
            )
            ->assertOk()
            ->assertJson([
                'received' => true,
            ]);

        $subscription->refresh();

        $this->assertSame(
            'pro',
            $subscription->plan->slug
        );

        $this->assertSame(
            'mercadopago_subscription',
            $subscription->payment_provider
        );

        $this->assertSame(
            'current',
            $subscription->billing_status
        );

        $this->assertSame(
            '182584944406',
            $subscription->provider_payment_id
        );

        $this->assertSame(
            'approved/accredited',
            $subscription->provider_payment_status
        );

        $this->assertNull(
            $subscription->ends_at
        );

        $this->assertNotNull(
            $subscription->last_payment_confirmed_at
        );
    }

    public function test_cancelled_preapproval_keeps_pro_until_paid_period_end(): void
    {
        $subscription =
            $this->createSubscription();

        $pro =
            Plan::query()
                ->where(
                    'slug',
                    'pro'
                )
                ->firstOrFail();

        $periodEnd =
            now()
                ->addDays(20)
                ->startOfSecond();

        $subscription->update([
            'plan_id' =>
                $pro->id,

            'status' =>
                'active',

            'billing_status' =>
                'current',

            'current_period_ends_at' =>
                $periodEnd,
        ]);

        $preapprovalId =
            '9f8d4e3a9b0b4efd93ea1e669ab5e000';

        Http::fake([
            'https://api.mercadopago.com/preapproval/'
                .$preapprovalId =>
                Http::response(
                    [
                        'id' =>
                            $preapprovalId,

                        'payer_id' =>
                            123456789,

                        'external_reference' =>
                            'negozia-subscription-'
                            .$subscription->id,

                        'status' =>
                            'cancelled',
                    ],
                    200
                ),
        ]);

        $requestId =
            'request-preapproval-cancelled';

        $timestamp =
            '1791230000';

        $manifest =
            'id:'
            .$preapprovalId
            .';request-id:'
            .$requestId
            .';ts:'
            .$timestamp
            .';';

        $signature =
            'ts='
            .$timestamp
            .',v1='
            .hash_hmac(
                'sha256',
                $manifest,
                'TEST_WEBHOOK_SECRET'
            );

        $this
            ->postJson(
                route(
                    'webhooks.mercadopago'
                )
                .'?data.id='
                .$preapprovalId
                .'&type=subscription_preapproval',
                [
                    'action' =>
                        'updated',

                    'type' =>
                        'subscription_preapproval',

                    'data' => [
                        'id' =>
                            $preapprovalId,
                    ],
                ],
                [
                    'X-Signature' =>
                        $signature,

                    'X-Request-Id' =>
                        $requestId,
                ]
            )
            ->assertOk()
            ->assertJson([
                'received' => true,
            ]);

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
            'canceling',
            $subscription->billing_status
        );

        $this->assertSame(
            'cancelled',
            $subscription->provider_checkout_status
        );

        $this->assertTrue(
            $subscription
                ->ends_at
                ->isSameSecond(
                    $periodEnd
                )
        );

        Http::assertSentCount(1);

        Http::assertSent(
            fn ($request) =>
                $request->method() === 'GET'
                && $request->url()
                    === 'https://api.mercadopago.com/preapproval/'
                    .$preapprovalId
        );
    }


    private function createSubscription(): Subscription
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

        return Subscription::create([
            'business_id' =>
                $business->id,

            'plan_id' =>
                $free->id,

            'status' =>
                'active',

            'billing_status' =>
                'pending',

            'payment_provider' =>
                'mercadopago_subscription',

            'provider_subscription_id' =>
                '9f8d4e3a9b0b4efd93ea1e669ab5e000',

            'provider_checkout_status' =>
                'authorized',

            'starts_at' =>
                now(),

            'current_period_starts_at' =>
                now()->startOfMonth(),

            'current_period_ends_at' =>
                now()->endOfMonth(),
        ]);
    }
}
