<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuotePaymentCollectionTest extends TestCase
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


        $client = Client::factory()->create([
            'business_id' =>
                $business->id,

            'whatsapp' =>
                '33999998888',
        ]);


        return [
            $user,
            $business,
            $client,
        ];
    }


    public function test_payment_collection_is_optional_by_default(): void
    {
        [
            $user,
            $business,
            $client,
        ] = $this->context();


        $quote = Quote::factory()->create([
            'business_id' =>
                $business->id,

            'client_id' =>
                $client->id,

            'status' =>
                'accepted',
        ]);


        $this->assertFalse(
            (bool)
                $business
                    ->fresh()
                    ->payment_collection_enabled
        );


        $this->assertFalse(
            (bool)
                $quote
                    ->fresh()
                    ->payment_collection_enabled
        );
    }


    public function test_business_can_enable_payment_collection(): void
    {
        [
            $user,
            $business,
        ] = $this->context();


        Livewire::actingAs(
            $user
        )
            ->test(
                'pages::settings.payment'
            )
            ->set(
                'paymentCollectionEnabled',
                true
            )
            ->set(
                'pixKey',
                'pix-teste-fechou'
            )
            ->set(
                'paymentInstructions',
                'Enviar comprovante após o pagamento.'
            )
            ->call(
                'save'
            )
            ->assertHasNoErrors();


        $business->refresh();


        $this->assertTrue(
            $business
                ->payment_collection_enabled
        );

        $this->assertSame(
            'pix-teste-fechou',
            $business->pix_key
        );

        $this->assertSame(
            'Enviar comprovante após o pagamento.',
            $business->payment_instructions
        );
    }


    public function test_accepted_quote_can_show_payment_collection_card(): void
    {
        [
            $user,
            $business,
            $client,
        ] = $this->context();


        $business->update([
            'payment_collection_enabled' =>
                true,

            'pix_key' =>
                'PIX-TESTE',

            'payment_instructions' =>
                'Envie o comprovante.',
        ]);


        $quote = Quote::factory()->create([
            'business_id' =>
                $business->id,

            'client_id' =>
                $client->id,

            'status' =>
                'accepted',

            'payment_status' =>
                'pending',

            'execution_status' =>
                'pending',

            'payment_collection_enabled' =>
                true,
        ]);


        $this
            ->actingAs($user)
            ->get(
                route(
                    'quotes.show',
                    $quote
                )
            )
            ->assertOk()
            ->assertSee(
                'Cobrança'
            )
            ->assertSee(
                'PIX-TESTE'
            )
            ->assertSee(
                'Enviar lembrete no WhatsApp'
            )
            ->assertSee(
                'Desativar nesta proposta'
            );
    }
}
