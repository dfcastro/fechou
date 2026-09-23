<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicQuoteVisualHierarchySourceTest extends TestCase
{
    public function test_public_quote_has_compact_commercial_hierarchy(): void
    {
        $source = file_get_contents(
            resource_path('views/pages/quotes/public.blade.php')
        );

        $this->assertStringContainsString('max-w-5xl', $source);
        $this->assertStringContainsString('Proposta aprovada', $source);
        $this->assertStringContainsString('sm:grid-cols-3', $source);
        $this->assertStringContainsString('Baixar PDF', $source);
        $this->assertStringContainsString('Documento digital via Fechou', $source);
    }
}
