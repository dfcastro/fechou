<?php

namespace Tests\Feature;

use Tests\TestCase;

class QuoteTextOverflowSourceTest extends TestCase
{
    public function test_quote_texts_are_protected_against_long_unbroken_content(): void
    {
        $show = file_get_contents(
            resource_path('views/pages/quotes/show.blade.php')
        );

        $create = file_get_contents(
            resource_path('views/pages/quotes/create.blade.php')
        );

        $edit = file_get_contents(
            resource_path('views/pages/quotes/edit.blade.php')
        );

        $this->assertGreaterThanOrEqual(
            2,
            substr_count($show, '[overflow-wrap:anywhere]')
        );

        $this->assertStringContainsString(
            'maxlength="5000"',
            $create
        );

        $this->assertStringContainsString(
            'maxlength="5000"',
            $edit
        );

        $publicPath = resource_path(
            'views/pages/quotes/public.blade.php'
        );

        if (file_exists($publicPath)) {
            $public = file_get_contents($publicPath);

            $this->assertStringContainsString(
                '[overflow-wrap:anywhere]',
                $public
            );
        }
    }
}
