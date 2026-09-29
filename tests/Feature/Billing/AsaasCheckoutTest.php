<?php

namespace Tests\Feature\Billing;

use App\Enums\PlanFeature;
use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AsaasCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function createFreeAccount(): array
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $business = Business::factory()->create([
            'user_id' => $user->id,
            'name' => 'Empresa Teste',
            'document' => '24971563792',
            'email' => 'financeiro@example.com',
            'whatsapp' => '(33) 99999-9999',
            'address' => 'Rua Teste',
            'address_number' => '123',
            'address_complement' => 'Sala 2',
            'province' => 'Centro',
            'postal_code' => '39900-000',
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

        Plan::create([
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
        ]);

        return [
            $user,
            $business,
            $subscription,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.asaas.environment' =>
                'sandbox',

            'services.asaas.base_url' =>
                'https://api-sandbox.asaas.com/v3',

            'services.asaas.api_key' =>
                'sandbox-test-key',

            'services.asaas.checkout_url' =>
                'https://sandbox.asaas.com/checkoutSession/show/',
        ]);
    }

    public function test_free_user_can_start_asaas_pro_checkout(): void
    {
        [
            $user,
            $business,
            $subscription,
        ] = $this->createFreeAccount();

        Http::fake(
            function (Request $request) {
                if (
                    $request->method() === 'GET'
                    && str_starts_with(
                        $request->url(),
                        'https://api-sandbox.asaas.com/v3/customers'
                    )
                ) {
                    return Http::response([
                        'data' => [],
                    ]);
                }

                if (
                    $request->method() === 'POST'
                    && $request->url()
                    === 'https://api-sandbox.asaas.com/v3/customers'
                ) {
                    return Http::response([
                        'id' =>
                            'cus_test_123',
                    ]);
                }

                if (
                    $request->method() === 'POST'
                    && $request->url()
                    === 'https://api-sandbox.asaas.com/v3/checkouts'
                ) {
                    return Http::response([
                        'id' =>
                            'checkout_test_123',

                        'link' =>
                            'https://sandbox.asaas.com/checkoutSession/show/checkout_test_123',

                        'status' =>
                            'ACTIVE',
                    ]);
                }

                return Http::response(
                    [],
                    404
                );
            }
        );

        $response = $this
            ->actingAs($user)
            ->post(
                route(
                    'settings.subscription.checkout.asaas'
                )
            );

        $response->assertRedirect(
            'https://sandbox.asaas.com/checkoutSession/show/checkout_test_123'
        );

        $subscription->refresh();

        $this->assertSame(
            'asaas',
            $subscription->payment_provider
        );

        $this->assertSame(
            'cus_test_123',
            $subscription->provider_customer_id
        );

        $this->assertSame(
            'checkout_test_123',
            $subscription->provider_checkout_id
        );

        $this->assertSame(
            'ACTIVE',
            $subscription->provider_checkout_status
        );

        /*
         * A criação do checkout ainda não libera o Pro.
         */
        $this->assertSame(
            'free',
            $subscription->plan->slug
        );

        Http::assertSent(
            function (Request $request) use (
                $business
            ): bool {
                if (
                    $request->method() !== 'POST'
                    || $request->url()
                    !== 'https://api-sandbox.asaas.com/v3/customers'
                ) {
                    return false;
                }

                $data = $request->data();

                return
                    data_get(
                        $data,
                        'name'
                    ) === $business->name

                    && data_get(
                        $data,
                        'cpfCnpj'
                    ) === '24971563792'

                    && data_get(
                        $data,
                        'externalReference'
                    ) ===
                        'negozia-business-'
                        . $business->id

                    && data_get(
                        $data,
                        'address'
                    ) === 'Rua Teste'

                    && data_get(
                        $data,
                        'addressNumber'
                    ) === '123'

                    && data_get(
                        $data,
                        'complement'
                    ) === 'Sala 2'

                    && data_get(
                        $data,
                        'province'
                    ) === 'Centro'

                    && data_get(
                        $data,
                        'postalCode'
                    ) === '39900000';
            }
        );

        Http::assertSent(
            function (Request $request) use (
                $subscription
            ): bool {
                if (
                    $request->method() !== 'POST'
                    || $request->url()
                    !== 'https://api-sandbox.asaas.com/v3/checkouts'
                ) {
                    return false;
                }

                $data = $request->data();

                return
                    $request->hasHeader(
                        'access_token',
                        'sandbox-test-key'
                    )

                    && data_get(
                        $data,
                        'customer'
                    ) === 'cus_test_123'

                    && !array_key_exists(
                        'customerData',
                        $data
                    )

                    && data_get(
                        $data,
                        'billingTypes.0'
                    ) === 'CREDIT_CARD'

                    && data_get(
                        $data,
                        'chargeTypes.0'
                    ) === 'RECURRENT'

                    && data_get(
                        $data,
                        'items.0.value'
                    ) === 29.9

                    && data_get(
                        $data,
                        'subscription.cycle'
                    ) === 'MONTHLY'

                    && data_get(
                        $data,
                        'externalReference'
                    ) ===
                        'negozia-subscription-'
                        . $subscription->id;
            }
        );
    }

    public function test_existing_asaas_customer_is_reused(): void
    {
        [
            $user,
            ,
            $subscription,
        ] = $this->createFreeAccount();

        $subscription->update([
            'payment_provider' =>
                'asaas',

            'provider_customer_id' =>
                'cus_existing_123',
        ]);

        Http::fake([
            'https://api-sandbox.asaas.com/v3/customers/cus_existing_123' =>
                Http::response([
                    'id' =>
                        'cus_existing_123',

                    'address' =>
                        'Rua Teste',

                    'addressNumber' =>
                        '123',

                    'complement' =>
                        'Sala 2',

                    'province' =>
                        'Centro',

                    'postalCode' =>
                        '39900000',
                ]),

            'https://api-sandbox.asaas.com/v3/checkouts' =>
                Http::response([
                    'id' =>
                        'checkout_existing_customer',

                    'link' =>
                        'https://sandbox.asaas.com/checkoutSession/show/checkout_existing_customer',

                    'status' =>
                        'ACTIVE',
                ]),
        ]);

        $this
            ->actingAs($user)
            ->post(
                route(
                    'settings.subscription.checkout.asaas'
                )
            )
            ->assertRedirect(
                'https://sandbox.asaas.com/checkoutSession/show/checkout_existing_customer'
            );

        Http::assertSentCount(2);

        Http::assertSent(
            function (Request $request): bool {
                if (
                    $request->method() !== 'PUT'
                    || $request->url()
                    !== 'https://api-sandbox.asaas.com/v3/customers/cus_existing_123'
                ) {
                    return false;
                }

                return
                    data_get(
                        $request->data(),
                        'address'
                    ) === 'Rua Teste'

                    && data_get(
                        $request->data(),
                        'addressNumber'
                    ) === '123'

                    && data_get(
                        $request->data(),
                        'complement'
                    ) === 'Sala 2'

                    && data_get(
                        $request->data(),
                        'province'
                    ) === 'Centro'

                    && data_get(
                        $request->data(),
                        'postalCode'
                    ) === '39900000';
            }
        );

        Http::assertSent(
            fn(Request $request): bool =>
                $request->method() === 'POST'

                && $request->url()
                === 'https://api-sandbox.asaas.com/v3/checkouts'

                && data_get(
                    $request->data(),
                    'customer'
                ) === 'cus_existing_123'
        );
    }

    public function test_checkout_requires_address_number_before_calling_asaas(): void
    {
        [
            $user,
            $business,
            ,
        ] = $this->createFreeAccount();

        $business->update([
            'address_number' => null,
        ]);

        Http::fake();

        $this
            ->actingAs($user)
            ->from(
                route('settings.subscription')
            )
            ->post(
                route(
                    'settings.subscription.checkout.asaas'
                )
            )
            ->assertRedirect(
                route('settings.subscription')
            )
            ->assertSessionHas(
                'billing_error',
                'Informe o número do endereço em Configurações > Empresa antes de assinar o Negozia Pro.'
            );

        Http::assertNothingSent();
    }

    public function test_active_pro_user_does_not_create_another_checkout(): void
    {
        [
            $user,
            ,
            $subscription,
        ] = $this->createFreeAccount();

        $pro = Plan::query()
            ->where('slug', 'pro')
            ->firstOrFail();

        $subscription->update([
            'plan_id' => $pro->id,
        ]);

        Http::fake();

        $this
            ->actingAs($user)
            ->from(route('settings.subscription'))
            ->post(
                route(
                    'settings.subscription.checkout.asaas'
                )
            )
            ->assertRedirect(
                route('settings.subscription')
            )
            ->assertSessionHas(
                'billing_info',
                'O Negozia Pro já está ativo nesta conta.'
            );

        Http::assertNothingSent();
    }

    public function test_subscription_page_shows_billing_address_modal(): void
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
                'Complete os dados para pagamento'
            )
            ->assertSee(
                'Continuar para pagamento'
            )
            ->assertSee(
                'name="billing_postal_code"',
                false
            )
            ->assertSee(
                'name="billing_address"',
                false
            )
            ->assertSee(
                'name="billing_address_number"',
                false
            )
            ->assertSee(
                'name="billing_province"',
                false
            );
    }

    public function test_checkout_modal_saves_billing_address_before_calling_asaas(): void
    {
        [
            $user,
            $business,
        ] = $this->createFreeAccount();

        $business->update([
            'address' =>
                null,

            'address_number' =>
                null,

            'address_complement' =>
                null,

            'province' =>
                null,

            'postal_code' =>
                null,
        ]);

        Http::fake(
            function (Request $request) {
                if (
                    $request->method() === 'GET'
                    && str_starts_with(
                        $request->url(),
                        'https://api-sandbox.asaas.com/v3/customers'
                    )
                ) {
                    return Http::response([
                        'data' => [],
                    ]);
                }

                if (
                    $request->method() === 'POST'
                    && $request->url()
                    === 'https://api-sandbox.asaas.com/v3/customers'
                ) {
                    return Http::response([
                        'id' =>
                            'cus_address_modal',
                    ]);
                }

                if (
                    $request->method() === 'POST'
                    && $request->url()
                    === 'https://api-sandbox.asaas.com/v3/checkouts'
                ) {
                    return Http::response([
                        'id' =>
                            'checkout_address_modal',

                        'link' =>
                            'https://sandbox.asaas.com/checkoutSession/show/checkout_address_modal',

                        'status' =>
                            'ACTIVE',
                    ]);
                }

                return Http::response(
                    [],
                    404
                );
            }
        );

        $this
            ->actingAs($user)
            ->post(
                route(
                    'settings.subscription.checkout.asaas'
                ),
                [
                    'billing_address_submit' =>
                        '1',

                    'billing_postal_code' =>
                        '39900-000',

                    'billing_address' =>
                        'Avenida Teste',

                    'billing_address_number' =>
                        '456',

                    'billing_address_complement' =>
                        'Sala 9',

                    'billing_province' =>
                        'Centro',
                ]
            )
            ->assertRedirect(
                'https://sandbox.asaas.com/checkoutSession/show/checkout_address_modal'
            );

        $business->refresh();

        $this->assertSame(
            '39900000',
            $business->postal_code
        );

        $this->assertSame(
            'Avenida Teste',
            $business->address
        );

        $this->assertSame(
            '456',
            $business->address_number
        );

        $this->assertSame(
            'Sala 9',
            $business->address_complement
        );

        $this->assertSame(
            'Centro',
            $business->province
        );

        Http::assertSent(
            function (Request $request): bool {
                if (
                    $request->method() !== 'POST'
                    || $request->url()
                    !== 'https://api-sandbox.asaas.com/v3/customers'
                ) {
                    return false;
                }

                return
                    data_get(
                        $request->data(),
                        'postalCode'
                    ) === '39900000'

                    && data_get(
                        $request->data(),
                        'address'
                    ) === 'Avenida Teste'

                    && data_get(
                        $request->data(),
                        'addressNumber'
                    ) === '456'

                    && data_get(
                        $request->data(),
                        'complement'
                    ) === 'Sala 9'

                    && data_get(
                        $request->data(),
                        'province'
                    ) === 'Centro';
            }
        );
    }

    public function test_checkout_modal_rejects_incomplete_billing_address(): void
    {
        [
            $user,
        ] = $this->createFreeAccount();

        Http::fake();

        $this
            ->actingAs($user)
            ->from(
                route(
                    'settings.subscription'
                )
            )
            ->post(
                route(
                    'settings.subscription.checkout.asaas'
                ),
                [
                    'billing_address_submit' =>
                        '1',

                    'billing_postal_code' =>
                        '39900-000',

                    'billing_address' =>
                        'Rua Teste',

                    'billing_address_number' =>
                        '',

                    'billing_address_complement' =>
                        '',

                    'billing_province' =>
                        'Centro',
                ]
            )
            ->assertRedirect(
                route(
                    'settings.subscription'
                )
            )
            ->assertSessionHasErrors([
                'billing_address_number',
            ]);

        Http::assertNothingSent();
    }

}
