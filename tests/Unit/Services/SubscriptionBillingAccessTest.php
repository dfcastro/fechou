<?php

namespace Tests\Unit\Services;

use App\Enums\PlanFeature;
use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SubscriptionBillingAccessTest extends TestCase
{
    use RefreshDatabase;

    private function plans(): array
    {
        $free = Plan::create([
            'name' => 'Grátis',
            'slug' => 'free',
            'price' => 0,
            'quote_limit' => 5,
            'billing_interval' => 'month',
            'features' => [
                PlanFeature::CLIENT_MANAGEMENT->value,
                PlanFeature::PUBLIC_QUOTE_LINK->value,
                PlanFeature::PDF_EXPORT->value,
                PlanFeature::WHATSAPP_SHARING->value,
            ],
            'is_active' => true,
        ]);

        $pro = Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price' => 29.90,
            'quote_limit' => null,
            'billing_interval' => 'month',
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
        ]);

        return [$free, $pro];
    }

    public function test_past_due_subscription_keeps_pro_during_grace(): void
    {
        Carbon::setTestNow(
            '2026-10-10 12:00:00'
        );

        [
            ,
            $pro,
        ] = $this->plans();

        $business =
            Business::factory()->create();

        Subscription::create([
            'business_id' =>
                $business->id,

            'plan_id' =>
                $pro->id,

            'status' =>
                'past_due',

            'payment_provider' =>
                'asaas',

            'past_due_at' =>
                now()->subDay(),

            'grace_ends_at' =>
                now()->addDays(2),

            'current_period_starts_at' =>
                now()->startOfMonth(),

            'current_period_ends_at' =>
                now()->endOfMonth(),
        ]);

        $service = app(
            SubscriptionService::class
        );

        $this->assertFalse(
            $service
                ->hasActiveSubscription(
                    $business
                )
        );

        $this->assertTrue(
            $service->hasFeature(
                $business,
                PlanFeature::FOLLOW_UP
            )
        );

        Carbon::setTestNow();
    }

    public function test_expired_grace_uses_free_features_without_changing_contracted_plan(): void
    {
        Carbon::setTestNow(
            '2026-10-15 12:00:00'
        );

        [
            ,
            $pro,
        ] = $this->plans();

        $business =
            Business::factory()->create();

        $subscription =
            Subscription::create([
                'business_id' =>
                    $business->id,

                'plan_id' =>
                    $pro->id,

                'status' =>
                    'past_due',

                'payment_provider' =>
                    'asaas',

                'past_due_at' =>
                    now()->subDays(5),

                'grace_ends_at' =>
                    now()->subDays(2),

                'current_period_starts_at' =>
                    now()->startOfMonth(),

                'current_period_ends_at' =>
                    now()->endOfMonth(),
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

        $this->assertSame(
            'pro',
            $subscription
                ->fresh()
                ->plan
                ->slug
        );

        Carbon::setTestNow();
    }
}
