<?php

namespace Tests\Feature;

use Tests\TestCase;

class QuoteVersioningRemovalTest
    extends TestCase
{
    public function test_formal_quote_versioning_is_not_exposed_in_v1(): void
    {
        $show = file_get_contents(
            resource_path(
                'views/pages/quotes/show.blade.php'
            )
        );

        $subscription = file_get_contents(
            resource_path(
                'views/pages/settings/subscription.blade.php'
            )
        );

        $enum = file_get_contents(
            app_path(
                'Enums/PlanFeature.php'
            )
        );

        $seeder = file_get_contents(
            database_path(
                'seeders/PlanSeeder.php'
            )
        );

        $service = file_get_contents(
            app_path(
                'Services/SubscriptionService.php'
            )
        );

        $this->assertStringNotContainsString(
            'createNewVersion',
            $show
        );

        $this->assertStringNotContainsString(
            'canUseVersioning',
            $show
        );

        $this->assertStringNotContainsString(
            'Criar nova versão',
            $show
        );

        $this->assertStringNotContainsString(
            'version_created',
            $show
        );

        $this->assertStringNotContainsString(
            'Versionamento de propostas',
            $subscription
        );

        $this->assertStringNotContainsString(
            'quote_versioning',
            $subscription
        );

        $this->assertStringNotContainsString(
            'QUOTE_VERSIONING',
            $enum
        );

        $this->assertStringNotContainsString(
            'QUOTE_VERSIONING',
            $seeder
        );

        /*
         * Cada proposta independente, inclusive uma
         * duplicação, deve consumir a cota do plano.
         */
        $this->assertStringNotContainsString(
            "->whereNull('root_quote_id')",
            $service
        );
    }
}
