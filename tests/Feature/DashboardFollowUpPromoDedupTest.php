<?php

namespace Tests\Feature;

use Tests\TestCase;

class DashboardFollowUpPromoDedupTest extends TestCase
{
    public function test_dashboard_keeps_only_compact_follow_up_promo(): void
    {
        $source = file_get_contents(
            resource_path(
                'views/pages/dashboard.blade.php'
            )
        );


        $this->assertStringContainsString(
            'Veja propostas que precisam de atenção →',
            $source
        );


        $this->assertStringNotContainsString(
            'Conhecer o Fechou Pro',
            $source
        );


        $this->assertStringContainsString(
            'Follow-up inteligente',
            $source
        );


        $this->assertStringContainsString(
            'Propostas recentes',
            $source
        );
    }
}
