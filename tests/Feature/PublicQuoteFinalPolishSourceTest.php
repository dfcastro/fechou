<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicQuoteFinalPolishSourceTest extends TestCase
{
    public function test_public_quote_header_and_rejected_state_are_compact(): void
    {
        $source = file_get_contents(
            resource_path('views/pages/quotes/public.blade.php')
        );

        $this->assertStringContainsString(
            'sm:grid-cols-[minmax(0,1fr)_auto]',
            $source
        );

        $this->assertStringContainsString(
            'Sua recusa foi registrada.',
            $source
        );

        $this->assertStringContainsString(
            'sm:justify-between',
            $source
        );

        $this->assertStringContainsString(
            'Documento digital via Fechou',
            $source
        );
    }
}
