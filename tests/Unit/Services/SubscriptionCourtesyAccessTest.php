<?php

namespace Tests\Unit\Services;

use App\Models\Business;
use App\Models\BusinessAccessGrant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionCourtesyAccessTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(
            SubscriptionService::class
        );
    }

    private function freePlan(): Plan
    {
        return Plan::create([
            'name' => 'Grátis',
            'slug' => 'free',
            'price' => 0,
            'billing_interval' => 'month',
            'quote_limit' => 5,
            'is_active' => true,
        ]);
    }

    private function proPlan(): Plan
    {
        return Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price' => 29.90,
            'billing_interval' => 'month',
            'quote_limit' => null,
            'is_active' => true,
        ]);
    }

    private function subscribe(
        Business $business,
        Plan $plan,
        array $attributes = []
    ): Subscription {
        return Subscription::create(
            array_merge([
                'business_id' => $business->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => now(),
                'current_period_starts_at' =>
                    now()->startOfMonth(),
                'current_period_ends_at' =>
                    now()->endOfMonth(),
            ], $attributes)
        );
    }

    private function grant(
        Business $business,
        Plan $plan,
        array $attributes = []
    ): BusinessAccessGrant {
        return BusinessAccessGrant::create(
            array_merge([
                'business_id' => $business->id,
                'plan_id' => $plan->id,
                'type' => 'courtesy',
                'starts_at' => now(),
                'ends_at' => now()
                    ->addDays(7)
                    ->endOfDay(),
            ], $attributes)
        );
    }

    public function test_free_business_with_active_pro_courtesy_uses_pro_access(): void
    {
        $business = Business::factory()->create();

        $free = $this->freePlan();
        $pro = $this->proPlan();

        $this->subscribe(
            $business,
            $free
        );

        $this->grant(
            $business,
            $pro
        );

        $accessPlan = $this->service
            ->accessPlan($business);

        $this->assertNotNull(
            $accessPlan
        );

        $this->assertSame(
            $pro->id,
            $accessPlan->id
        );

        $this->assertTrue(
            $this->service->canCreateQuote(
                $business
            )
        );

        $this->assertNull(
            $this->service->quotesRemaining(
                $business
            )
        );
    }

    public function test_expired_courtesy_falls_back_to_contracted_free_plan(): void
    {
        $business = Business::factory()->create();

        $free = $this->freePlan();
        $pro = $this->proPlan();

        $this->subscribe(
            $business,
            $free
        );

        $this->grant(
            $business,
            $pro,
            [
                'starts_at' =>
                    now()->subDays(10),

                'ends_at' =>
                    now()->subDay(),
            ]
        );

        $accessPlan = $this->service
            ->accessPlan($business);

        $this->assertNotNull(
            $accessPlan
        );

        $this->assertSame(
            $free->id,
            $accessPlan->id
        );

        $this->assertSame(
            5,
            $this->service->quotesRemaining(
                $business
            )
        );
    }

    public function test_revoked_courtesy_falls_back_to_contracted_free_plan(): void
    {
        $business = Business::factory()->create();

        $free = $this->freePlan();
        $pro = $this->proPlan();

        $this->subscribe(
            $business,
            $free
        );

        $this->grant(
            $business,
            $pro,
            [
                'revoked_at' => now(),
            ]
        );

        $accessPlan = $this->service
            ->accessPlan($business);

        $this->assertNotNull(
            $accessPlan
        );

        $this->assertSame(
            $free->id,
            $accessPlan->id
        );
    }

    public function test_future_courtesy_does_not_apply_before_start_date(): void
    {
        $business = Business::factory()->create();

        $free = $this->freePlan();
        $pro = $this->proPlan();

        $this->subscribe(
            $business,
            $free
        );

        $this->grant(
            $business,
            $pro,
            [
                'starts_at' =>
                    now()->addDay(),

                'ends_at' =>
                    now()->addDays(8),
            ]
        );

        $accessPlan = $this->service
            ->accessPlan($business);

        $this->assertNotNull(
            $accessPlan
        );

        $this->assertSame(
            $free->id,
            $accessPlan->id
        );
    }

    public function test_courtesy_does_not_change_financial_subscription(): void
    {
        $business = Business::factory()->create();

        $free = $this->freePlan();
        $pro = $this->proPlan();

        $subscription = $this->subscribe(
            $business,
            $free,
            [
                'payment_provider' => 'asaas',
                'provider_subscription_id' =>
                    'sub_teste_123',
            ]
        );

        $this->grant(
            $business,
            $pro
        );

        $subscription->refresh();

        $this->assertSame(
            $free->id,
            $subscription->plan_id
        );

        $this->assertSame(
            'active',
            $subscription->status
        );

        $this->assertSame(
            'asaas',
            $subscription->payment_provider
        );

        $this->assertSame(
            'sub_teste_123',
            $subscription
                ->provider_subscription_id
        );

        $this->assertSame(
            $pro->id,
            $this->service
                ->accessPlan($business)
                ?->id
        );
    }

    public function test_active_access_grant_returns_current_courtesy(): void
    {
        $business = Business::factory()->create();

        $free = $this->freePlan();
        $pro = $this->proPlan();

        $this->subscribe(
            $business,
            $free
        );

        $grant = $this->grant(
            $business,
            $pro
        );

        $activeGrant = $this->service
            ->activeAccessGrant(
                $business
            );

        $this->assertNotNull(
            $activeGrant
        );

        $this->assertSame(
            $grant->id,
            $activeGrant->id
        );

        $this->assertSame(
            $pro->id,
            $activeGrant->plan->id
        );
    }
}
