<?php

namespace Tests\Feature;

use Tests\TestCase;

class QuickClientNormalizationSourceTest extends TestCase
{
    public function test_quick_client_normalization_uses_the_correct_properties(): void
    {
        foreach ([
            'pages/quotes/create.blade.php',
            'pages/quotes/edit.blade.php',
        ] as $file) {
            $source = file_get_contents(
                resource_path('views/' . $file)
            );

            $start = strpos(
                $source,
                'public function saveNewClient(): void'
            );

            $this->assertNotFalse(
                $start,
                'saveNewClient() não encontrado em ' . $file
            );

            $end = strpos(
                $source,
                '$validated = $this->validate(',
                $start
            );

            $this->assertNotFalse(
                $end,
                'validate() não encontrado em ' . $file
            );

            $prefix = substr(
                $source,
                $start,
                $end - $start
            );

            $this->assertStringContainsString(
                '$this->newClientDocument',
                $prefix
            );

            $this->assertStringContainsString(
                '$this->newClientWhatsapp',
                $prefix
            );

            $this->assertStringNotContainsString(
                '$this->document',
                $prefix
            );

            $this->assertStringNotContainsString(
                '$this->phone',
                $prefix
            );

            $this->assertStringNotContainsString(
                '$this->whatsapp',
                $prefix
            );
        }
    }
}
