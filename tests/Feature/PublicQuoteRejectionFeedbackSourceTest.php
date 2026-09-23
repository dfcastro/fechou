<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicQuoteRejectionFeedbackSourceTest extends TestCase
{
    public function test_public_rejection_collects_optional_feedback(): void
    {
        $source = file_get_contents(
            resource_path(
                'views/pages/quotes/public.blade.php'
            )
        );

        $this->assertStringContainsString(
            "public string \$rejectReason = '';",
            $source
        );

        $this->assertStringContainsString(
            "public string \$rejectComment = '';",
            $source
        );

        $this->assertStringContainsString(
            "'rejection_reason' => \$reason",
            $source
        );

        $this->assertStringContainsString(
            "'rejection_comment' =>",
            $source
        );

        $this->assertStringContainsString(
            'O que pesou na decisão?',
            $source
        );

        $this->assertStringContainsString(
            'wire:model="rejectReason"',
            $source
        );

        $this->assertStringContainsString(
            'wire:model="rejectComment"',
            $source
        );

        $this->assertStringContainsString(
            'Essa informação é opcional.',
            $source
        );
    }
}
