<?php

namespace Tests\Feature\Billing;

use App\Enums\PlanFeature;
use App\Models\Business;
use App\Models\GatewayWebhookEvent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AsaasWebhookTest extends TestCase
{
    use RefreshDatabase;

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
            'description' => 'Plano gratuito',
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
            'description' => 'Plano profissional',
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
            'plan_id' => $free->id,
            'status' => 'active',
            'starts_at' => now(),
            'current_period_starts_at' =>
                now()->startOfMonth(),
            'current_period_ends_at' =>
                now()->endOfMonth(),
            'payment_provider' => 'asaas',
            'provider_checkout_id' =>
                'checkout_test_123',
            'provider_checkout_status' =>
                'ACTIVE',
        ]);

        return [
            $user,
            $business,
            $subscription,
            $free,
            $pro,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.asaas.webhook_token' =>
                'webhook-test-token-with-at-least-32-characters',
        ]);
    }

    public function test_checkout_paid_activates_pro_plan(): void
    {
        [




            ,
        ,
            $subscription,
        ,
            $pro,
        ] = $this->createAccount();

        $response = $this->postJson(
            route('webhooks.asaas'),
            [
                'id' => 'evt_checkout_paid_1',
                'event' => 'CHECKOUT_PAID',
                'checkout' => [
                    'id' => 'checkout_test_123',
                    'status' => 'PAID',
                    'customer' => 'cus_test_123',
                ],
            ],
            [
                'asaas-access-token' =>
                    'webhook-test-token-with-at-least-32-characters',
            ]
        );

        $response
            ->assertOk()
            ->assertJson([
                'received' => true,
            ]);

        $subscription->refresh();

        $this->assertSame(
            $pro->id,
            $subscription->plan_id
        );

        $this->assertSame(
            'active',
            $subscription->status
        );

        $this->assertSame(
            'PAID',
            $subscription
                ->provider_checkout_status
        );

        $this->assertSame(
            'cus_test_123',
            $subscription
                ->provider_customer_id
        );

        $this->assertNotNull(
            $subscription
                ->current_period_starts_at
        );

        $this->assertNotNull(
            $subscription
                ->current_period_ends_at
        );
    }

    public function test_duplicate_event_is_idempotent(): void
    {
        $this->createAccount();

        $payload = [
            'id' => 'evt_checkout_paid_duplicate',
            'event' => 'CHECKOUT_PAID',
            'checkout' => [
                'id' => 'checkout_test_123',
                'status' => 'PAID',
                'customer' => 'cus_test_123',
            ],
        ];

        $headers = [
            'asaas-access-token' =>
                'webhook-test-token-with-at-least-32-characters',
        ];

        $this
            ->postJson(
                route('webhooks.asaas'),
                $payload,
                $headers
            )
            ->assertOk();

        $this
            ->postJson(
                route('webhooks.asaas'),
                $payload,
                $headers
            )
            ->assertOk()
            ->assertJson([
                'received' => true,
                'duplicate' => true,
            ]);

        $this->assertSame(
            1,
            GatewayWebhookEvent::query()
                ->where(
                    'provider_event_id',
                    'evt_checkout_paid_duplicate'
                )
                ->count()
        );
    }

    public function test_invalid_webhook_token_is_rejected(): void
    {
        $this->createAccount();

        $this
            ->postJson(
                route('webhooks.asaas'),
                [
                    'id' => 'evt_invalid_token',
                    'event' => 'CHECKOUT_PAID',
                    'checkout' => [
                        'id' =>
                            'checkout_test_123',
                        'status' => 'PAID',
                    ],
                ],
                [
                    'asaas-access-token' =>
                        'wrong-token',
                ]
            )
            ->assertUnauthorized();

        $this->assertDatabaseMissing(
            'gateway_webhook_events',
            [
                'provider_event_id' =>
                    'evt_invalid_token',
            ]
        );
    }

    public function test_canceled_checkout_does_not_activate_pro(): void
    {
        [



            ,
        ,
            $subscription,
            $free,
        ] = $this->createAccount();

        $this
            ->postJson(
                route('webhooks.asaas'),
                [
                    'id' =>
                        'evt_checkout_canceled_1',
                    'event' =>
                        'CHECKOUT_CANCELED',
                    'checkout' => [
                        'id' =>
                            'checkout_test_123',
                        'status' =>
                            'CANCELED',
                    ],
                ],
                [
                    'asaas-access-token' =>
                        'webhook-test-token-with-at-least-32-characters',
                ]
            )
            ->assertOk();

        $subscription->refresh();

        $this->assertSame(
            $free->id,
            $subscription->plan_id
        );

        $this->assertSame(
            'CANCELED',
            $subscription
                ->provider_checkout_status
        );
    }

    public function test_subscription_created_saves_provider_ids(): void
    {
        [


            ,
        ,
            $subscription,
        ] = $this->createAccount();

        /*
         * Primeiro simulamos o CHECKOUT_PAID,
         * que salva o customer no registro local.
         */
        $this
            ->postJson(
                route('webhooks.asaas'),
                [
                    'id' =>
                        'evt_checkout_paid_for_sub',
                    'event' =>
                        'CHECKOUT_PAID',
                    'checkout' => [
                        'id' =>
                            'checkout_test_123',
                        'status' => 'PAID',
                        'customer' =>
                            'cus_test_123',
                    ],
                ],
                [
                    'asaas-access-token' =>
                        'webhook-test-token-with-at-least-32-characters',
                ]
            )
            ->assertOk();

        $this
            ->postJson(
                route('webhooks.asaas'),
                [
                    'id' =>
                        'evt_subscription_created_1',
                    'event' =>
                        'SUBSCRIPTION_CREATED',
                    'subscription' => [
                        'id' =>
                            'sub_test_123',
                        'customer' =>
                            'cus_test_123',
                        'status' => 'ACTIVE',
                        'cycle' => 'MONTHLY',
                        'nextDueDate' =>
                            now()
                                ->addMonth()
                                ->toDateString(),
                    ],
                ],
                [
                    'asaas-access-token' =>
                        'webhook-test-token-with-at-least-32-characters',
                ]
            )
            ->assertOk();

        $subscription->refresh();

        $this->assertSame(
            'sub_test_123',
            $subscription
                ->provider_subscription_id
        );

        $this->assertSame(
            'cus_test_123',
            $subscription
                ->provider_customer_id
        );
    }


    /*
    |--------------------------------------------------------------------------
    | TESTE PARA ADICIONAR AO AsaasWebhookTest EXISTENTE
    |--------------------------------------------------------------------------
    |
    | Este arquivo é apenas um snippet de referência.
    | Adicione o método abaixo dentro da classe AsaasWebhookTest.
    |
    */

    public function test_subscription_created_can_be_linked_before_checkout_paid_by_customer_id(): void
    {
        [


            ,
        ,
            $subscription,
        ] = $this->createAccount();

        /*
         * Na nova arquitetura o customer_id já é salvo
         * antes mesmo de criar o checkout.
         */
        $subscription->update([
            'payment_provider' =>
                'asaas',

            'provider_customer_id' =>
                'cus_test_early_123',
        ]);

        $this
            ->postJson(
                route('webhooks.asaas'),
                [
                    'id' =>
                        'evt_subscription_created_before_paid',

                    'event' =>
                        'SUBSCRIPTION_CREATED',

                    'subscription' => [
                        'id' =>
                            'sub_test_early_123',

                        'customer' =>
                            'cus_test_early_123',

                        'status' =>
                            'ACTIVE',

                        'cycle' =>
                            'MONTHLY',

                        'nextDueDate' =>
                            now()
                                ->addMonth()
                                ->toDateString(),

                        'externalReference' =>
                            null,
                    ],
                ],
                [
                    'asaas-access-token' =>
                        'webhook-test-token-with-at-least-32-characters',
                ]
            )
            ->assertOk();

        $subscription->refresh();

        $this->assertSame(
            'sub_test_early_123',
            $subscription
                ->provider_subscription_id
        );

        $this->assertSame(
            'cus_test_early_123',
            $subscription
                ->provider_customer_id
        );
    }

}
