<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Business>
 */
class BusinessFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->company(),
            'document' => fake()->numerify('##.###.###/####-##'),
            'email' => fake()->companyEmail(),
            'phone' => fake()->numerify('(##) ####-####'),
            'whatsapp' => fake()->numerify('(##) 9####-####'),
            'city' => fake()->city(),
            'state' => 'MG',
            'postal_code' => fake()->numerify('#####-###'),
            'pix_key' => fake()->email(),
        ];
    }
}