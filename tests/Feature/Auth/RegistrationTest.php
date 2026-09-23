<?php

namespace Tests\Feature\Auth;

use App\Models\Plan;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(
            Features::registration()
        );
    }

    private function createFreePlan(): Plan
    {
        return Plan::create([
            'name' => 'Grátis',
            'slug' => 'free',
            'price' => 0,
            'quote_limit' => 5,
            'billing_interval' => 'month',
            'is_active' => true,
        ]);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(
            route('register')
        );

        $response->assertOk();
    }

    public function test_new_users_can_register(): void
    {
        $this->createFreePlan();

        $response = $this->post(
            route('register.store'),
            [
                'name' => 'John Doe',
                'email' => 'test@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ]
        );

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route(
                    'dashboard',
                    absolute: false
                )
            );

        $this->assertAuthenticated();

        $user = User::query()
            ->where(
                'email',
                'test@example.com'
            )
            ->firstOrFail();

        $this->assertNotNull(
            $user->business
        );

        $this->assertSame(
            'John Doe',
            $user->business->name
        );

        $subscription = $user
            ->business
            ->subscriptions()
            ->with('plan')
            ->latest('id')
            ->first();

        $this->assertNotNull(
            $subscription
        );

        $this->assertSame(
            'free',
            $subscription->plan->slug
        );

        $this->assertSame(
            5,
            app(SubscriptionService::class)
                ->quotesRemaining(
                    $user->business
                )
        );
    }
}