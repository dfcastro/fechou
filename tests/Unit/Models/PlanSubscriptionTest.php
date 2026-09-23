<?php

namespace Tests\Unit\Models;

use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Enums\PlanFeature;

class PlanSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_plan_is_identified_correctly(): void
    {
        $plan = Plan::create([
            'name' => 'Grátis',
            'slug' => 'free',
            'price' => 0,
            'billing_interval' => 'month',
            'quote_limit' => 5,
        ]);

        $this->assertTrue($plan->isFree());
    }

    public function test_paid_plan_is_not_identified_as_free(): void
    {
        $plan = Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price' => 29.90,
            'billing_interval' => 'month',
            'quote_limit' => null,
        ]);

        $this->assertFalse($plan->isFree());
    }

    public function test_plan_can_have_quote_limit(): void
    {
        $plan = Plan::create([
            'name' => 'Grátis',
            'slug' => 'free',
            'price' => 0,
            'billing_interval' => 'month',
            'quote_limit' => 5,
        ]);

        $this->assertSame(
            5,
            $plan->quote_limit
        );

        $this->assertFalse(
            $plan->hasUnlimitedQuotes()
        );
    }

    public function test_plan_can_have_unlimited_quotes(): void
    {
        $plan = Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price' => 29.90,
            'billing_interval' => 'month',
            'quote_limit' => null,
        ]);

        $this->assertTrue(
            $plan->hasUnlimitedQuotes()
        );
    }

    public function test_business_can_have_subscription_history(): void
    {
        $business = Business::factory()->create();

        $freePlan = Plan::create([
            'name' => 'Grátis',
            'slug' => 'free',
            'price' => 0,
        ]);

        $proPlan = Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price' => 29.90,
        ]);

        Subscription::create([
            'business_id' => $business->id,
            'plan_id' => $freePlan->id,
            'status' => 'canceled',
            'starts_at' => now()->subMonths(2),
            'canceled_at' => now()->subMonth(),
        ]);

        Subscription::create([
            'business_id' => $business->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'starts_at' => now()->subMonth(),
        ]);

        $this->assertCount(
            2,
            $business->subscriptions
        );
    }

    public function test_business_resolves_current_subscription(): void
    {
        $business = Business::factory()->create();

        $freePlan = Plan::create([
            'name' => 'Grátis',
            'slug' => 'free',
            'price' => 0,
        ]);

        $proPlan = Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price' => 29.90,
        ]);

        Subscription::create([
            'business_id' => $business->id,
            'plan_id' => $freePlan->id,
            'status' => 'canceled',
            'starts_at' => now()->subMonths(2),
            'canceled_at' => now()->subMonth(),
        ]);

        $activeSubscription = Subscription::create([
            'business_id' => $business->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'starts_at' => now()->subMonth(),
        ]);

        $business->refresh();

        $this->assertNotNull(
            $business->currentSubscription
        );

        $this->assertSame(
            $activeSubscription->id,
            $business->currentSubscription->id
        );

        $this->assertSame(
            'pro',
            $business->currentSubscription->plan->slug
        );
    }

    public function test_active_subscription_is_active(): void
    {
        $business = Business::factory()->create();

        $plan = Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price' => 29.90,
        ]);

        $subscription = Subscription::create([
            'business_id' => $business->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        $this->assertTrue(
            $subscription->isActive()
        );

        $this->assertFalse(
            $subscription->isTrialing()
        );
    }

    public function test_valid_trial_is_active(): void
    {
        $business = Business::factory()->create();

        $plan = Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price' => 29.90,
        ]);

        $subscription = Subscription::create([
            'business_id' => $business->id,
            'plan_id' => $plan->id,
            'status' => 'trialing',
            'starts_at' => now(),
            'trial_ends_at' => now()->addDays(7),
        ]);

        $this->assertTrue(
            $subscription->isTrialing()
        );

        $this->assertTrue(
            $subscription->isActive()
        );
    }

    public function test_expired_trial_is_not_active(): void
    {
        $business = Business::factory()->create();

        $plan = Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price' => 29.90,
        ]);

        $subscription = Subscription::create([
            'business_id' => $business->id,
            'plan_id' => $plan->id,
            'status' => 'trialing',
            'starts_at' => now()->subDays(10),
            'trial_ends_at' => now()->subDays(3),
        ]);

        $this->assertFalse(
            $subscription->isTrialing()
        );

        $this->assertFalse(
            $subscription->isActive()
        );
    }

    public function test_canceled_subscription_is_not_active(): void
    {
        $business = Business::factory()->create();

        $plan = Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price' => 29.90,
        ]);

        $subscription = Subscription::create([
            'business_id' => $business->id,
            'plan_id' => $plan->id,
            'status' => 'canceled',
            'starts_at' => now()->subMonth(),
            'canceled_at' => now(),
        ]);

        $this->assertFalse(
            $subscription->isActive()
        );
    }

    public function test_past_due_subscription_remains_current_but_is_not_active(): void
    {
        $business = Business::factory()->create();

        $plan = Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price' => 29.90,
        ]);

        $subscription = Subscription::create([
            'business_id' => $business->id,
            'plan_id' => $plan->id,
            'status' => 'past_due',
            'starts_at' => now()->subMonth(),
        ]);

        $business->refresh();

        $this->assertSame(
            $subscription->id,
            $business->currentSubscription->id
        );

        $this->assertFalse(
            $subscription->isActive()
        );
    }

    public function test_canceled_subscription_is_not_returned_as_current(): void
    {
        $business = Business::factory()->create();

        $plan = Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price' => 29.90,
        ]);

        Subscription::create([
            'business_id' => $business->id,
            'plan_id' => $plan->id,
            'status' => 'canceled',
            'starts_at' => now()->subMonth(),
            'canceled_at' => now(),
        ]);

        $business->refresh();

        $this->assertNull(
            $business->currentSubscription
        );
    }
    public function test_plan_can_have_feature(): void
    {
        $plan = Plan::create([
            'name' => 'Grátis',
            'slug' => 'free-feature-test',
            'price' => 0,
            'billing_interval' => 'month',
            'quote_limit' => null,
            'features' => [
                PlanFeature::PDF_EXPORT->value,
                PlanFeature::PUBLIC_QUOTE_LINK->value,
            ],
            'is_active' => true,
        ]);

        $this->assertTrue(
            $plan->hasFeature(
                PlanFeature::PDF_EXPORT
            )
        );
    }

    public function test_plan_does_not_have_unassigned_feature(): void
    {
        $plan = Plan::create([
            'name' => 'Grátis',
            'slug' => 'free-no-premium-test',
            'price' => 0,
            'billing_interval' => 'month',
            'quote_limit' => null,
            'features' => [
                PlanFeature::PDF_EXPORT->value,
            ],
            'is_active' => true,
        ]);

        $this->assertFalse(
            $plan->hasFeature(
                PlanFeature::CUSTOM_BRANDING
            )
        );
    }

    public function test_plan_feature_can_be_checked_using_string(): void
    {
        $plan = Plan::create([
            'name' => 'Grátis',
            'slug' => 'free-string-feature-test',
            'price' => 0,
            'billing_interval' => 'month',
            'quote_limit' => null,
            'features' => [
                PlanFeature::PDF_EXPORT->value,
            ],
            'is_active' => true,
        ]);

        $this->assertTrue(
            $plan->hasFeature(
                'pdf_export'
            )
        );
    }

    public function test_plan_can_check_multiple_features(): void
    {
        $plan = Plan::create([
            'name' => 'Grátis',
            'slug' => 'free-all-features-test',
            'price' => 0,
            'billing_interval' => 'month',
            'quote_limit' => null,
            'features' => [
                PlanFeature::PDF_EXPORT->value,
                PlanFeature::PUBLIC_QUOTE_LINK->value,
                PlanFeature::CLIENT_MANAGEMENT->value,
            ],
            'is_active' => true,
        ]);

        $this->assertTrue(
            $plan->hasAllFeatures([
                PlanFeature::PDF_EXPORT,
                PlanFeature::PUBLIC_QUOTE_LINK,
            ])
        );

        $this->assertFalse(
            $plan->hasAllFeatures([
                PlanFeature::PDF_EXPORT,
                PlanFeature::CUSTOM_BRANDING,
            ])
        );
    }
}