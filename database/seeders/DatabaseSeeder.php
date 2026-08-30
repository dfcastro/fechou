<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteEvent;
use App\Models\QuoteItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Usuário de demonstração
        |--------------------------------------------------------------------------
        */

        $user = User::factory()->create([
            'name' => 'Daniel Demo',
            'email' => 'demo@fechou.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Empresa
        |--------------------------------------------------------------------------
        */

        $business = Business::factory()->create([
            'user_id' => $user->id,
            'name' => 'ClimaTech Refrigeração',
            'document' => '12.345.678/0001-90',
            'email' => 'contato@climatech.com.br',
            'phone' => '(33) 3333-4444',
            'whatsapp' => '(33) 99999-8888',
            'city' => 'Almenara',
            'state' => 'MG',
            'postal_code' => '39900-000',
            'pix_key' => 'contato@climatech.com.br',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Clientes
        |--------------------------------------------------------------------------
        */

        $clients = Client::factory()
            ->count(8)
            ->create([
                'business_id' => $business->id,
            ]);

        /*
        |--------------------------------------------------------------------------
        | Orçamentos
        |--------------------------------------------------------------------------
        */

        $statuses = [
            'draft',
            'sent',
            'sent',
            'viewed',
            'viewed',
            'accepted',
            'accepted',
            'rejected',
            'viewed',
            'accepted',
        ];

        foreach ($statuses as $index => $status) {

            $client = $clients->random();

            $quote = Quote::factory()->create([
                'business_id' => $business->id,
                'client_id' => $client->id,

                'number' => $index + 1,
                'status' => $status,

                'sent_at' => in_array($status, [
                    'sent',
                    'viewed',
                    'accepted',
                    'rejected',
                ])
                    ? now()->subDays(rand(1, 7))
                    : null,

                'first_viewed_at' => in_array($status, [
                    'viewed',
                    'accepted',
                    'rejected',
                ])
                    ? now()->subDays(rand(0, 5))
                    : null,

                'accepted_at' => $status === 'accepted'
                    ? now()->subDays(rand(0, 3))
                    : null,

                'rejected_at' => $status === 'rejected'
                    ? now()->subDays(rand(0, 3))
                    : null,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Itens
            |--------------------------------------------------------------------------
            */

            QuoteItem::factory()
                ->count(rand(2, 5))
                ->create([
                    'quote_id' => $quote->id,
                ]);

            /*
            |--------------------------------------------------------------------------
            | Recalcula valores reais
            |--------------------------------------------------------------------------
            */

            $subtotal = $quote->items()->sum('total');

            $discount = rand(0, 1)
                ? 0
                : min(50, $subtotal);

            $quote->update([
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $subtotal - $discount,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Eventos
            |--------------------------------------------------------------------------
            */

            QuoteEvent::create([
                'quote_id' => $quote->id,
                'type' => 'created',
            ]);

            if ($quote->sent_at) {
                QuoteEvent::create([
                    'quote_id' => $quote->id,
                    'type' => 'sent',
                    'created_at' => $quote->sent_at,
                    'updated_at' => $quote->sent_at,
                ]);
            }

            if ($quote->first_viewed_at) {
                QuoteEvent::create([
                    'quote_id' => $quote->id,
                    'type' => 'viewed',
                    'ip_address' => fake()->ipv4(),
                    'user_agent' => fake()->userAgent(),
                    'created_at' => $quote->first_viewed_at,
                    'updated_at' => $quote->first_viewed_at,
                ]);
            }

            if ($quote->accepted_at) {
                QuoteEvent::create([
                    'quote_id' => $quote->id,
                    'type' => 'accepted',
                    'created_at' => $quote->accepted_at,
                    'updated_at' => $quote->accepted_at,
                ]);
            }

            if ($quote->rejected_at) {
                QuoteEvent::create([
                    'quote_id' => $quote->id,
                    'type' => 'rejected',
                    'created_at' => $quote->rejected_at,
                    'updated_at' => $quote->rejected_at,
                ]);
            }
        }
    }
}