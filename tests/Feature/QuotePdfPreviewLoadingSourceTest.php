<?php

namespace Tests\Feature;

use Tests\TestCase;

class QuotePdfPreviewLoadingSourceTest extends TestCase
{
    public function test_pdf_preview_uses_a_loading_screen_before_rendering_the_file(): void
    {
        $routes = file_get_contents(
            base_path('routes/web.php')
        );

        $controller = file_get_contents(
            app_path('Http/Controllers/QuotePdfController.php')
        );

        $view = file_get_contents(
            resource_path('views/pdf/preview.blade.php')
        );

        $this->assertStringContainsString(
            'quotes.pdf.preview.file',
            $routes
        );

        $this->assertStringContainsString(
            'previewFile',
            $controller
        );

        $this->assertStringContainsString(
            'Gerando sua proposta...',
            $view
        );

        $this->assertStringContainsString(
            "classList.add('pdf-ready')",
            $view
        );
    }
}
