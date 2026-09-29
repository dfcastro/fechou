<?php

namespace Tests\Unit\Services;

use App\Models\Business;
use App\Models\BusinessAccessGrant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PlatformOverviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PlatformOverviewServiceTest extends TestCase
{
    use RefreshDatabase;


    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }


    private function plan(
        string $slug,
        float $price,
        ?int $limit
    ): Plan {
        return Plan::create([
            'name' =>
                $slug === 'free'
                    ? 'Grátis'
                    : 'Pro',

            'slug' =>
                $slug,

            'price' =>
                $price,

            'billing_interval' =>
                'month',

            'quote_limit' =>
                $limit,

            'is_active' =>
                true,

            'features' =>
                [],
        ]);
    }


    private function business(
        ?\Carbon\CarbonInterface $lastSeenAt = null
    ): Business {
        $user = User::factory()->create([
            'last_seen_at' =>
                $lastSeenAt,
        ]);

        return Business::factory()->create([
            'user_id' =>
                $user->id,
        ]);
    }


    private function subscription(
        Business $business,
        Plan $plan,
        array $attributes = []
    ): Subscription {
        return Subscription::create(
            array_merge([
                'business_id' =>
                    $business->id,

                'plan_id' =>
                    $plan->id,

                'status' =>
                    'active',

                'starts_at' =>
                    now(),

                'current_period_starts_at' =>
                    now()->startOfMonth(),

                'current_period_ends_at' =>
                    now()->endOfMonth(),
            ], $attributes)
        );
    }


    public function test_builds_platform_overview(): void
    {
        Carbon::setTestNow(
            Carbon::parse(
                '2026-09-29 18:00:00'
            )
        );

        $free = $this->plan(
            'free',
            0,
            5
        );

        $pro = $this->plan(
            'pro',
            29.90,
            null
        );


        $businessFree =
            $this->business(
                now()->subMinutes(5)
            );

        $this->subscription(
            $businessFree,
            $free
        );


        $businessPro =
            $this->business(
                now()->subHours(2)
            );

        $this->subscription(
            $businessPro,
            $pro
        );


        $businessCourtesy =
            $this->business(
                now()->subDays(10)
            );

        $this->subscription(
            $businessCourtesy,
            $free
        );

        BusinessAccessGrant::create([
            'business_id' =>
                $businessCourtesy->id,

            'plan_id' =>
                $pro->id,

            'type' =>
                'courtesy',

            'starts_at' =>
                now(),

            'ends_at' =>
                now()->addDays(10),
        ]);


        $overview = app(
            PlatformOverviewService::class
        )->summary();


        $this->assertSame(
            3,
            $overview[
                'businesses'
            ]['total']
        );

        $this->assertSame(
            1,
            $overview[
                'businesses'
            ]['active_now']
        );

        $this->assertSame(
            2,
            $overview[
                'businesses'
            ]['active_today']
        );

        $this->assertSame(
            2,
            $overview[
                'businesses'
            ]['active_7_days']
        );

        $this->assertSame(
            2,
            $overview[
                'plans'
            ]['free']
        );

        $this->assertSame(
            1,
            $overview[
                'plans'
            ]['pro']
        );

        $this->assertSame(
            1,
            $overview[
                'plans'
            ]['courtesy']
        );

        $this->assertSame(
            29.90,
            $overview[
                'revenue'
            ]['mrr']
        );
    }


    public function test_builds_subscription_health_and_attention_list(): void
    {
        Carbon::setTestNow(
            Carbon::parse(
                '2026-09-29 18:00:00'
            )
        );

        $free = $this->plan(
            'free',
            0,
            5
        );

        $pro = $this->plan(
            'pro',
            29.90,
            null
        );


        /*
         * Pro ativo.
         */
        $active =
            $this->business();

        $this->subscription(
            $active,
            $pro
        );


        /*
         * Pro past_due em tolerância.
         */
        $grace =
            $this->business();

        $this->subscription(
            $grace,
            $pro,
            [
                'status' =>
                    'past_due',

                'past_due_at' =>
                    now(),

                'grace_ends_at' =>
                    now()->addDays(2),
            ]
        );


        /*
         * Pro com cancelamento agendado.
         */
        $scheduled =
            $this->business();

        $this->subscription(
            $scheduled,
            $pro,
            [
                'canceled_at' =>
                    now(),

                'ends_at' =>
                    now()->addDays(5),
            ]
        );


        /*
         * Pro suspenso.
         */
        $suspended =
            $this->business();

        $this->subscription(
            $suspended,
            $pro,
            [
                'status' =>
                    'past_due',

                'past_due_at' =>
                    now()->subDays(5),

                'grace_ends_at' =>
                    now()->subDay(),

                'access_suspended_at' =>
                    now(),
            ]
        );


        /*
         * Free em cortesia perto de expirar.
         */
        $courtesy =
            $this->business();

        $this->subscription(
            $courtesy,
            $free
        );

        BusinessAccessGrant::create([
            'business_id' =>
                $courtesy->id,

            'plan_id' =>
                $pro->id,

            'type' =>
                'courtesy',

            'starts_at' =>
                now(),

            'ends_at' =>
                now()->addDays(3),
        ]);


        /*
         * Empresa sem assinatura.
         */
        $this->business();


        $overview = app(
            PlatformOverviewService::class
        )->summary();


        $this->assertSame(
            2,
            $overview[
                'health'
            ]['pro_active']
        );

        $this->assertSame(
            2,
            $overview[
                'health'
            ]['payment_pending']
        );

        $this->assertSame(
            1,
            $overview[
                'health'
            ]['grace_period']
        );

        $this->assertSame(
            1,
            $overview[
                'health'
            ]['scheduled_cancellation']
        );

        $this->assertSame(
            1,
            $overview[
                'health'
            ]['suspended']
        );

        $this->assertSame(
            1,
            $overview[
                'health'
            ]['courtesy']
        );

        $this->assertSame(
            1,
            $overview[
                'health'
            ]['without_subscription']
        );

        $this->assertSame(
            59.80,
            $overview[
                'revenue'
            ]['mrr']
        );


        $types = collect(
            $overview['attention']
        )->pluck('type');


        $this->assertTrue(
            $types->contains(
                'payment_pending'
            )
        );

        $this->assertTrue(
            $types->contains(
                'suspended'
            )
        );

        $this->assertTrue(
            $types->contains(
                'scheduled_cancellation'
            )
        );

        $this->assertTrue(
            $types->contains(
                'courtesy_expiring'
            )
        );
    }


    public function test_expired_courtesy_is_not_counted(): void
    {
        Carbon::setTestNow(
            Carbon::parse(
                '2026-09-29 18:00:00'
            )
        );

        $free = $this->plan(
            'free',
            0,
            5
        );

        $pro = $this->plan(
            'pro',
            29.90,
            null
        );

        $business =
            $this->business();

        $this->subscription(
            $business,
            $free
        );

        BusinessAccessGrant::create([
            'business_id' =>
                $business->id,

            'plan_id' =>
                $pro->id,

            'type' =>
                'courtesy',

            'starts_at' =>
                now()->subDays(10),

            'ends_at' =>
                now()->subDay(),
        ]);

        $overview = app(
            PlatformOverviewService::class
        )->summary();

        $this->assertSame(
            0,
            $overview[
                'plans'
            ]['courtesy']
        );

        $this->assertSame(
            0,
            $overview[
                'health'
            ]['courtesy']
        );
    }
}
