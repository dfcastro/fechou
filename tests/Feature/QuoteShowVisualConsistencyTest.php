<?php

namespace Tests\Feature;

use Tests\TestCase;

class QuoteShowVisualConsistencyTest extends TestCase
{
    public function test_quote_detail_uses_the_new_visual_hierarchy(): void
    {
        $source = file_get_contents(
            resource_path('views/pages/quotes/show.blade.php')
        );

        $this->assertStringContainsString('Voltar para propostas', $source);
        $this->assertStringContainsString('Editar proposta', $source);
        $this->assertStringContainsString('Revisar PDF', $source);
        $this->assertStringContainsString('Proposta criada com sucesso', $source);

        $this->assertStringNotContainsString('Voltar para orçamentos', $source);
        $this->assertStringNotContainsString('Editar orçamento', $source);
    }
}
