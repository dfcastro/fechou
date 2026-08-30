<?php

namespace Database\Factories;

use App\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\QuoteItem>
 */
class QuoteItemFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->randomElement([1, 1, 1, 2, 3]);
        $unitPrice = fake()->randomFloat(2, 50, 800);

        return [
            'quote_id' => Quote::factory(),

            'type' => fake()->randomElement([
                'service',
                'service',
                'material',
            ]),

            'description' => fake()->randomElement([
                'Mão de obra',
                'Instalação',
                'Limpeza e higienização',
                'Tubulação de cobre',
                'Suporte para condensadora',
                'Carga de gás refrigerante',
                'Deslocamento técnico',
            ]),

            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total' => $quantity * $unitPrice,

            'sort_order' => 0,
        ];
    }
}