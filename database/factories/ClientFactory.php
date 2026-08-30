<?php

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => fake()->name(),
            'document' => fake()->numerify('###.###.###-##'),
            'email' => fake()->safeEmail(),
            'phone' => fake()->numerify('(##) ####-####'),
            'whatsapp' => fake()->numerify('(##) 9####-####'),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}