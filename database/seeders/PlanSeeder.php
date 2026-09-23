<?php

namespace Database\Seeders;

use App\Enums\PlanFeature;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Plano Grátis
        |--------------------------------------------------------------------------
        */

        Plan::updateOrCreate(
            [
                'slug' => 'free',
            ],
            [
                'name' => 'Grátis',

                'description' =>
                    'Para começar a criar e enviar propostas profissionais.',

                'price' => 0,

                'billing_interval' => 'month',

                /*
                 * 5 novas propostas raiz por ciclo.
                 *
                 * Versões de uma proposta existente
                 * não consumirão uma nova unidade.
                 */
                'quote_limit' => 5,

                'features' => [
                    PlanFeature::CLIENT_MANAGEMENT->value,
                    PlanFeature::PUBLIC_QUOTE_LINK->value,
                    PlanFeature::PDF_EXPORT->value,
                    PlanFeature::WHATSAPP_SHARING->value,
                ],

                'is_active' => true,

                'sort_order' => 10,
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Plano Pro
        |--------------------------------------------------------------------------
        */

        Plan::updateOrCreate(
            [
                'slug' => 'pro',
            ],
            [
                'name' => 'Pro',

                'description' =>
                    'Para profissionais que querem vender mais e acompanhar cada proposta.',

                'price' => 29.90,

                'billing_interval' => 'month',

                /*
                 * null = propostas ilimitadas.
                 */
                'quote_limit' => null,

                'features' => [
                        /*
                         * Recursos do Grátis.
                         */
                    PlanFeature::CLIENT_MANAGEMENT->value,
                    PlanFeature::PUBLIC_QUOTE_LINK->value,
                    PlanFeature::PDF_EXPORT->value,
                    PlanFeature::WHATSAPP_SHARING->value,

                        /*
                         * Recursos Pro.
                         */
                    PlanFeature::QUOTE_VERSIONING->value,
                    PlanFeature::FOLLOW_UP->value,
                    PlanFeature::NOTIFICATIONS->value,
                    PlanFeature::CUSTOM_BRANDING->value,
                ],

                'is_active' => true,

                'sort_order' => 20,
            ]
        );
    }
}