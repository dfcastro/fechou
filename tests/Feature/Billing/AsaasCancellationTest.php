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

class AsaasCancellationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.asaas.base_url' =>
                'https://api-sandbox.asaas.com/v3',

            'services.asaas.api_key' =>
                'sandbox-test-key',
        ]);
    }

    private function createProAccount(): array
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $business = Business::factory()->create([
            'user_id' => $user->id,
        ]);

        $pro = Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price' => 29.90,
            'billing_interval' => 'month',
            'quote_limit' => null,
            'features' => [
                PlanFeature::CLIENT_MANAGEMENT->value,
                PlanFeature::FOLLOW_UP->value,
            ],
            'is_active' => true,
            'sort_order' => 20,
        ]);

        $periodEnd = now()
            ->addMonth()
            ->startOfSecond();

        $subscription = Subscription::create([
            'business_id' =>
                $business->id,

            'plan_id' =>
                $pro->id,

            'status' =>
                'active',

            'billing_status' =>
                'current',

            'starts_at' =>
                now(),

            'current_period_starts_at' =>
                now(),

            'current_period_ends_at' =>
                $periodEnd,

            'payment_provider' =>
                'asaas',

            'provider_customer_id' =>
                'cus_cancel_test',

            'provider_subscription_id' =>
                'sub_cancel_test',
        ]);

        return [
            $user,
            $business,
            $subscription,
            $periodEnd,
        ];
    }

    public function test_pro_user_can_cancel_asaas_subscription(): void
    {
        [
            $user,
            ,
            $subscription,
            $periodEnd,
        ] = $this->createProAccount();

        Http::fake([
            'https://api-sandbox.asaas.com/v3/subscriptions/sub_cancel_test' =>
                Http::response([
                    'deleted' => true,
                ]),
        ]);

        $this
            ->actingAs($user)
            ->from(
                route('settings.subscription')
            )
            ->delete(
                route(
                    'settings.subscription.cancel.asaas'
                )
            )
            ->assertRedirect(
                route('settings.subscription')
            )
            ->assertSessionHas(
                'billing_info'
            );

        $subscription->refresh();

        $this->assertSame(
            'active',
            $subscription->status
        );

        $this->assertSame(
            'canceling',
            $subscription->billing_status
        );

        $this->assertNotNull(
            $subscription->canceled_at
        );

        $this->assertTrue(
            $subscription
                ->ends_at
                ->equalTo(
                    $periodEnd
                )
        );

        $this->assertTrue(
            $subscription
                ->current_period_ends_at
                ->equalTo(
                    $periodEnd
                )
        );

        Http::assertSent(
            fn (Request $request): bool =>
                $request->method() === 'DELETE'
                && $request->url()
                    === 'https://api-sandbox.asaas.com/v3/subscriptions/sub_cancel_test'
                && $request->hasHeader(
                    'access_token',
                    'sandbox-test-key'
                )
        );
    }

    public function test_canceling_subscription_is_not_deleted_twice(): void
    {
        [
            $user,
            ,
            $subscription,
            $periodEnd,
        ] = $this->createProAccount();

        $subscription->update([
            'billing_status' =>
                'canceling',

            'canceled_at' =>
                now(),

            'ends_at' =>
                $periodEnd,
        ]);

        Http::fake();

        $this
            ->actingAs($user)
            ->from(
                route('settings.subscription')
            )
            ->delete(
                route(
                    'settings.subscription.cancel.asaas'
                )
            )
            ->assertRedirect(
                route('settings.subscription')
            )
            ->assertSessionHas(
                'billing_info',
                'O cancelamento desta assinatura já está agendado.'
            );

        Http::assertNothingSent();
    }

    public function test_subscription_page_shows_cancel_action_for_active_pro(): void
    {
        [
            $user,
        ] = $this->createProAccount();

        $this
            ->actingAs($user)
            ->get(
                route('settings.subscription')
            )
            ->assertOk()
            ->assertSee(
                'Cancelar assinatura'
            )
            ->assertSee(
                'Cancelar Negozia Pro?'
            )
            ->assertSee(
                'Confirmar cancelamento'
            );
    }
}
