<?php

namespace Database\Factories;

use App\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\QuoteEvent>
 */
class QuoteEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'quote_id' => Quote::factory(),

            'type' => fake()->randomElement([
                'created',
                'sent',
                'viewed',
            ]),

            'ip_address' => fake()->ipv4(),

            'user_agent' => fake()->userAgent(),

            'metadata' => null,
        ];
    }
}