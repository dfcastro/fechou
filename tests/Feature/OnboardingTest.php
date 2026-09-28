<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Plan::create([
            'name' => 'Grátis',
            'slug' => 'free',
            'price' => 0,
            'quote_limit' => 5,
            'billing_interval' => 'month',
            'is_active' => true,
        ]);
    }

    private function userWithBusiness(
        bool $completed = false
    ): User {
        $user = User::factory()->create();

        $user->business()->create([
            'name' => 'Empresa Teste',
            'onboarding_completed_at' =>
                $completed ? now() : null,
        ]);

        return $user->fresh();
    }

    public function test_guest_cannot_access_onboarding(): void
    {
        $this->get(
            route('onboarding')
        )->assertRedirect(
            route('login')
        );
    }

    public function test_incomplete_user_is_redirected_from_dashboard(): void
    {
        $user = $this->userWithBusiness();

        $this
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(
                route('onboarding')
            );
    }

    public function test_incomplete_user_cannot_open_new_quote_directly(): void
    {
        $user = $this->userWithBusiness();

        $this
            ->actingAs($user)
            ->get(route('quotes.create'))
            ->assertRedirect(
                route('onboarding')
            );
    }

    public function test_completed_user_skips_onboarding(): void
    {
        $user = $this->userWithBusiness(true);

        $this
            ->actingAs($user)
            ->get(route('onboarding'))
            ->assertRedirect(
                route('dashboard')
            );
    }

    public function test_onboarding_rejects_document_used_by_another_business(): void
    {
        $owner =
            User::factory()->create();

        Business::factory()->create([
            'user_id' =>
                $owner->id,

            'document' =>
                '24971563792',
        ]);

        $user =
            $this->userWithBusiness();

        Livewire::actingAs($user)
            ->test(
                'pages::onboarding'
            )
            ->set(
                'name',
                'Empresa duplicada'
            )
            ->set(
                'document',
                '249.715.637-92'
            )
            ->set(
                'whatsapp',
                '33999999999'
            )
            ->call('save')
            ->assertHasErrors([
                'document',
            ])
            ->assertSee(
                'Este CPF/CNPJ já está cadastrado em outra conta.'
            );

        $this->assertNull(
            $user
                ->fresh()
                ->business
                ->onboarding_completed_at
        );
    }


    public function test_user_can_complete_onboarding(): void
    {
        $user = $this->userWithBusiness();

        Livewire::actingAs($user)
            ->test('pages::onboarding')
            ->set(
                'name',
                'ClimaTech Refrigeração'
            )
            ->set(
                'document',
                '24971563792'
            )
            ->set(
                'whatsapp',
                '33999999999'
            )
            ->set(
                'pixKey',
                'financeiro@climatech.test'
            )
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(
                route('dashboard')
            );

        $business = $user
            ->fresh()
            ->business;

        $this->assertSame(
            'ClimaTech Refrigeração',
            $business->name
        );

        $this->assertSame(
            '24971563792',
            $business->document
        );

        $this->assertSame(
            '33999999999',
            $business->whatsapp
        );

        $this->assertSame(
            'financeiro@climatech.test',
            $business->pix_key
        );

        $this->assertNotNull(
            $business->onboarding_completed_at
        );

        $this->assertSame(
            'free',
            $business
                ->subscriptions()
                ->with('plan')
                ->latest('id')
                ->firstOrFail()
                ->plan
                ->slug
        );
    }
}
