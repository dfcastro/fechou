<?php

namespace Tests\Feature;

use Tests\TestCase;

class QuotePdfPaginationSourceTest extends TestCase
{
    public function test_pdf_template_is_compact_and_page_break_safe(): void
    {
        $source = file_get_contents(
            resource_path('views/pdf/quote.blade.php')
        );

        $this->assertStringContainsString(
            'padding: 7px 8px;',
            $source
        );

        $this->assertStringContainsString(
            'page-break-inside: avoid;',
            $source
        );

        $this->assertStringContainsString(
            'margin-left: 6px;',
            $source
        );

        $this->assertStringContainsString(
            'ITEM TYPE INLINE PDF',
            $source
        );
    }
}
