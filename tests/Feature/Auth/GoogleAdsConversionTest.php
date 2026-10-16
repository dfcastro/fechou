<?php

namespace Tests\Feature\Auth;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class GoogleAdsConversionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    private function enableGoogleAdsTracking(): void
    {
        config()->set('services.google_ads.id', 'AW-123456789');
        config()->set('services.google_ads.registration_label', 'TestRegistrationLabel');
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

    private function register(): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('register.store'), [
            'name' => 'Teste Anúncios',
            'email' => 'ads-test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
    }

    public function test_google_ads_tag_is_disabled_without_configuration(): void
    {
        config()->set('services.google_ads.id', null);
        config()->set('services.google_ads.registration_label', null);

        $this->get(route('register'))
            ->assertOk()
            ->assertDontSee('negozia_google_ads_consent_v1');
    }

    public function test_failed_registration_does_not_mark_a_conversion(): void
    {
        $this->enableGoogleAdsTracking();

        $this->post(route('register.store'), [
            'name' => 'Teste',
            'email' => 'invalid-email',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors();

        $this->assertNull(session('google_ads_registration_pending'));
    }

    public function test_registration_conversion_is_available_once_on_onboarding(): void
    {
        $this->enableGoogleAdsTracking();
        $this->createFreePlan();

        $this->register()
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();

        $token = session('google_ads_registration_pending');
        $this->assertNotNull($token);

        $response = $this->get(route('onboarding'));
        $response->assertOk()
            ->assertSee('AW-123456789')
            ->assertSee('TestRegistrationLabel')
            ->assertSee($token);

        $this->assertNull(session('google_ads_registration_pending'));

        $this->get(route('onboarding'))
            ->assertOk()
            ->assertDontSee($token);
    }
}
