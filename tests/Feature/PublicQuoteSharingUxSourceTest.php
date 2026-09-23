<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicQuoteSharingUxSourceTest extends TestCase
{
    public function test_public_quote_has_pdf_download_and_contextual_sharing(): void
    {
        $routes = file_get_contents(
            base_path('routes/web.php')
        );

        $controller = file_get_contents(
            app_path('Http/Controllers/QuotePdfController.php')
        );

        $show = file_get_contents(
            resource_path('views/pages/quotes/show.blade.php')
        );

        $public = file_get_contents(
            resource_path('views/pages/quotes/public.blade.php')
        );

        $this->assertStringContainsString(
            'quotes.public.pdf',
            $routes
        );

        $this->assertStringContainsString(
            'public function publicDownload(string $token)',
            $controller
        );

        $this->assertStringContainsString(
            'Preparei a proposta #{$number}',
            $show
        );

        $this->assertStringContainsString(
            'Baixar PDF',
            $public
        );

        $this->assertStringContainsString(
            'Documento digital via Fechou',
            $public
        );

        $this->assertStringContainsString(
            'no valor de R$ {$total}',
            $public
        );
    }
}
