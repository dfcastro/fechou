<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BrazilianDocumentValidationTest
    extends TestCase
{
    use RefreshDatabase;

    private function account(): array
    {
        $user =
            User::factory()->create([
                'email_verified_at' =>
                    now(),
            ]);

        $business =
            Business::factory()->create([
                'user_id' =>
                    $user->id,

                'onboarding_completed_at' =>
                    now(),
            ]);

        $plan =
            Plan::create([
                'name' => 'Grátis',
                'slug' => 'free',
                'description' =>
                    'Plano gratuito',
                'price' => 0,
                'billing_interval' =>
                    'month',
                'quote_limit' => 5,
                'features' => [],
                'is_active' => true,
                'sort_order' => 10,
            ]);

        Subscription::create([
            'business_id' =>
                $business->id,

            'plan_id' =>
                $plan->id,

            'status' =>
                'active',

            'starts_at' =>
                now(),

            'current_period_starts_at' =>
                now()->startOfMonth(),

            'current_period_ends_at' =>
                now()->endOfMonth(),
        ]);

        return [
            $user,
            $business,
        ];
    }

    public function test_quick_client_rejects_invalid_document(): void
    {
        [
            $user,
            $business,
        ] = $this->account();

        Livewire::actingAs($user)
            ->test(
                'pages::quotes.create'
            )
            ->set(
                'newClientName',
                'Cliente inválido'
            )
            ->set(
                'newClientDocument',
                '10706.616/6660-0'
            )
            ->call(
                'saveNewClient'
            )
            ->assertHasErrors([
                'newClientDocument',
            ])
            ->assertSet(
                'newClientDocument',
                '10706.616/6660-0'
            );

        $this->assertDatabaseMissing(
            'clients',
            [
                'business_id' =>
                    $business->id,

                'name' =>
                    'Cliente inválido',
            ]
        );
    }

    public function test_quick_client_accepts_alphanumeric_cnpj(): void
    {
        [
            $user,
            $business,
        ] = $this->account();

        Livewire::actingAs($user)
            ->test(
                'pages::quotes.create'
            )
            ->set(
                'newClientName',
                'Cliente CNPJ Alfa'
            )
            ->set(
                'newClientDocument',
                '00.000.000/E08G-12'
            )
            ->call(
                'saveNewClient'
            )
            ->assertHasNoErrors();

        $this->assertDatabaseHas(
            'clients',
            [
                'business_id' =>
                    $business->id,

                'name' =>
                    'Cliente CNPJ Alfa',

                'document' =>
                    '00000000E08G12',
            ]
        );
    }

    public function test_client_form_rejects_duplicate_document_in_same_business(): void
    {
        [
            $user,
            $business,
        ] = $this->account();

        \App\Models\Client::factory()->create([
            'business_id' =>
                $business->id,

            'name' =>
                'Cliente original',

            'document' =>
                '00000000E08G12',
        ]);

        Livewire::actingAs($user)
            ->test(
                'pages::clients.index'
            )
            ->call('create')
            ->set(
                'name',
                'Cliente duplicado'
            )
            ->set(
                'document',
                '00.000.000/E08G-12'
            )
            ->call('save')
            ->assertHasErrors([
                'document',
            ])
            ->assertSee(
                'Já existe um cliente cadastrado com este CPF/CNPJ.'
            );

        $this->assertDatabaseMissing(
            'clients',
            [
                'business_id' =>
                    $business->id,

                'name' =>
                    'Cliente duplicado',
            ]
        );
    }

    public function test_client_can_keep_own_document_when_editing(): void
    {
        [
            $user,
            $business,
        ] = $this->account();

        $client =
            \App\Models\Client::factory()->create([
                'business_id' =>
                    $business->id,

                'name' =>
                    'Cliente original',

                'document' =>
                    '00000000E08G12',
            ]);

        Livewire::actingAs($user)
            ->test(
                'pages::clients.index'
            )
            ->call(
                'edit',
                $client->id
            )
            ->assertSet(
                'document',
                '00.000.000/E08G-12'
            )
            ->set(
                'name',
                'Cliente atualizado'
            )
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas(
            'clients',
            [
                'id' =>
                    $client->id,

                'name' =>
                    'Cliente atualizado',

                'document' =>
                    '00000000E08G12',
            ]
        );
    }


    public function test_client_form_keeps_document_mask_after_validation_error(): void
    {
        [
            $user,
            $business,
        ] = $this->account();

        Livewire::actingAs($user)
            ->test(
                'pages::clients.index'
            )
            ->call('create')
            ->set(
                'name',
                'Cliente máscara'
            )
            ->set(
                'document',
                '10706.616/6660-0'
            )
            ->call('save')
            ->assertHasErrors([
                'document',
            ])
            ->assertSet(
                'document',
                '10706.616/6660-0'
            )
            ->assertSee(
                'Informe um CPF ou CNPJ válido.'
            );

        $this->assertDatabaseMissing(
            'clients',
            [
                'business_id' =>
                    $business->id,

                'name' =>
                    'Cliente máscara',
            ]
        );
    }
}
