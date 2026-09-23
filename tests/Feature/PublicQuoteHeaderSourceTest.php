<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicQuoteHeaderSourceTest extends TestCase
{
    public function test_public_header_groups_business_and_quote_actions(): void
    {
        $source = file_get_contents(
            resource_path('views/pages/quotes/public.blade.php')
        );

        $this->assertStringContainsString(
            'grid max-w-5xl',
            $source
        );

        $this->assertStringContainsString(
            'flex min-w-0 items-center gap-3',
            $source
        );

        $this->assertStringContainsString(
            'PROPOSTA / PDF',
            $source
        );

        $this->assertStringContainsString(
            'Baixar PDF',
            $source
        );
    }
}
