<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotePostAcceptanceSelectFilterTest extends TestCase
{
    use RefreshDatabase;


    public function test_quotes_page_has_integrated_post_acceptance_filter(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        Business::factory()->create([
            'user_id' => $user->id,
            'onboarding_completed_at' => now(),
        ]);


        /*
         * Além de testar o HTML, esta requisição
         * força o Livewire a carregar a classe.
         *
         * Isso também detecta erros fatais como
         * métodos duplicados.
         */
        $this
            ->actingAs($user)
            ->get(
                route('quotes.index')
            )
            ->assertOk();


        $source = file_get_contents(
            resource_path(
                'views/pages/quotes/index.blade.php'
            )
        );


        $this->assertSame(
            1,
            substr_count(
                $source,
                'public function updatedStatus(): void'
            )
        );

        $this->assertSame(
            1,
            substr_count(
                $source,
                'public function updatedPostAcceptance(): void'
            )
        );

        $this->assertSame(
            1,
            substr_count(
                $source,
                'public function clearFilters(): void'
            )
        );


        $this->assertStringContainsString(
            'wire:model.live="postAcceptance"',
            $source
        );

        $this->assertStringContainsString(
            'Todos os pós-aceites',
            $source
        );

        $this->assertStringContainsString(
            'Negócios fechados',
            $source
        );

        $this->assertStringContainsString(
            'A receber',
            $source
        );

        $this->assertStringContainsString(
            'Aguardando execução',
            $source
        );

        $this->assertStringContainsString(
            'Em execução',
            $source
        );

        $this->assertStringContainsString(
            'Concluídos',
            $source
        );


        /*
         * Os dois hooks precisam continuar
         * resetando a paginação.
         */
        $statusStart = strpos(
            $source,
            'public function updatedStatus(): void'
        );

        $postStart = strpos(
            $source,
            'public function updatedPostAcceptance(): void'
        );

        $clearStart = strpos(
            $source,
            'public function clearFilters(): void'
        );


        $statusMethod = substr(
            $source,
            $statusStart,
            $postStart - $statusStart
        );

        $postMethod = substr(
            $source,
            $postStart,
            $clearStart - $postStart
        );


        $this->assertStringContainsString(
            '$this->postAcceptance = \'\';',
            $statusMethod
        );

        $this->assertStringContainsString(
            '$this->resetPage();',
            $statusMethod
        );

        $this->assertStringContainsString(
            '$this->status = \'\';',
            $postMethod
        );

        $this->assertStringContainsString(
            '$this->resetPage();',
            $postMethod
        );
    }
}
