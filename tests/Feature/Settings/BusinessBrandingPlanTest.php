<?php

namespace Tests\Feature\Settings;

use App\Enums\PlanFeature;
use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BusinessBrandingPlanTest extends TestCase
{
    use RefreshDatabase;

    private function createSubscription(
        Business $business,
        array $features,
        ?int $quoteLimit,
        string $slug
    ): Subscription {
        $plan = Plan::create([
            'name' => $slug === 'pro'
                ? 'Pro'
                : 'Grátis',

            'slug' => $slug,

            'description' => 'Plano de teste',

            'price' => $slug === 'pro'
                ? 29.90
                : 0,

            'billing_interval' => 'month',

            'quote_limit' => $quoteLimit,

            'features' => $features,

            'is_active' => true,

            'sort_order' => 10,
        ]);

        return Subscription::create([
            'business_id' => $business->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'current_period_starts_at' =>
                now()->startOfMonth(),
            'current_period_ends_at' =>
                now()->endOfMonth(),
        ]);
    }

    private function freeFeatures(): array
    {
        return [
            PlanFeature::CLIENT_MANAGEMENT->value,
            PlanFeature::PUBLIC_QUOTE_LINK->value,
            PlanFeature::PDF_EXPORT->value,
            PlanFeature::WHATSAPP_SHARING->value,
        ];
    }

    private function proFeatures(): array
    {
        return [
            ...$this->freeFeatures(),
            PlanFeature::QUOTE_VERSIONING->value,
            PlanFeature::FOLLOW_UP->value,
            PlanFeature::NOTIFICATIONS->value,
            PlanFeature::CUSTOM_BRANDING->value,
        ];
    }

    public function test_free_plan_sees_branding_upgrade_but_can_edit_basic_company_data(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $business = Business::factory()->create([
            'user_id' => $user->id,
            'name' => 'Empresa Antiga',
        ]);

        $this->createSubscription(
            $business,
            $this->freeFeatures(),
            5,
            'free'
        );

        $this
            ->actingAs($user)
            ->get(route('settings.business'))
            ->assertOk()
            ->assertSee('Personalização da marca')
            ->assertSee('Conhecer o Fechou Pro')
            ->assertDontSee('PNG ou JPG, até 2 MB');

        Livewire::actingAs($user)
            ->test('pages::settings.business')
            ->set('name', 'Empresa Atualizada')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(
            'Empresa Atualizada',
            $business->fresh()->name
        );
    }

    public function test_pro_plan_can_manage_company_logo(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $business = Business::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->createSubscription(
            $business,
            $this->proFeatures(),
            null,
            'pro'
        );

        $this
            ->actingAs($user)
            ->get(route('settings.business'))
            ->assertOk()
            ->assertSee('Identidade da empresa')
            ->assertSee('PNG ou JPG, até 2 MB')
            ->assertDontSee('Conhecer o Fechou Pro');

        $logo = UploadedFile::fake()
            ->image(
                'logo.png',
                300,
                300
            );

        Livewire::actingAs($user)
            ->test('pages::settings.business')
            ->set('logo', $logo)
            ->call('save')
            ->assertHasNoErrors();

        $business->refresh();

        $this->assertNotNull(
            $business->logo_path
        );

        Storage::disk('public')
            ->assertExists(
                $business->logo_path
            );
    }

    public function test_free_plan_keeps_existing_logo_stored_when_saving_basic_data(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $business = Business::factory()->create([
            'user_id' => $user->id,
            'name' => 'Empresa',
            'logo_path' =>
                'business-logos/logo-antiga.png',
        ]);

        $this->createSubscription(
            $business,
            $this->freeFeatures(),
            5,
            'free'
        );

        Livewire::actingAs($user)
            ->test('pages::settings.business')
            ->set('name', 'Empresa Nova')
            ->call('save')
            ->assertHasNoErrors();

        $business->refresh();

        $this->assertSame(
            'Empresa Nova',
            $business->name
        );

        /*
         * O downgrade não apaga o arquivo.
         * Apenas bloqueia o gerenciamento da marca.
         */
        $this->assertSame(
            'business-logos/logo-antiga.png',
            $business->logo_path
        );
    }
}
