<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotePaymentReminderTimelineTest extends TestCase
{
    use RefreshDatabase;


    public function test_payment_reminder_appears_correctly_in_timeline(): void
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
                'PIX-TESTE',
        ]);


        $client = Client::factory()->create([
            'business_id' =>
                $business->id,

            'whatsapp' =>
                '33999998888',
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


        $quote
            ->events()
            ->create([
                'type' =>
                    'payment_reminder_sent',

                'metadata' => [
                    'channel' =>
                        'whatsapp',

                    'origin' =>
                        'test',
                ],
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
                'Lembrete de pagamento enviado'
            )
            ->assertSee(
                'Pagamento'
            );
    }


    public function test_payment_reminder_has_payment_phase_mapping(): void
    {
        $source = file_get_contents(
            resource_path(
                'views/pages/quotes/show.blade.php'
            )
        );


        $this->assertStringContainsString(
            "'payment_reminder_sent' =>",
            $source
        );


        $this->assertStringContainsString(
            "'Lembrete de pagamento enviado'",
            $source
        );


        $this->assertStringContainsString(
            "'bg-amber-50 text-amber-700 '",
            $source
        );
    }
}
