<?php

namespace Tests\Feature;

use Tests\TestCase;

class QuoteRejectionFeedbackInternalSourceTest extends TestCase
{
    public function test_internal_quote_shows_rejection_feedback(): void
    {
        $source = file_get_contents(
            resource_path(
                'views/pages/quotes/show.blade.php'
            )
        );

        $this->assertStringContainsString(
            'FEEDBACK DA RECUSA',
            $source
        );

        $this->assertStringContainsString(
            "status === 'rejected'",
            $source
        );

        $this->assertStringContainsString(
            "'rejection_reason_label'",
            $source
        );

        $this->assertStringContainsString(
            "'rejection_comment'",
            $source
        );

        $this->assertStringContainsString(
            'Proposta recusada',
            $source
        );

        $this->assertStringContainsString(
            'Comentário do cliente',
            $source
        );

        $this->assertStringContainsString(
            'sem informar um motivo',
            $source
        );
    }
}
