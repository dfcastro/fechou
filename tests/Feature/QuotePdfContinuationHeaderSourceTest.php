<?php

namespace Tests\Feature;

use Tests\TestCase;

class QuotePdfContinuationHeaderSourceTest extends TestCase
{
    public function test_pdf_controller_has_continuation_header_for_later_pages(): void
    {
        $source = file_get_contents(
            app_path('Http/Controllers/QuotePdfController.php')
        );

        $this->assertStringContainsString(
            'ORÇAMENTO • CONTINUAÇÃO',
            $source
        );

        $this->assertStringContainsString(
            '$pageNumber <= 1',
            $source
        );

        $this->assertStringContainsString(
            'page_script',
            $source
        );

        $this->assertStringContainsString(
            'runningUnitTests',
            $source
        );
    }
}
