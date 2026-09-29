<?php

namespace Tests\Feature\Admin;

use App\Models\AdminAuditLog;
use App\Models\Business;
use App\Models\BusinessAccessGrant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AdminAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
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
            'email' => 'admin@negozia.local',
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
            'business_id' =>
                $this->business->id,

            'plan_id' =>
                $this->free->id,

            'status' =>
                'active',

            'starts_at' =>
                now(),

            'current_period_starts_at' =>
                now()->startOfMonth(),

            'current_period_ends_at' =>
                now()->endOfMonth(),

            'payment_provider' =>
                'asaas',

            'provider_subscription_id' =>
                'sub_audit_test_123',
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
                'business' =>
                    $this->business,
            ]
        );
    }


    public function test_granting_courtesy_creates_audit_log(): void
    {
        $this->adminComponent()
            ->set(
                'reason',
                'Teste comercial'
            )
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

        $log = AdminAuditLog::query()
            ->where(
                'action',
                'courtesy.granted'
            )
            ->firstOrFail();

        $this->assertSame(
            $this->admin->id,
            $log->admin_user_id
        );

        $this->assertSame(
            $this->business->id,
            $log->business_id
        );

        $this->assertSame(
            $grant->getMorphClass(),
            $log->subject_type
        );

        $this->assertSame(
            $grant->id,
            $log->subject_id
        );

        $this->assertSame(
            $this->pro->id,
            data_get(
                $log->metadata,
                'plan_id'
            )
        );

        $this->assertSame(
            'Teste comercial',
            data_get(
                $log->metadata,
                'reason'
            )
        );

        $loggedEndsAt = Carbon::parse(
            data_get(
                $log->metadata,
                'ends_at'
            )
        );

        $this->assertTrue(
            $loggedEndsAt->equalTo(
                $grant->ends_at
            )
        );
    }


    public function test_extending_courtesy_creates_extension_audit_log(): void
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

        $grant = BusinessAccessGrant::query()
            ->firstOrFail();

        $firstEndsAt =
            $grant->ends_at->copy();

        $component
            ->call(
                'selectDays',
                7
            )
            ->call(
                'grantAccess'
            )
            ->assertHasNoErrors();

        $grant->refresh();

        $log = AdminAuditLog::query()
            ->where(
                'action',
                'courtesy.extended'
            )
            ->firstOrFail();

        $this->assertSame(
            2,
            AdminAuditLog::query()
                ->count()
        );

        $this->assertSame(
            $grant->id,
            $log->subject_id
        );

        $previousEndsAt = Carbon::parse(
            data_get(
                $log->metadata,
                'previous_ends_at'
            )
        );

        $newEndsAt = Carbon::parse(
            data_get(
                $log->metadata,
                'ends_at'
            )
        );

        $this->assertTrue(
            $previousEndsAt->equalTo(
                $firstEndsAt
            )
        );

        $this->assertTrue(
            $newEndsAt->equalTo(
                $grant->ends_at
            )
        );

        $this->assertSame(
            '13/10/2026 23:59',
            $grant
                ->ends_at
                ->format('d/m/Y H:i')
        );
    }


    public function test_revoking_courtesy_creates_revocation_audit_log(): void
    {
        $component = $this->adminComponent();

        $component
            ->set(
                'reason',
                'Cortesia de demonstração'
            )
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

        $component
            ->call(
                'revokeGrant'
            )
            ->assertHasNoErrors();

        $grant->refresh();

        $log = AdminAuditLog::query()
            ->where(
                'action',
                'courtesy.revoked'
            )
            ->firstOrFail();

        $this->assertSame(
            2,
            AdminAuditLog::query()
                ->count()
        );

        $this->assertSame(
            $this->admin->id,
            $log->admin_user_id
        );

        $this->assertSame(
            $this->business->id,
            $log->business_id
        );

        $this->assertSame(
            $grant->id,
            $log->subject_id
        );

        $this->assertSame(
            'Cortesia de demonstração',
            data_get(
                $log->metadata,
                'reason'
            )
        );

        $this->assertNotNull(
            $grant->revoked_at
        );

        $this->assertSame(
            $this->admin->id,
            $grant->revoked_by
        );
    }


    public function test_audit_service_records_ip_and_user_agent(): void
    {
        $request = Request::create(
            '/admin/teste-auditoria',
            'POST',
            [],
            [],
            [],
            [
                'REMOTE_ADDR' =>
                    '203.0.113.10',

                'HTTP_USER_AGENT' =>
                    'Negozia Audit Test/1.0',
            ]
        );

        app()->instance(
            'request',
            $request
        );

        Auth::login(
            $this->admin
        );

        $log = app(
            AdminAuditService::class
        )->record(
            'test.audit',
            $this->business,
            null,
            [
                'source' =>
                    'automated-test',
            ]
        );

        $this->assertSame(
            $this->admin->id,
            $log->admin_user_id
        );

        $this->assertSame(
            $this->business->id,
            $log->business_id
        );

        $this->assertSame(
            '203.0.113.10',
            $log->ip_address
        );

        $this->assertSame(
            'Negozia Audit Test/1.0',
            $log->user_agent
        );

        $this->assertSame(
            'automated-test',
            data_get(
                $log->metadata,
                'source'
            )
        );
    }


    public function test_regular_user_cannot_access_audit_page(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_admin' => false,
        ]);

        $this
            ->actingAs($user)
            ->get(
                route('admin.audit')
            )
            ->assertForbidden();
    }


    public function test_admin_can_access_audit_page(): void
    {
        $this
            ->actingAs($this->admin)
            ->get(
                route('admin.audit')
            )
            ->assertOk();
    }

    public function test_company_page_shows_recent_admin_history(): void
    {
        AdminAuditLog::create([
            'admin_user_id' =>
                $this->admin->id,

            'business_id' =>
                $this->business->id,

            'action' =>
                'courtesy.granted',

            'metadata' => [
                'ends_at' =>
                    now()
                        ->addDays(7)
                        ->toDateTimeString(),

                'reason' =>
                    'Histórico empresa teste',
            ],
        ]);


        $response =
            $this
                ->actingAs(
                    $this->admin
                )
                ->get(
                    route(
                        'admin.businesses.show',
                        $this->business
                    )
                );


        $response
            ->assertOk()
            ->assertSee(
                'Histórico administrativo'
            )
            ->assertSee(
                'Concedeu cortesia Pro'
            )
            ->assertSee(
                'Histórico empresa teste'
            )
            ->assertSee(
                'Ver histórico completo'
            );
    }


    public function test_audit_page_can_filter_logs_by_business(): void
    {
        $otherUser =
            User::factory()->create();

        $otherBusiness =
            Business::factory()->create([
                'user_id' =>
                    $otherUser->id,

                'name' =>
                    'Empresa Fora do Filtro',
            ]);


        AdminAuditLog::create([
            'admin_user_id' =>
                $this->admin->id,

            'business_id' =>
                $this->business->id,

            'action' =>
                'courtesy.granted',
        ]);


        AdminAuditLog::create([
            'admin_user_id' =>
                $this->admin->id,

            'business_id' =>
                $otherBusiness->id,

            'action' =>
                'courtesy.granted',
        ]);


        $response =
            $this
                ->actingAs(
                    $this->admin
                )
                ->get(
                    route(
                        'admin.audit',
                        [
                            'business' =>
                                $this->business->id,
                        ]
                    )
                );


        $response
            ->assertOk()
            ->assertSee(
                $this->business->name
            )
            ->assertDontSee(
                'Empresa Fora do Filtro'
            );
    }

}
