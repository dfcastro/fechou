<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuoteDuplicationTest extends TestCase
{
    use RefreshDatabase;


    private function context(): array
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


        $client =
            Client::factory()->create([
                'business_id' =>
                    $business->id,
            ]);


        return [
            $user,
            $business,
            $client,
        ];
    }


    public function test_existing_quote_can_prefill_independent_duplicate(): void
    {
        [
            $user,
            $business,
            $client,
        ] = $this->context();


        $createdAt =
            now()
                ->subDays(3)
                ->startOfDay();


        $quote =
            Quote::factory()->create([
                'business_id' =>
                    $business->id,

                'client_id' =>
                    $client->id,

                'number' =>
                    18,

                'title' =>
                    'Instalação de ar condicionado',

                'description' =>
                    'Instalação completa.',

                'discount' =>
                    40,

                'notes' =>
                    'Tubulação inclusa.',

                'status' =>
                    'accepted',

                'payment_status' =>
                    'paid',

                'execution_status' =>
                    'completed',

                'valid_until' =>
                    $createdAt
                        ->copy()
                        ->addDays(15),

                'created_at' =>
                    $createdAt,

                'updated_at' =>
                    $createdAt,
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
                    500,

                'total' =>
                    1000,

                'sort_order' =>
                    0,
            ]);


        $component =
            Livewire::actingAs($user)
                ->withQueryParams([
                    'duplicar' =>
                        $quote->id,
                ])
                ->test(
                    'pages::quotes.create'
                );


        $component
            ->assertSet(
                'duplicateSourceNumber',
                '0018'
            )
            ->assertSet(
                'clientId',
                null
            )
            ->assertSet(
                'clientSearch',
                ''
            )
            ->assertSet(
                'title',
                'Instalação de ar condicionado'
            )
            ->assertSet(
                'description',
                'Instalação completa.'
            )
            ->assertSet(
                'discount',
                '40.00'
            )
            ->assertSet(
                'notes',
                'Tubulação inclusa.'
            )
            ->assertSet(
                'selectedTemplateId',
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


        /*
         * Apenas abrir a duplicação não cria
         * uma nova proposta no banco.
         */
        $this->assertDatabaseCount(
            'quotes',
            1
        );


        /*
         * A validade mantém a duração
         * original de 15 dias.
         */
        $this->assertSame(
            now()
                ->addDays(15)
                ->format('Y-m-d'),
            $component->get(
                'validUntil'
            )
        );
    }


    public function test_duplicate_source_must_belong_to_logged_business(): void
    {
        [
            $user,
        ] = $this->context();


        $otherUser =
            User::factory()->create();


        $otherBusiness =
            Business::factory()->create([
                'user_id' =>
                    $otherUser->id,

                'onboarding_completed_at' =>
                    now(),
            ]);


        $otherClient =
            Client::factory()->create([
                'business_id' =>
                    $otherBusiness->id,
            ]);


        $quote =
            Quote::factory()->create([
                'business_id' =>
                    $otherBusiness->id,

                'client_id' =>
                    $otherClient->id,
            ]);


        $this
            ->actingAs($user)
            ->get(
                route(
                    'quotes.create',
                    [
                        'duplicar' =>
                            $quote->id,
                    ]
                )
            )
            ->assertNotFound();
    }
}
