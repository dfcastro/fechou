<?php

namespace Tests\Feature;

use Tests\TestCase;

class QuotePdfVisualSourceTest extends TestCase
{
    public function test_pdf_template_keeps_document_terminology_and_refined_footer(): void
    {
        $source = file_get_contents(
            resource_path('views/pdf/quote.blade.php')
        );

        $this->assertStringContainsString(
            'Orçamento',
            $source
        );

        $this->assertStringContainsString(
            'Proposta comercial para',
            $source
        );

        $this->assertStringContainsString(
            'Itens da proposta',
            $source
        );

        $this->assertStringContainsString(
            'Documento gerado pelo Negozia',
            $source
        );

        $this->assertStringNotContainsString(
            'Orçamento digital gerado pelo Negozia',
            $source
        );
    }
}
