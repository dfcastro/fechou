<?php

namespace Tests\Feature;

use Tests\TestCase;

class UiConsistencySourceTest extends TestCase
{
    public function test_internal_navigation_uses_proposal_terminology(): void
    {
        $sidebar = file_get_contents(
            resource_path('views/layouts/app/sidebar.blade.php')
        );

        $show = file_get_contents(
            resource_path('views/pages/quotes/show.blade.php')
        );

        $clients = file_get_contents(
            resource_path('views/pages/clients/index.blade.php')
        );

        $this->assertStringContainsString(
            'Propostas',
            $sidebar
        );

        $this->assertStringContainsString(
            'Voltar para propostas',
            $show
        );

        $this->assertStringContainsString(
            'Editar proposta',
            $show
        );

        $this->assertStringContainsString(
            'Itens da proposta',
            $show
        );

        $this->assertStringContainsString(
            '>Propostas</th>',
            $clients
        );
    }

    public function test_page_widths_follow_the_same_visual_system(): void
    {
        $clients = file_get_contents(
            resource_path('views/pages/clients/index.blade.php')
        );

        $business = file_get_contents(
            resource_path('views/pages/settings/business.blade.php')
        );

        $subscription = file_get_contents(
            resource_path('views/pages/settings/subscription.blade.php')
        );

        $this->assertStringContainsString(
            'mx-auto w-full max-w-7xl space-y-6',
            $clients
        );

        $this->assertStringContainsString(
            'mx-auto w-full max-w-6xl space-y-5',
            $business
        );

        $this->assertStringContainsString(
            'mx-auto w-full max-w-6xl space-y-5',
            $subscription
        );

        $followup = resource_path(
            'views/pages/settings/follow-up.blade.php'
        );

        if (file_exists($followup)) {
            $this->assertStringContainsString(
                'mx-auto w-full max-w-6xl space-y-5',
                file_get_contents($followup)
            );
        }
    }
}
