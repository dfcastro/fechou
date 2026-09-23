<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();

        /*
         * Este teste representa uma conta que já concluiu
         * a configuração inicial.
         */
        $user->business()->create([
            'name' => 'Empresa Teste',
            'onboarding_completed_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }
}
