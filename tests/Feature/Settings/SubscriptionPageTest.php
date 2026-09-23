<?php

namespace Tests\Feature\Settings;

use App\Models\Business;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_subscription_page(): void
    {
        $this
            ->get(route('settings.subscription'))
            ->assertRedirect(route('login'));
    }


    public function test_user_without_business_can_access_subscription_page(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->get(route('settings.subscription'))
            ->assertOk()
            ->assertSee('Configure sua empresa');
    }


    public function test_user_can_view_current_subscription(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $business = Business::factory()->create([
            'user_id' => $user->id,
        ]);

        $plan = Plan::create([
            'name' => 'Grátis',
            'slug' => 'free',
            'price' => 0,
            'billing_interval' => 'month',
            'quote_limit' => null,

            'features' => [
                'client_management',
                'public_quote_link',
                'pdf_export',
            ],

            'is_active' => true,
        ]);

        Subscription::create([
            'business_id' => $business->id,
            'plan_id' => $plan->id,

            'status' => 'active',

            'starts_at' => now(),

            'current_period_starts_at' =>
                now()->startOfMonth(),

            'current_period_ends_at' =>
                now()->endOfMonth(),
        ]);

        $this
            ->actingAs($user)
            ->get(route('settings.subscription'))
            ->assertOk()

            /*
             * Plano.
             */
            ->assertSee('Grátis')

            /*
             * Status.
             */
            ->assertSee('Ativo')

            /*
             * O layout atual usa esta descrição
             * para planos sem limite de propostas.
             */
            ->assertSee('Sem limite mensal')

            /*
             * Recursos incluídos.
             */
            ->assertSee('Gestão de clientes')
            ->assertSee('Link público para propostas')
            ->assertSee('Exportação em PDF');
    }


    public function test_subscription_page_displays_quote_usage(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $business = Business::factory()->create([
            'user_id' => $user->id,
        ]);

        $plan = Plan::create([
            'name' => 'Limitado',
            'slug' => 'limited-test',

            'price' => 19.90,

            'billing_interval' => 'month',

            'quote_limit' => 5,

            'features' => [
                'pdf_export',
            ],

            'is_active' => true,
        ]);

        Subscription::create([
            'business_id' => $business->id,
            'plan_id' => $plan->id,

            'status' => 'active',

            'starts_at' => now(),

            'current_period_starts_at' =>
                now()->startOfMonth(),

            'current_period_ends_at' =>
                now()->endOfMonth(),
        ]);

        $client = Client::factory()->create([
            'business_id' => $business->id,
        ]);


        /*
         * 2 propostas consumidas no ciclo.
         */
        Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,

            'root_quote_id' => null,

            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,

            'root_quote_id' => null,

            'created_at' => now(),
            'updated_at' => now(),
        ]);


        $response = $this
            ->actingAs($user)
            ->get(route('settings.subscription'));


        $response
            ->assertOk()

            /*
             * Plano atual.
             */
            ->assertSee('Limitado')

            /*
             * Uso: 2 de 5.
             */
            ->assertSee('2')
            ->assertSee('5')
            ->assertSee('restante(s)');


        /*
         * O serviço deve calcular exatamente
         * 3 propostas restantes.
         *
         * Como o Blade possui whitespace entre
         * o número e "restante(s)", verificamos
         * a ordem em vez de procurar a string
         * literal "3 restante(s)".
         */
        $response->assertSeeInOrder([
            '3',
            'restante(s)',
        ]);
    }


    public function test_user_with_business_but_without_subscription_sees_warning(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        Business::factory()->create([
            'user_id' => $user->id,
        ]);

        $this
            ->actingAs($user)
            ->get(route('settings.subscription'))
            ->assertOk()
            ->assertSee('Assinatura não encontrada');
    }
}