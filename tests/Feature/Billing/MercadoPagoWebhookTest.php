<?php

namespace Tests\Feature\Billing;

use App\Models\Business;
use App\Models\GatewayWebhookEvent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MercadoPagoWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.mercadopago.base_url' => 'https://api.mercadopago.com',

            'services.mercadopago.access_token' => 'TEST_ACCESS_TOKEN',

            'services.mercadopago.webhook_secret' => 'TEST_WEBHOOK_SECRET',
        ]);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $this
            ->postJson(
                route('webhooks.mercadopago')
                    .'?data.id=ORD_TEST_123&type=order',
                [
                    'id' => 'event_test_123',
                    'type' => 'order',
                    'action' => 'order.processed',
                    'data' => [
                        'id' => 'ORD_TEST_123',
                    ],
                ],
                [
                    'X-Signature' => 'ts=1234567890,v1=invalid',

                    'X-Request-Id' => 'request-test-123',
                ]
            )
            ->assertStatus(401);
    }

    public function test_processed_order_activates_pro(): void
    {
        $subscription =
            $this->createFreeSubscription();

        $order =
            $this->paidOrder(
                $subscription
            );

        Http::fake([
            'https://api.mercadopago.com/v1/orders/ORD_TEST_123' => Http::response(
                $order,
                200
            ),
        ]);

        $requestId =
            'request-test-456';

        $timestamp =
            '1760000000000';

        $signature =
            $this->signature(
                'ORD_TEST_123',
                $requestId,
                $timestamp
            );

        $response =
            $this->postJson(
                route('webhooks.mercadopago')
                    .'?data.id=ORD_TEST_123&type=order',
                [
                    'id' => 'event_test_456',

                    'type' => 'order',

                    'action' => 'order.processed',

                    'data' => [
                        'id' => 'ORD_TEST_123',
                    ],
                ],
                [
                    'X-Signature' => $signature,

                    'X-Request-Id' => $requestId,
                ]
            );

        $response
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
            'mercadopago_pix',
            $subscription->payment_provider
        );

        $this->assertSame(
            'current',
            $subscription->billing_status
        );

        $this->assertSame(
            'processed/accredited',
            $subscription->provider_payment_status
        );

        $event =
            GatewayWebhookEvent::query()
                ->where(
                    'provider',
                    'mercadopago'
                )
                ->where(
                    'provider_event_id',
                    'event_test_456'
                )
                ->first();

        $this->assertNotNull(
            $event
        );

        $this->assertNotNull(
            $event->processed_at
        );

        Http::assertSent(
            fn ($request) => $request->method() === 'GET'
                && $request->url()
                    === 'https://api.mercadopago.com/v1/orders/ORD_TEST_123'
        );
    }

    public function test_php_normalized_data_id_signature_is_valid(): void
    {
        $subscription =
            $this->createFreeSubscription();

        Http::fake([
            'https://api.mercadopago.com/v1/orders/ORD_TEST_123' => Http::response(
                $this->paidOrder(
                    $subscription
                ),
                200
            ),
        ]);

        $requestId =
            'request-php-normalized';

        $timestamp =
            '1760000000000';

        $signature =
            $this->signature(
                'ORD_TEST_123',
                $requestId,
                $timestamp
            );

        $this
            ->postJson(
                route(
                    'webhooks.mercadopago'
                )
                .'?data_id=ORD_TEST_123&type=order',
                [
                    'id' => 'event_php_normalized',

                    'action' => 'order.processed',

                    'type' => 'order',

                    'data' => [
                        'id' => 'ORD_TEST_123',
                    ],
                ],
                [
                    'X-Signature' => $signature,

                    'X-Request-Id' => $requestId,
                ]
            )
            ->assertOk()
            ->assertJson([
                'received' => true,
            ]);
    }

    public function test_uppercase_order_id_is_preserved_in_signature_manifest(): void
    {
        $subscription =
            $this->createFreeSubscription();

        Http::fake([
            'https://api.mercadopago.com/v1/orders/ORDTST01ABCXYZ' => Http::response(
                [
                    'id' => 'ORDTST01ABCXYZ',

                    'status' => 'processed',

                    'status_detail' => 'accredited',

                    'external_reference' => 'negozia-pix-subscription-'
                        .$subscription->id,

                    'total_amount' => '29.90',

                    'transactions' => [
                        'payments' => [
                            [
                                'id' => 'PAY_TEST_UPPER',

                                'status' => 'processed',

                                'status_detail' => 'accredited',

                                'payment_method' => [
                                    'id' => 'pix',

                                    'type' => 'bank_transfer',
                                ],
                            ],
                        ],
                    ],
                ],
                200
            ),
        ]);

        $requestId =
            'request-uppercase-test';

        $timestamp =
            '1791068501';

        $manifest =
            'id:ORDTST01ABCXYZ;'
            .'request-id:'
            .$requestId
            .';ts:'
            .$timestamp
            .';';

        $hash =
            hash_hmac(
                'sha256',
                $manifest,
                'TEST_WEBHOOK_SECRET'
            );

        $signature =
            'ts='
            .$timestamp
            .',v1='
            .$hash;

        $this
            ->postJson(
                route(
                    'webhooks.mercadopago'
                )
                .'?data.id=ORDTST01ABCXYZ&type=order',
                [
                    'id' => 'event-uppercase-test',

                    'action' => 'order.processed',

                    'type' => 'order',

                    'data' => [
                        'id' => 'ORDTST01ABCXYZ',
                    ],
                ],
                [
                    'X-Signature' => $signature,

                    'X-Request-Id' => $requestId,
                ]
            )
            ->assertOk()
            ->assertJson([
                'received' => true,
            ]);
    }

    public function test_signature_without_query_data_id_is_valid(): void
    {
        $subscription =
            $this->createFreeSubscription();

        Http::fake([
            'https://api.mercadopago.com/v1/orders/ORD_TEST_123' => Http::response(
                $this->paidOrder(
                    $subscription
                ),
                200
            ),
        ]);

        $requestId =
            'request-simulator-123';

        $timestamp =
            '1760000000000';

        /*
         * Simula o comportamento em que data.id vem
         * no JSON, mas não aparece na query string.
         */
        $manifest =
            'request-id:'
            .$requestId
            .';ts:'
            .$timestamp
            .';';

        $hash =
            hash_hmac(
                'sha256',
                $manifest,
                'TEST_WEBHOOK_SECRET'
            );

        $signature =
            'ts='
            .$timestamp
            .',v1='
            .$hash;

        $this
            ->postJson(
                route(
                    'webhooks.mercadopago'
                ),
                [
                    'action' => 'order.processed',

                    'api_version' => 'v1',

                    'data' => [
                        'id' => 'ORD_TEST_123',

                        'status' => 'processed',

                        'status_detail' => 'accredited',
                    ],

                    'type' => 'order',
                ],
                [
                    'X-Signature' => $signature,

                    'X-Request-Id' => $requestId,
                ]
            )
            ->assertOk()
            ->assertJson([
                'received' => true,
            ]);
    }

    private function signature(
        string $dataId,
        string $requestId,
        string $timestamp
    ): string {
        $manifest =
            'id:'
            .$dataId
            .';request-id:'
            .$requestId
            .';ts:'
            .$timestamp
            .';';

        $hash =
            hash_hmac(
                'sha256',
                $manifest,
                'TEST_WEBHOOK_SECRET'
            );

        return
            'ts='
            .$timestamp
            .',v1='
            .$hash;
    }

    private function createFreeSubscription(): Subscription
    {
        $user =
            User::factory()->create();

        $business =
            Business::factory()->create([
                'user_id' => $user->id,
            ]);

        $free =
            Plan::create([
                'name' => 'Grátis',

                'slug' => 'free',

                'description' => 'Plano gratuito',

                'price' => 0,

                'billing_interval' => 'month',

                'quote_limit' => 5,

                'features' => [],

                'is_active' => true,

                'sort_order' => 10,
            ]);

        Plan::create([
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

        return Subscription::create([
            'business_id' => $business->id,

            'plan_id' => $free->id,

            'status' => 'active',

            'starts_at' => now(),

            'current_period_starts_at' => now()->startOfMonth(),

            'current_period_ends_at' => now()->endOfMonth(),
        ]);
    }

    private function paidOrder(
        Subscription $subscription
    ): array {
        return [
            'id' => 'ORD_TEST_123',

            'status' => 'processed',

            'status_detail' => 'accredited',

            'external_reference' => 'negozia-pix-subscription-'
                .$subscription->id,

            'total_amount' => '29.90',

            'transactions' => [
                'payments' => [
                    [
                        'id' => 'PAY_TEST_123',

                        'status' => 'processed',

                        'status_detail' => 'accredited',

                        'payment_method' => [
                            'id' => 'pix',

                            'type' => 'bank_transfer',
                        ],
                    ],
                ],
            ],
        ];
    }
}
