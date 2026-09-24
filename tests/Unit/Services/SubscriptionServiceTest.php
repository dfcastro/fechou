<?php

namespace Tests\Unit\Services;

use App\Models\Business;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
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

    private function createPlan(
        ?int $quoteLimit
    ): Plan {
        return Plan::create([
            'name' => $quoteLimit === null
                ? 'Pro'
                : 'Grátis',

            'slug' => $quoteLimit === null
                ? 'pro'
                : 'free',

            'price' => $quoteLimit === null
                ? 29.90
                : 0,

            'quote_limit' => $quoteLimit,

            'billing_interval' => 'month',

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

    private function createQuote(
        Business $business,
        array $attributes = []
    ): Quote {
        $client = Client::factory()->create([
            'business_id' => $business->id,
        ]);

        return Quote::factory()->create(
            array_merge([
                'business_id' => $business->id,
                'client_id' => $client->id,
                'created_at' => now(),
                'updated_at' => now(),
            ], $attributes)
        );
    }

    public function test_business_without_subscription_cannot_create_quote(): void
    {
        $business = Business::factory()->create();

        $this->assertFalse(
            $this->service->canCreateQuote(
                $business
            )
        );

        $this->assertSame(
            0,
            $this->service->quotesRemaining(
                $business
            )
        );
    }

    public function test_unlimited_plan_can_create_quotes(): void
    {
        $business = Business::factory()->create();

        $plan = $this->createPlan(null);

        $this->subscribe(
            $business,
            $plan
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

    public function test_limited_plan_reports_used_and_remaining_quotes(): void
    {
        $business = Business::factory()->create();

        $plan = $this->createPlan(5);

        $this->subscribe(
            $business,
            $plan
        );

        $this->createQuote($business);
        $this->createQuote($business);

        $this->assertSame(
            2,
            $this->service->quotesUsed(
                $business
            )
        );

        $this->assertSame(
            3,
            $this->service->quotesRemaining(
                $business
            )
        );

        $this->assertTrue(
            $this->service->canCreateQuote(
                $business
            )
        );
    }

    public function test_business_cannot_create_quote_when_limit_is_reached(): void
    {
        $business = Business::factory()->create();

        $plan = $this->createPlan(2);

        $this->subscribe(
            $business,
            $plan
        );

        $this->createQuote($business);
        $this->createQuote($business);

        $this->assertSame(
            2,
            $this->service->quotesUsed(
                $business
            )
        );

        $this->assertSame(
            0,
            $this->service->quotesRemaining(
                $business
            )
        );

        $this->assertFalse(
            $this->service->canCreateQuote(
                $business
            )
        );
    }

    public function test_independent_duplicate_consumes_additional_limit(): void
    {
        $business = Business::factory()->create();

        $plan = $this->createPlan(2);

        $this->subscribe(
            $business,
            $plan
        );

        $this->createQuote(
            $business,
            [
                'root_quote_id' => null,
            ]
        );

        /*
         * Uma duplicação é outra proposta independente.
         * Portanto também consome uma unidade da cota.
         */
        $this->createQuote(
            $business,
            [
                'root_quote_id' => null,
            ]
        );

        $this->assertSame(
            2,
            $this->service->quotesUsed(
                $business
            )
        );

        $this->assertSame(
            0,
            $this->service->quotesRemaining(
                $business
            )
        );

        $this->assertFalse(
            $this->service->canCreateQuote(
                $business
            )
        );
    }

    public function test_quotes_outside_current_period_do_not_count(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-08-20 12:00')
        );

        $business = Business::factory()->create();

        $plan = $this->createPlan(2);

        $this->subscribe(
            $business,
            $plan,
            [
                'current_period_starts_at' =>
                    Carbon::parse('2026-08-01'),

                'current_period_ends_at' =>
                    Carbon::parse('2026-08-31 23:59:59'),
            ]
        );

        $this->createQuote(
            $business,
            [
                'created_at' =>
                    Carbon::parse('2026-07-20'),

                'updated_at' =>
                    Carbon::parse('2026-07-20'),
            ]
        );

        $this->createQuote(
            $business,
            [
                'created_at' =>
                    Carbon::parse('2026-08-10'),

                'updated_at' =>
                    Carbon::parse('2026-08-10'),
            ]
        );

        $this->assertSame(
            1,
            $this->service->quotesUsed(
                $business
            )
        );

        $this->assertSame(
            1,
            $this->service->quotesRemaining(
                $business
            )
        );

        Carbon::setTestNow();
    }

    public function test_expired_trial_cannot_create_quote(): void
    {
        $business = Business::factory()->create();

        $plan = $this->createPlan(null);

        $this->subscribe(
            $business,
            $plan,
            [
                'status' => 'trialing',
                'trial_ends_at' => now()->subDay(),
            ]
        );

        $this->assertFalse(
            $this->service->canCreateQuote(
                $business
            )
        );

        $this->assertSame(
            0,
            $this->service->quotesRemaining(
                $business
            )
        );
    }

    public function test_valid_trial_can_use_plan_limits(): void
    {
        $business = Business::factory()->create();

        $plan = $this->createPlan(3);

        $this->subscribe(
            $business,
            $plan,
            [
                'status' => 'trialing',
                'trial_ends_at' => now()->addDays(7),
            ]
        );

        $this->createQuote($business);

        $this->assertTrue(
            $this->service->canCreateQuote(
                $business
            )
        );

        $this->assertSame(
            2,
            $this->service->quotesRemaining(
                $business
            )
        );
    }
    public function test_default_free_subscription_can_be_created(): void
    {
        $business = Business::factory()->create();

        $plan = Plan::create([
            'name' => 'Grátis',
            'slug' => 'free',
            'price' => 0,
            'quote_limit' => null,
            'billing_interval' => 'month',
            'is_active' => true,
        ]);

        $subscription = $this->service
            ->ensureDefaultSubscription(
                $business
            );

        $this->assertSame(
            $business->id,
            $subscription->business_id
        );

        $this->assertSame(
            $plan->id,
            $subscription->plan_id
        );

        $this->assertSame(
            'active',
            $subscription->status
        );

        $this->assertSame(
            'free',
            $subscription->plan->slug
        );
    }

    public function test_default_subscription_is_not_duplicated(): void
    {
        $business = Business::factory()->create();

        Plan::create([
            'name' => 'Grátis',
            'slug' => 'free',
            'price' => 0,
            'quote_limit' => null,
            'billing_interval' => 'month',
            'is_active' => true,
        ]);

        $first = $this->service
            ->ensureDefaultSubscription(
                $business
            );

        $second = $this->service
            ->ensureDefaultSubscription(
                $business
            );

        $this->assertSame(
            $first->id,
            $second->id
        );

        $this->assertDatabaseCount(
            'subscriptions',
            1
        );
    }
    public function test_deleted_quotes_still_consume_limit(): void
    {
        $business = Business::factory()->create();

        $plan = $this->createPlan(1);

        $this->subscribe(
            $business,
            $plan
        );

        $quote = $this->createQuote(
            $business,
            [
                'root_quote_id' => null,
            ]
        );

        $quote->delete();

        $this->assertSame(
            1,
            $this->service->quotesUsed($business)
        );

        $this->assertSame(
            0,
            $this->service->quotesRemaining($business)
        );

        $this->assertFalse(
            $this->service->canCreateQuote($business)
        );
    }
}