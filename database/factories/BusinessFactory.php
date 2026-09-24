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
            'document' => $this->validCnpj(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->numerify('(##) ####-####'),
            'whatsapp' => fake()->numerify('(##) 9####-####'),
            'city' => fake()->city(),
            'state' => 'MG',
            'postal_code' => fake()->numerify('#####-###'),
            'pix_key' => fake()->email(),
        ];
    }

    private function validCnpj(): string
    {
        /*
         * Gera os 12 caracteres-base e calcula
         * os dois dígitos verificadores.
         *
         * O factory precisa produzir documentos
         * válidos porque os formulários da empresa
         * validam CPF/CNPJ ao salvar.
         */
        $base = '';

        for ($i = 0; $i < 12; $i++) {
            $base .= (string) fake()->numberBetween(
                0,
                9
            );
        }

        $calculateDigit = function (
            string $value,
            array $weights
        ): int {
            $sum = 0;

            foreach ($weights as $index => $weight) {
                $sum +=
                    ((int) $value[$index])
                    * $weight;
            }

            $remainder = $sum % 11;

            return $remainder < 2
                ? 0
                : 11 - $remainder;
        };

        $firstDigit = $calculateDigit(
            $base,
            [
                5, 4, 3, 2,
                9, 8, 7, 6,
                5, 4, 3, 2,
            ]
        );

        $withFirstDigit =
            $base . $firstDigit;

        $secondDigit = $calculateDigit(
            $withFirstDigit,
            [
                6, 5, 4, 3, 2,
                9, 8, 7, 6,
                5, 4, 3, 2,
            ]
        );

        return
            $withFirstDigit
            . $secondDigit;
    }

}