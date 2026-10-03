<?php

namespace Tests\Feature\Billing;

use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\MercadoPagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MercadoPagoPixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.mercadopago.environment' => 'test',

            'services.mercadopago.base_url' => 'https://api.mercadopago.com',

            'services.mercadopago.access_token' => 'TEST_ACCESS_TOKEN',
        ]);
    }

    public function test_free_user_can_create_pix_order(): void
    {
        [
            $user,
            ,
            $subscription,
        ] = $this->createFreeAccount();

        Http::fake([
            'https://api.mercadopago.com/v1/orders' => Http::response(
                $this->pendingOrder(
                    $subscription
                ),
                201
            ),
        ]);

        $this
            ->actingAs($user)
            ->post(
                route(
                    'settings.subscription.checkout.pix'
                )
            )
            ->assertRedirect(
                route(
                    'settings.subscription.pix'
                )
            );

        $subscription->refresh();

        $this->assertSame(
            'mercadopago_pix',
            $subscription
                ->payment_provider
        );

        $this->assertSame(
            'ORD_TEST_123',
            $subscription
                ->provider_checkout_id
        );

        $this->assertSame(
            'PAY_TEST_123',
            $subscription
                ->provider_payment_id
        );

        $this->assertSame(
            'free',
            $subscription
                ->plan
                ->slug
        );

        Http::assertSent(
            function ($request) use (
                $subscription
            ): bool {
                $data =
                    $request->data();

                return
                    $request->method()
                        === 'POST'

                    && data_get(
                        $data,
                        'external_reference'
                    ) ===
                        'negozia-pix-subscription-'
                        .$subscription->id

                    && data_get(
                        $data,
                        'total_amount'
                    ) === '29.90'

                    && data_get(
                        $data,
                        'transactions.payments.0.payment_method.id'
                    ) === 'pix'

                    && $request->hasHeader(
                        'X-Idempotency-Key'
                    );
            }
        );
    }

    public function test_accredited_pix_activates_pro_for_thirty_days(): void
    {
        [
            ,
            ,
            $subscription,
        ] = $this->createFreeAccount();

        $subscription->update([
            'payment_provider' => 'mercadopago_pix',

            'provider_checkout_id' => 'ORD_TEST_123',

            'provider_payment_id' => 'PAY_TEST_123',

            'provider_payment_status' => 'action_required',
        ]);

        $service =
            app(
                MercadoPagoService::class
            );

        $paid =
            $service->syncPixOrder(
                $subscription,
                $this->paidOrder(
                    $subscription
                )
            );

        $this->assertTrue(
            $paid
        );

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
            'processed/accredited',
            $subscription
                ->provider_payment_status
        );

        $this->assertEquals(
            30,
            $subscription
                ->current_period_starts_at
                ->diffInDays(
                    $subscription
                        ->current_period_ends_at
                        ->copy()
                        ->addSecond()
                )
        );
    }

    public function test_subscription_page_offers_pix_payment(): void
    {
        [
            $user,
        ] = $this->createFreeAccount();

        $this
            ->actingAs($user)
            ->get(
                route(
                    'settings.subscription'
                )
            )
            ->assertOk()
            ->assertSee(
                'Pagar com Pix'
            )
            ->assertSee(
                'Assinar com cartão'
            );
    }

    public function test_pix_page_shows_copy_and_paste_code(): void
    {
        [
            $user,
            ,
            $subscription,
        ] = $this->createFreeAccount();

        $subscription->update([
            'payment_provider' => 'mercadopago_pix',

            'provider_checkout_id' => 'ORD_TEST_123',

            'provider_payment_id' => 'PAY_TEST_123',
        ]);

        Http::fake([
            'https://api.mercadopago.com/v1/orders/ORD_TEST_123' => Http::response(
                $this->pendingOrder(
                    $subscription
                )
            ),
        ]);

        $this
            ->actingAs($user)
            ->get(
                route(
                    'settings.subscription.pix'
                )
            )
            ->assertOk()
            ->assertSee(
                'Pagamento via Pix'
            )
            ->assertSee(
                'Pix Copia e Cola'
            )
            ->assertSee(
                'PIX-COPIA-E-COLA'
            )
            ->assertSee(
                'Já paguei, verificar pagamento'
            );
    }

    public function test_paid_pix_page_syncs_subscription_and_redirects(): void
    {
        [
            $user,
            ,
            $subscription,
        ] = $this->createFreeAccount();

        $subscription->update([
            'payment_provider' => 'mercadopago_pix',

            'provider_checkout_id' => 'ORD_TEST_123',

            'provider_payment_id' => 'PAY_TEST_123',

            'provider_payment_status' => 'action_required',
        ]);

        Http::fake([
            'https://api.mercadopago.com/v1/orders/ORD_TEST_123' => Http::response(
                $this->paidOrder(
                    $subscription
                ),
                200
            ),
        ]);

        $this
            ->actingAs($user)
            ->get(
                route(
                    'settings.subscription.pix'
                )
            )
            ->assertRedirect(
                route(
                    'settings.subscription'
                )
            )
            ->assertSessionHas(
                'billing_info',
                'Pix confirmado. O Negozia Pro está ativo por 30 dias.'
            );

        $subscription->refresh();

        $this->assertSame(
            'pro',
            $subscription
                ->plan
                ->slug
        );

        $this->assertSame(
            'current',
            $subscription
                ->billing_status
        );

        $this->assertSame(
            'processed/accredited',
            $subscription
                ->provider_payment_status
        );
    }

    private function createFreeAccount(): array
    {
        $user =
            User::factory()->create([
                'email_verified_at' => now(),
            ]);

        $business =
            Business::factory()->create([
                'user_id' => $user->id,

                'email' => 'financeiro@example.com',
            ]);

        $free = Plan::create([
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

        $subscription =
            Subscription::create([
                'business_id' => $business->id,

                'plan_id' => $free->id,

                'status' => 'active',

                'starts_at' => now(),

                'current_period_starts_at' => now()->startOfMonth(),

                'current_period_ends_at' => now()->endOfMonth(),
            ]);

        return [
            $user,
            $business,
            $subscription,
        ];
    }

    private function pendingOrder(
        Subscription $subscription
    ): array {
        return [
            'id' => 'ORD_TEST_123',

            'status' => 'action_required',

            'status_detail' => 'waiting_transfer',

            'external_reference' => 'negozia-pix-subscription-'
                .$subscription->id,

            'total_amount' => '29.90',

            'transactions' => [
                'payments' => [
                    [
                        'id' => 'PAY_TEST_123',

                        'status' => 'action_required',

                        'status_detail' => 'waiting_transfer',

                        'payment_method' => [
                            'id' => 'pix',

                            'type' => 'bank_transfer',

                            'qr_code' => 'PIX-COPIA-E-COLA',

                            'ticket_url' => 'https://example.com/pix',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function paidOrder(
        Subscription $subscription
    ): array {
        $order =
            $this->pendingOrder(
                $subscription
            );

        $order['status'] =
            'processed';

        $order['status_detail'] =
            'accredited';

        $order[
            'transactions'
        ][
            'payments'
        ][0][
            'status'
        ] = 'processed';

        $order[
            'transactions'
        ][
            'payments'
        ][0][
            'status_detail'
        ] = 'accredited';

        return $order;
    }
}
