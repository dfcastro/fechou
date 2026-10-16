<?php

namespace Tests\Feature\Auth;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationAbuseProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());

        config()->set('services.turnstile.site_key', null);
        config()->set('services.turnstile.secret_key', null);
    }

    private function registrationData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Cliente NegoZia',
            'email' => 'cliente@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ], $overrides);
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

    private function enableTurnstile(): void
    {
        config()->set('services.turnstile.site_key', 'site-key-test');
        config()->set('services.turnstile.secret_key', 'secret-key-test');
    }

    public function test_registration_get_is_not_rate_limited(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $this->get(route('register'))->assertOk();
        }
    }

    public function test_rate_limiter_blocks_sixth_registration_attempt(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.71']);

        for ($i = 0; $i < 5; $i++) {
            // Invalid registration still consumes one attempt, without database writes.
            $this->post(route('register.store'), $this->registrationData([
                'email' => 'invalid-email',
            ]))->assertSessionHasErrors('email');
        }

        $blocked = $this->post(route('register.store'), $this->registrationData([
            'email' => 'invalid-email',
        ]));

        $blocked->assertStatus(429)->assertHeader('Retry-After');
        $this->assertSame(0, User::count());

        // Other Fortify endpoints are not affected by registration throttling.
        $this->get(route('login'))->assertOk();
        $this->get(route('register'))->assertOk();

        // A different client IP can still reach validation normally.
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.72'])
            ->post(route('register.store'), $this->registrationData([
                'email' => 'invalid-email',
            ]))->assertSessionHasErrors('email');
    }

    public function test_transport_peer_limit_still_works_with_forged_forwarded_ip(): void
    {
        $peer = '192.0.2.85';
        $key = 'register:peer:'.hash('sha256', $peer).':hour';

        for ($i = 0; $i < 100; $i++) {
            RateLimiter::hit($key, 3600);
        }

        $this->withServerVariables(['REMOTE_ADDR' => $peer])
            ->withHeaders(['X-Forwarded-For' => '198.51.100.200'])
            ->post(route('register.store'), $this->registrationData([
                'email' => 'invalid-email',
            ]))->assertStatus(429);

        $this->assertSame(0, User::count());
    }

    public function test_turnstile_widget_only_loads_when_site_key_is_set(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertDontSee('challenges.cloudflare.com/turnstile');

        $this->enableTurnstile();

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('class="cf-turnstile"', false)
            ->assertSee('data-sitekey="site-key-test"', false)
            ->assertSee('https://challenges.cloudflare.com/turnstile/v0/api.js', false);
    }

    public function test_configured_turnstile_rejects_missing_token_without_creating_user(): void
    {
        $this->enableTurnstile();
        config()->set('services.google_ads.id', 'AW-1234');
        config()->set('services.google_ads.registration_label', 'registration');

        $this->post(route('register.store'), $this->registrationData())
            ->assertSessionHasErrors('turnstile');

        $this->assertSame(0, User::count());
        $this->assertNull(session('google_ads_registration_pending'));

        Http::assertNothingSent();
    }

    public function test_invalid_turnstile_token_is_rejected_by_cloudflare(): void
    {
        $this->enableTurnstile();

        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' =>
                Http::response(['success' => false], 200),
        ]);

        $this->post(route('register.store'), $this->registrationData([
            'cf-turnstile-response' => 'invalid-token',
        ]))->assertSessionHasErrors('turnstile');

        $this->assertSame(0, User::count());
        Http::assertSentCount(1);
    }

    public function test_malformed_turnstile_token_is_rejected_without_server_error(): void
    {
        $this->enableTurnstile();

        $this->post(route('register.store'), $this->registrationData([
            'cf-turnstile-response' => ['malformed'],
        ]))->assertSessionHasErrors('turnstile');

        $this->assertSame(0, User::count());
        Http::assertNothingSent();
    }

    public function test_cloudflare_unavailability_does_not_create_accounts(): void
    {
        $this->enableTurnstile();

        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' =>
                Http::response(['success' => false], 503),
        ]);

        $this->post(route('register.store'), $this->registrationData([
            'cf-turnstile-response' => 'unverifiable',
        ]))->assertSessionHasErrors('turnstile');

        $this->assertSame(0, User::count());
    }

    public function test_partial_turnstile_configuration_fails_closed(): void
    {
        config()->set('services.turnstile.site_key', 'site-key-only');

        $this->post(route('register.store'), $this->registrationData())
            ->assertSessionHasErrors('turnstile');

        $this->assertSame(0, User::count());
        Http::assertNothingSent();
    }

    public function test_valid_turnstile_token_allows_regular_registration(): void
    {
        $this->createFreePlan();
        $this->enableTurnstile();

        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' =>
                Http::response(['success' => true], 200),
        ]);

        $this->post(route('register.store'), $this->registrationData([
            'cf-turnstile-response' => 'valid-test-token',
        ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertSame(1, User::count());
        $this->assertNotNull(User::first()?->business);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
                && $request['secret'] === 'secret-key-test'
                && $request['response'] === 'valid-test-token';
        });
    }

    public function test_no_turnstile_keys_preserves_normal_registration(): void
    {
        $this->createFreePlan();

        $this->post(route('register.store'), $this->registrationData())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }
}
