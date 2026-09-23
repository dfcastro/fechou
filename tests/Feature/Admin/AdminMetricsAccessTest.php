<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMetricsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_metrics(): void
    {
        $response = $this->get(
            route('admin.metrics')
        );

        $response->assertRedirect(
            route('login')
        );
    }

    public function test_regular_user_cannot_access_admin_metrics(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_admin' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('admin.metrics'));

        $response->assertForbidden();
    }

    public function test_admin_can_access_admin_metrics(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_admin' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('admin.metrics'));

        $response->assertOk();
    }
}