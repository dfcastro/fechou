<?php

namespace Tests\Feature\Billing;

use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SubscriptionLifecycleCommandTest extends TestCase
{
    use RefreshDatabase;

    private function createPlans(): array
    {
        $free = Plan::create([
            'name' => 'Grátis',
            'slug' => 'free',
            'price' => 0,
            'quote_limit' => 5,
            'billing_interval' => 'month',
            'is_active' => true,
        ]);

        $pro = Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price' => 29.90,
            'quote_limit' => null,
            'billing_interval' => 'month',
            'is_active' => true,
        ]);

        return [$free, $pro];
    }

    public function test_command_marks_expired_grace_as_suspended(): void
    {
        Carbon::setTestNow(
            '2026-10-15 12:00:00'
        );

        [
            ,
            $pro,
        ] = $this->createPlans();

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

                'billing_status' =>
                    'overdue',

                'payment_provider' =>
                    'asaas',

                'grace_ends_at' =>
                    now()->subMinute(),

                'current_period_starts_at' =>
                    now()->startOfMonth(),

                'current_period_ends_at' =>
                    now()->endOfMonth(),
            ]);

        Artisan::call(
            'billing:process-subscriptions'
        );

        $this->assertNotNull(
            $subscription
                ->fresh()
                ->access_suspended_at
        );

        Carbon::setTestNow();
    }

    public function test_command_finalizes_scheduled_cancellation_and_creates_free_subscription(): void
    {
        Carbon::setTestNow(
            '2026-10-15 12:00:00'
        );

        [
            $free,
            $pro,
        ] = $this->createPlans();

        $business =
            Business::factory()->create();

        $subscription =
            Subscription::create([
                'business_id' =>
                    $business->id,

                'plan_id' =>
                    $pro->id,

                'status' =>
                    'active',

                'billing_status' =>
                    'canceling',

                'payment_provider' =>
                    'asaas',

                'canceled_at' =>
                    now()->subDays(10),

                'ends_at' =>
                    now()->subMinute(),

                'current_period_starts_at' =>
                    now()->subMonth(),

                'current_period_ends_at' =>
                    now()->subMinute(),
            ]);

        Artisan::call(
            'billing:process-subscriptions'
        );

        $this->assertSame(
            'canceled',
            $subscription
                ->fresh()
                ->status
        );

        $this->assertDatabaseHas(
            'subscriptions',
            [
                'business_id' =>
                    $business->id,

                'plan_id' =>
                    $free->id,

                'status' =>
                    'active',
            ]
        );

        Carbon::setTestNow();
    }
}
