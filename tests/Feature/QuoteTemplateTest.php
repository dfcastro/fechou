<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Client;
use App\Models\QuoteTemplate;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuoteTemplateTest extends TestCase
{
    use RefreshDatabase;


    private function context(): array
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);


        $business = Business::factory()->create([
            'user_id' =>
                $user->id,

            'onboarding_completed_at' =>
                now(),
        ]);


        return [
            $user,
            $business,
        ];
    }


    public function test_business_can_create_quote_template_with_items(): void
    {
        [
            $user,
            $business,
        ] = $this->context();


        Livewire::actingAs($user)
            ->test(
                'pages::quote-templates.index'
            )
            ->set(
                'name',
                'Manutenção preventiva'
            )
            ->set(
                'title',
                'Serviço de manutenção preventiva'
            )
            ->set(
                'description',
                'Descrição padrão.'
            )
            ->set(
                'validityDays',
                '10'
            )
            ->set(
                'discount',
                '20'
            )
            ->set(
                'notes',
                'Observação padrão.'
            )
            ->set(
                'items',
                [
                    [
                        'type' => 'service',
                        'description' =>
                            'Manutenção',

                        'quantity' => 1,
                        'unit' => 'serviço',
                        'unit_price' => 350,
                    ],
                ]
            )
            ->call(
                'saveTemplate'
            )
            ->assertHasNoErrors();


        $template =
            QuoteTemplate::query()
                ->where(
                    'business_id',
                    $business->id
                )
                ->firstOrFail();


        $this->assertSame(
            'Manutenção preventiva',
            $template->name
        );

        $this->assertSame(
            10,
            $template->validity_days
        );

        $this->assertCount(
            1,
            $template->items
        );

        $this->assertSame(
            'Manutenção',
            $template
                ->items
                ->first()
                ->description
        );
    }


    public function test_template_can_fill_new_quote_without_selecting_client(): void
    {
        [
            $user,
            $business,
        ] = $this->context();


        $template =
            QuoteTemplate::query()
                ->create([
                    'business_id' =>
                        $business->id,

                    'name' =>
                        'Site institucional',

                    'title' =>
                        'Desenvolvimento de site',

                    'description' =>
                        'Criação de site institucional.',

                    'validity_days' =>
                        15,

                    'discount' =>
                        50,

                    'notes' =>
                        'Hospedagem não inclusa.',
                ]);


        $template
            ->items()
            ->create([
                'type' =>
                    'service',

                'description' =>
                    'Desenvolvimento',

                'quantity' =>
                    1,

                'unit' =>
                    'serviço',

                'unit_price' =>
                    2500,

                'sort_order' =>
                    0,
            ]);


        $component =
            Livewire::actingAs($user)
                ->test(
                    'pages::quotes.create'
                )
                ->set(
                    'selectedTemplateId',
                    $template->id
                )
                ->call(
                    'applySelectedTemplate'
                )
                ->assertSet(
                    'title',
                    'Desenvolvimento de site'
                )
                ->assertSet(
                    'description',
                    'Criação de site institucional.'
                )
                ->assertSet(
                    'discount',
                    '50.00'
                )
                ->assertSet(
                    'clientId',
                    null
                );


        $items =
            $component->get('items');


        $this->assertCount(
            1,
            $items
        );

        $this->assertSame(
            'Desenvolvimento',
            $items[0]['description']
        );

        $this->assertSame(
            2500.0,
            $items[0]['unit_price']
        );
    }


    public function test_template_link_can_open_new_quote_already_applied(): void
    {
        [
            $user,
            $business,
        ] = $this->context();


        $template =
            QuoteTemplate::query()
                ->create([
                    'business_id' =>
                        $business->id,

                    'name' =>
                        'Instalação de ar condicionado',

                    'title' =>
                        'Serviço de ar condicionado',

                    'description' =>
                        'Instalação completa.',

                    'validity_days' =>
                        30,

                    'discount' =>
                        25,

                    'notes' =>
                        'Modelo direto.',
                ]);


        $template
            ->items()
            ->create([
                'type' =>
                    'service',

                'description' =>
                    'Instalação',

                'quantity' =>
                    1,

                'unit' =>
                    'serviço',

                'unit_price' =>
                    500,

                'sort_order' =>
                    0,
            ]);


        $component =
            Livewire::actingAs($user)
                ->withQueryParams([
                    'modelo' =>
                        $template->id,
                ])
                ->test(
                    'pages::quotes.create'
                );


        $component
            ->assertSet(
                'selectedTemplateId',
                $template->id
            )
            ->assertSet(
                'appliedTemplateName',
                'Instalação de ar condicionado'
            )
            ->assertSet(
                'title',
                'Serviço de ar condicionado'
            )
            ->assertSet(
                'description',
                'Instalação completa.'
            )
            ->assertSet(
                'discount',
                '25.00'
            )
            ->assertSet(
                'notes',
                'Modelo direto.'
            )
            ->assertSet(
                'clientId',
                null
            );


        $items =
            $component->get(
                'items'
            );


        $this->assertCount(
            1,
            $items
        );


        $this->assertSame(
            'Instalação',
            $items[0]['description']
        );


        $this->assertSame(
            500.0,
            $items[0]['unit_price']
        );
    }



    public function test_existing_quote_can_prefill_new_template(): void
    {
        [
            $user,
            $business,
        ] = $this->context();

        $client =
            Client::factory()->create([
                'business_id' =>
                    $business->id,
            ]);

        $quote =
            Quote::factory()->create([
                'business_id' =>
                    $business->id,

                'client_id' =>
                    $client->id,

                'number' =>
                    27,

                'title' =>
                    'Instalação elétrica',

                'description' =>
                    'Execução completa da instalação.',

                'discount' =>
                    35,

                'notes' =>
                    'Material conforme necessidade.',

                'valid_until' =>
                    now()->addDays(20),
            ]);

        $quote
            ->items()
            ->create([
                'type' =>
                    'service',

                'description' =>
                    'Instalação',

                'quantity' =>
                    2,

                'unit' =>
                    'serviço',

                'unit_price' =>
                    450,

                'total' =>
                    900,

                'sort_order' =>
                    0,
            ]);

        $component =
            Livewire::actingAs($user)
                ->withQueryParams([
                    'proposta' =>
                        $quote->id,
                ])
                ->test(
                    'pages::quote-templates.index'
                );

        $component
            ->assertSet(
                'showForm',
                true
            )
            ->assertSet(
                'editingTemplateId',
                null
            )
            ->assertSet(
                'name',
                'Instalação elétrica'
            )
            ->assertSet(
                'title',
                'Instalação elétrica'
            )
            ->assertSet(
                'description',
                'Execução completa da instalação.'
            )
            ->assertSet(
                'discount',
                '35.00'
            )
            ->assertSet(
                'notes',
                'Material conforme necessidade.'
            )
            ->assertSet(
                'sourceQuoteNumber',
                '0027'
            );

        $items =
            $component->get(
                'items'
            );

        $this->assertCount(
            1,
            $items
        );

        $this->assertSame(
            'Instalação',
            $items[0]['description']
        );

        $this->assertSame(
            450.0,
            $items[0]['unit_price']
        );

        /*
         * Abrir "Salvar como modelo"
         * não pode criar nada automaticamente.
         */
        $this->assertDatabaseCount(
            'quote_templates',
            0
        );
    }

}
