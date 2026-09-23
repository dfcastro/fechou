<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotePipelinePaymentCollectionTest extends TestCase
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

            'payment_collection_enabled' =>
                true,

            'pix_key' =>
                'PIX-PIPELINE',
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


    public function test_pipeline_shows_active_collection_for_unpaid_quote(): void
    {
        [
            $user,
            $business,
            $client,
        ] = $this->context();


        Quote::factory()->create([
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
                    'quotes.pipeline'
                )
            )
            ->assertOk()
            ->assertSee(
                'Cobrança ativa'
            )
            ->assertSee(
                'Cobrar'
            )
            ->assertSee(
                '#cobranca',
                false
            );
    }


    public function test_pipeline_does_not_offer_collection_when_quote_opt_in_is_disabled(): void
    {
        [
            $user,
            $business,
            $client,
        ] = $this->context();


        Quote::factory()->create([
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
                false,
        ]);


        $this
            ->actingAs($user)
            ->get(
                route(
                    'quotes.pipeline'
                )
            )
            ->assertOk()
            ->assertDontSee(
                'Cobrança ativa'
            )
            ->assertDontSee(
                'data-pipeline-collect-payment',
                false
            );
    }


    public function test_quote_payment_section_has_collection_anchor(): void
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

            'payment_status' =>
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
                'id="cobranca"',
                false
            );
    }
}
