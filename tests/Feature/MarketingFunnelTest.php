<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use App\Services\MarketingFunnelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MarketingFunnelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.turnstile.site_key', null);
        config()->set('services.turnstile.secret_key', null);
    }

    private function total(string $event): int
    {
        return (int) DB::table('marketing_funnel_daily_counts')
            ->where('day', now()->toDateString())
            ->where('event', $event)
            ->value('total');
    }

    private function registrationData(): array
    {
        return [
            'name' => 'Cliente Teste',
            'email' => 'marketing-test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ];
    }

    private function createFreePlan(): void
    {
        Plan::create([
            'name' => 'Grátis',
            'slug' => 'free',
            'price' => 0,
            'quote_limit' => 5,
            'billing_interval' => 'month',
            'is_active' => true,
        ]);
    }

    public function test_landing_button_redirects_to_signup_and_counts_aggregate_events(): void
    {
        $this->get(route('for-businesses'))
            ->assertOk()
            ->assertSee('Crie orçamentos profissionais')
            ->assertSee('em minutos, sem planilhas.')
            ->assertSee(route('marketing.start'));

        $this->get(route('for-businesses'))->assertOk();

        $this->get(route('marketing.start'))
            ->assertRedirect(route('register'));

        $this->get(route('register'))->assertOk();

        $this->assertSame(2, $this->total('landing_view'));
        $this->assertSame(1, $this->total('cta_click'));
        $this->assertSame(1, $this->total('register_view'));
        $this->assertSame(0, $this->total('registered'));
    }

    public function test_registration_success_is_counted_only_after_creating_an_account(): void
    {
        $this->createFreePlan();

        $this->post(route('register.store'), $this->registrationData())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertSame(1, User::count());
        $this->assertSame(1, $this->total('register_submit'));
        $this->assertSame(1, $this->total('registered'));
        $this->assertSame(0, $this->total('register_invalid'));
    }

    public function test_invalid_registration_counts_rejection_but_not_a_new_account(): void
    {
        $data = $this->registrationData();
        $data['email'] = 'invalid-email';

        $this->post(route('register.store'), $data)
            ->assertSessionHasErrors('email');

        $this->assertSame(0, User::count());
        $this->assertSame(1, $this->total('register_submit'));
        $this->assertSame(1, $this->total('register_invalid'));
        $this->assertSame(0, $this->total('registered'));
    }

    public function test_turnstile_rejections_are_counted_separately(): void
    {
        config()->set('services.turnstile.site_key', 'site-test');
        config()->set('services.turnstile.secret_key', 'secret-test');

        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify'
                => Http::response(['success' => false]),
        ]);

        $this->post(route('register.store'), [
            ...$this->registrationData(),
            'cf-turnstile-response' => 'invalid-token',
        ])->assertSessionHasErrors('turnstile');

        $this->assertSame(0, User::count());
        $this->assertSame(1, $this->total('register_submit'));
        $this->assertSame(1, $this->total('turnstile_rejected'));
        $this->assertSame(0, $this->total('registered'));
    }

    public function test_metrics_do_not_store_visitor_or_form_identifiers(): void
    {
        $this->get(route('for-businesses', [
            'gclid' => 'example-click-id',
            'email' => 'do-not-store@example.com',
        ]))->assertOk();

        $this->assertSame(
            ['day', 'event', 'total'],
            Schema::getColumnListing('marketing_funnel_daily_counts')
        );

        $row = DB::table('marketing_funnel_daily_counts')->first();
        $this->assertSame('landing_view', $row->event);
        $this->assertSame(1, $row->total);
    }

    public function test_aggregate_summary_uses_selected_date_range(): void
    {
        $service = app(MarketingFunnelService::class);

        $service->record('landing_view');

        $today = $service->summary(now()->startOfDay(), now()->endOfDay());

        $this->assertSame(1, $today['landing_view']);
        $this->assertSame(0, $today['registered']);

        $past = $service->summary(now()->subDays(3), now()->subDays(2));

        $this->assertSame(0, $past['landing_view']);
    }

    public function test_only_admins_can_see_funnel_counters(): void
    {
        $this->get(route('admin.metrics'))->assertRedirect(route('login'));

        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user)->get(route('admin.metrics'))->assertForbidden();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)
            ->get(route('admin.metrics'))
            ->assertOk()
            ->assertSee('Funil de cadastros')
            ->assertSee('Turnstile rejeitado');
    }
    private function sourceTotal(string $source, string $event): int
    {
        return (int) DB::table('marketing_funnel_channel_daily_counts')
            ->where('day', now()->toDateString())
            ->where('source', $source)
            ->where('event', $event)
            ->value('total');
    }

    public function test_google_ads_marker_is_classified_without_storing_click_id(): void
    {
        $this->get(route('for-businesses', [
            'gclid' => 'secret-google-click-id',
            'email' => 'not-stored@example.com',
        ]))->assertOk();

        $this->get(route('marketing.start'))
            ->assertRedirect(route('register'));

        $this->get(route('register'))->assertOk();

        $this->assertSame(1, $this->sourceTotal('google_ads', 'landing_view'));
        $this->assertSame(1, $this->sourceTotal('google_ads', 'cta_click'));
        $this->assertSame(1, $this->sourceTotal('google_ads', 'register_view'));
        $this->assertSame(0, $this->sourceTotal('unattributed', 'landing_view'));

        $this->assertSame(
            ['day', 'source', 'event', 'total'],
            Schema::getColumnListing('marketing_funnel_channel_daily_counts')
        );

        $this->assertSame(
            ['source' => 'google_ads', 'event' => 'landing_view'],
            (array) DB::table('marketing_funnel_channel_daily_counts')
                ->where('event', 'landing_view')
                ->first(['source', 'event'])
        );
    }

    public function test_paid_google_utm_is_classified_without_storing_url_values(): void
    {
        $this->get(route('for-businesses', [
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'campaign-name-must-not-be-stored',
        ]))->assertOk();

        $this->assertSame(1, $this->sourceTotal('google_ads', 'landing_view'));
        $this->assertSame(0, $this->sourceTotal('unattributed', 'landing_view'));
    }

    public function test_nonpaid_utm_does_not_count_as_google_ads(): void
    {
        $this->get(route('for-businesses', [
            'utm_source' => 'google',
            'utm_medium' => 'organic',
        ]))->assertOk();

        $this->assertSame(1, $this->sourceTotal('unattributed', 'landing_view'));
    }

    public function test_expired_session_source_returns_to_unattributed(): void
    {
        $this->get(route('for-businesses', [
            'gclid' => 'test-ads-source',
        ]))->assertOk();

        $this->withSession([
            'marketing_funnel_source' => [
                'source' => 'google_ads',
                'expires_at' => now()->subMinute()->getTimestamp(),
            ],
        ])->get(route('register'))->assertOk();

        $this->assertSame(1, $this->sourceTotal('google_ads', 'landing_view'));
        $this->assertSame(1, $this->sourceTotal('unattributed', 'register_view'));
    }

    public function test_signed_in_admins_are_excluded_from_all_new_counters(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('for-businesses', ['gclid' => 'admin-test']))
            ->assertOk();

        $this->get(route('marketing.start'))
            ->assertRedirect(route('register'));

        $this->assertSame(0, $this->total('landing_view'));
        $this->assertSame(0, $this->total('cta_click'));
        $this->assertSame(0, $this->sourceTotal('google_ads', 'landing_view'));
        $this->assertSame(0, $this->sourceTotal('unattributed', 'cta_click'));
    }

    public function test_channel_breakdown_respects_period_without_rewriting_older_counts(): void
    {
        $service = app(MarketingFunnelService::class);

        $this->get(route('for-businesses'))->assertOk();

        $recent = $service->summaryByChannel(
            now()->startOfDay(),
            now()->endOfDay()
        );

        $this->assertSame(1, $recent['unattributed']['landing_view']);
        $this->assertSame(0, $recent['google_ads']['landing_view']);
        $this->assertSame(1, $service->summary(
            now()->startOfDay(),
            now()->endOfDay()
        )['landing_view']);
    }

}
