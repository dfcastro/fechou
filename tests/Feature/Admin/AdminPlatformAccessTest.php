<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPlatformAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_businesses(): void
    {
        $response = $this->get(
            route('admin.businesses.index')
        );

        $response->assertRedirect(
            route('login')
        );
    }

    public function test_regular_user_cannot_access_admin_businesses(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_admin' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(
                route('admin.businesses.index')
            );

        $response->assertForbidden();
    }

    public function test_admin_can_access_admin_businesses(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_admin' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(
                route('admin.businesses.index')
            );

        $response->assertOk();
    }

    public function test_guest_cannot_access_admin_accesses(): void
    {
        $response = $this->get(
            route('admin.accesses')
        );

        $response->assertRedirect(
            route('login')
        );
    }

    public function test_regular_user_cannot_access_admin_accesses(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_admin' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(
                route('admin.accesses')
            );

        $response->assertForbidden();
    }

    public function test_admin_can_access_admin_accesses(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_admin' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(
                route('admin.accesses')
            );

        $response->assertOk();
    }

    public function test_platform_admin_is_redirected_away_from_operational_dashboard(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_admin' => true,
        ]);

        $this->assertFalse(
            $user->business()->exists()
        );

        $response = $this
            ->actingAs($user)
            ->get(
                route('dashboard')
            );

        $response->assertRedirect(
            route(
                'admin.metrics'
            )
        );
    }
}
