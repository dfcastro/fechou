<?php

namespace Tests\Feature\Admin;

use App\Models\Business;
use App\Models\BusinessAccessGrant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AdminBusinessCourtesyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Business $business;

    private Plan $free;

    private Plan $pro;

    private Subscription $subscription;


    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(
            Carbon::parse(
                '2026-09-29 18:00:00'
            )
        );

        $this->admin = User::factory()->create([
            'name' => 'Administrador',
            'email_verified_at' => now(),
            'is_admin' => true,
        ]);

        $this->business = Business::factory()->create();

        $this->free = Plan::create([
            'name' => 'Grátis',
            'slug' => 'free',
            'price' => 0,
            'billing_interval' => 'month',
            'quote_limit' => 5,
            'is_active' => true,
            'features' => [],
        ]);

        $this->pro = Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price' => 29.90,
            'billing_interval' => 'month',
            'quote_limit' => null,
            'is_active' => true,
            'features' => [],
        ]);

        $this->subscription = Subscription::create([
            'business_id' => $this->business->id,
            'plan_id' => $this->free->id,
            'status' => 'active',
            'starts_at' => now(),
            'current_period_starts_at' =>
                now()->startOfMonth(),
            'current_period_ends_at' =>
                now()->endOfMonth(),

            'payment_provider' =>
                'asaas',

            'provider_subscription_id' =>
                'sub_financeira_123',
        ]);
    }


    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }


    private function adminComponent()
    {
        return Livewire::actingAs(
            $this->admin
        )->test(
            'pages::admin.businesses.show',
            [
                'business' => $this->business,
            ]
        );
    }


    public function test_admin_can_select_seven_days_without_granting_immediately(): void
    {
        $component = $this->adminComponent()
            ->call(
                'selectDays',
                7
            )
            ->assertSet(
                'selectedDays',
                7
            )
            ->assertSet(
                'customEndsAt',
                null
            );

        $this->assertSame(
            0,
            BusinessAccessGrant::query()
                ->count()
        );

        $component->assertHasNoErrors();
    }


    public function test_admin_can_grant_seven_days_of_pro_access(): void
    {
        $this->adminComponent()
            ->call(
                'selectDays',
                7
            )
            ->call(
                'grantAccess'
            )
            ->assertHasNoErrors()
            ->assertSet(
                'selectedDays',
                null
            );

        $grant = BusinessAccessGrant::query()
            ->firstOrFail();

        $this->assertSame(
            $this->business->id,
            $grant->business_id
        );

        $this->assertSame(
            $this->pro->id,
            $grant->plan_id
        );

        $this->assertSame(
            'courtesy',
            $grant->type
        );

        $this->assertSame(
            $this->admin->id,
            $grant->granted_by
        );

        $this->assertSame(
            '06/10/2026 23:59',
            $grant
                ->ends_at
                ->format('d/m/Y H:i')
        );
    }


    public function test_second_seven_day_grant_extends_existing_courtesy(): void
    {
        $component = $this->adminComponent();

        $component
            ->call(
                'selectDays',
                7
            )
            ->call(
                'grantAccess'
            )
            ->assertHasNoErrors();

        $firstGrant = BusinessAccessGrant::query()
            ->firstOrFail();

        $this->assertSame(
            '06/10/2026 23:59',
            $firstGrant
                ->ends_at
                ->format('d/m/Y H:i')
        );

        /*
         * Mais sete dias devem ser somados
         * ao término atual, e não reiniciados
         * a partir de hoje.
         */
        $component
            ->call(
                'selectDays',
                7
            )
            ->call(
                'grantAccess'
            )
            ->assertHasNoErrors();

        $this->assertSame(
            1,
            BusinessAccessGrant::query()
                ->count()
        );

        $grant = BusinessAccessGrant::query()
            ->firstOrFail();

        $this->assertSame(
            '13/10/2026 23:59',
            $grant
                ->ends_at
                ->format('d/m/Y H:i')
        );
    }


    public function test_admin_can_grant_fifteen_days(): void
    {
        $this->adminComponent()
            ->call(
                'selectDays',
                15
            )
            ->call(
                'grantAccess'
            )
            ->assertHasNoErrors();

        $grant = BusinessAccessGrant::query()
            ->firstOrFail();

        $this->assertSame(
            '14/10/2026 23:59',
            $grant
                ->ends_at
                ->format('d/m/Y H:i')
        );
    }


    public function test_admin_can_grant_thirty_days(): void
    {
        $this->adminComponent()
            ->call(
                'selectDays',
                30
            )
            ->call(
                'grantAccess'
            )
            ->assertHasNoErrors();

        $grant = BusinessAccessGrant::query()
            ->firstOrFail();

        $this->assertSame(
            '29/10/2026 23:59',
            $grant
                ->ends_at
                ->format('d/m/Y H:i')
        );
    }


    public function test_admin_can_use_custom_end_date(): void
    {
        $this->adminComponent()
            ->set(
                'customEndsAt',
                '2026-10-20'
            )
            ->assertSet(
                'selectedDays',
                null
            )
            ->call(
                'grantAccess'
            )
            ->assertHasNoErrors();

        $grant = BusinessAccessGrant::query()
            ->firstOrFail();

        $this->assertSame(
            '20/10/2026 23:59',
            $grant
                ->ends_at
                ->format('d/m/Y H:i')
        );
    }


    public function test_custom_date_clears_selected_quick_period(): void
    {
        $this->adminComponent()
            ->call(
                'selectDays',
                15
            )
            ->assertSet(
                'selectedDays',
                15
            )
            ->set(
                'customEndsAt',
                '2026-10-25'
            )
            ->assertSet(
                'selectedDays',
                null
            );
    }


    public function test_grant_requires_a_period(): void
    {
        $this->adminComponent()
            ->call(
                'grantAccess'
            )
            ->assertHasErrors([
                'accessPeriod',
            ]);

        $this->assertSame(
            0,
            BusinessAccessGrant::query()
                ->count()
        );
    }


    public function test_custom_date_must_be_after_today(): void
    {
        $this->adminComponent()
            ->set(
                'customEndsAt',
                '2026-09-29'
            )
            ->call(
                'grantAccess'
            )
            ->assertHasErrors([
                'customEndsAt',
            ]);

        $this->assertSame(
            0,
            BusinessAccessGrant::query()
                ->count()
        );
    }


    public function test_admin_can_revoke_active_courtesy(): void
    {
        $this->adminComponent()
            ->call(
                'selectDays',
                7
            )
            ->call(
                'grantAccess'
            )
            ->assertHasNoErrors();

        $grant = BusinessAccessGrant::query()
            ->firstOrFail();

        $this->assertNull(
            $grant->revoked_at
        );

        $this->adminComponent()
            ->call(
                'revokeGrant'
            )
            ->assertHasNoErrors();

        $grant->refresh();

        $this->assertNotNull(
            $grant->revoked_at
        );

        $this->assertSame(
            $this->admin->id,
            $grant->revoked_by
        );
    }


    public function test_courtesy_does_not_change_financial_subscription(): void
    {
        $this->adminComponent()
            ->call(
                'selectDays',
                30
            )
            ->call(
                'grantAccess'
            )
            ->assertHasNoErrors();

        $this->subscription->refresh();

        $this->assertSame(
            $this->free->id,
            $this->subscription->plan_id
        );

        $this->assertSame(
            'active',
            $this->subscription->status
        );

        $this->assertSame(
            'asaas',
            $this->subscription
                ->payment_provider
        );

        $this->assertSame(
            'sub_financeira_123',
            $this->subscription
                ->provider_subscription_id
        );
    }


    public function test_revoking_courtesy_does_not_change_financial_subscription(): void
    {
        $this->adminComponent()
            ->call(
                'selectDays',
                7
            )
            ->call(
                'grantAccess'
            )
            ->assertHasNoErrors();

        $this->adminComponent()
            ->call(
                'revokeGrant'
            )
            ->assertHasNoErrors();

        $this->subscription->refresh();

        $this->assertSame(
            $this->free->id,
            $this->subscription->plan_id
        );

        $this->assertSame(
            'active',
            $this->subscription->status
        );

        $this->assertSame(
            'asaas',
            $this->subscription
                ->payment_provider
        );

        $this->assertSame(
            'sub_financeira_123',
            $this->subscription
                ->provider_subscription_id
        );
    }
}
