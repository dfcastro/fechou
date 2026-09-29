<?php

namespace Tests\Feature;

use Tests\TestCase;

class CommercialReportResponsiveSourceTest extends TestCase
{
    public function test_monthly_summary_has_mobile_cards_and_desktop_table(): void
    {
        $source = file_get_contents(
            resource_path(
                'views/pages/reports/commercial.blade.php'
            )
        );

        $this->assertStringContainsString(
            'RESUMO MENSAL MOBILE',
            $source
        );

        $this->assertStringContainsString(
            'RESUMO MENSAL DESKTOP',
            $source
        );

        $this->assertStringContainsString(
            'report-mobile-list',
            $source
        );

        $this->assertStringContainsString(
            'report-desktop-table',
            $source
        );

        $this->assertStringContainsString(
            "@media (min-width: 768px)",
            $source
        );

        $this->assertStringContainsString(
            "\$month['conversion']",
            $source
        );

        $this->assertStringContainsString(
            "\$month['received']",
            $source
        );

        $this->assertStringContainsString(
            "\$month['open']",
            $source
        );
    }
}
