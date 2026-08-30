<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Quote>
 */
class QuoteFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 150, 5000);
        $discount = fake()->randomElement([0, 0, 0, 50, 100]);

        return [
            'business_id' => Business::factory(),
            'client_id' => Client::factory(),

            'number' => fake()->unique()->numberBetween(1, 999999),

            'public_token' => Str::random(48),

            'title' => fake()->randomElement([
                'Instalação de ar-condicionado',
                'Manutenção preventiva',
                'Limpeza de equipamento',
                'Manutenção corretiva',
                'Instalação e fornecimento de materiais',
            ]),

            'description' => fake()->optional()->sentence(),

            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $subtotal - $discount,

            'status' => 'draft',

            'valid_until' => now()->addDays(7),

            'notes' => fake()->optional()->sentence(),
        ];
    }
}