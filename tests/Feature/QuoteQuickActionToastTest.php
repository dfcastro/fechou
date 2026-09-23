<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteQuickActionToastTest extends TestCase
{
    use RefreshDatabase;


    public function test_quick_actions_have_toast_feedback(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        Business::factory()->create([
            'user_id' => $user->id,
            'onboarding_completed_at' => now(),
        ]);


        /*
         * Força o carregamento real da página.
         * Também detecta erro de PHP/Blade
         * introduzido pelo patch.
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


        $this->assertStringContainsString(
            '@quote-toast.window=',
            $source
        );

        $this->assertStringContainsString(
            'TOAST DE AÇÃO RÁPIDA',
            $source
        );

        $this->assertStringContainsString(
            '3500',
            $source
        );


        $paymentStart = strpos(
            $source,
            'public function markAsPaidFromList('
        );

        $startExecutionStart = strpos(
            $source,
            'public function startExecutionFromList('
        );

        $completeStart = strpos(
            $source,
            'public function completeExecutionFromList('
        );

        $badgesStart = strpos(
            $source,
            'public function postAcceptanceBadges('
        );


        $paymentMethod = substr(
            $source,
            $paymentStart,
            $startExecutionStart
                - $paymentStart
        );

        $startMethod = substr(
            $source,
            $startExecutionStart,
            $completeStart
                - $startExecutionStart
        );

        $completeMethod = substr(
            $source,
            $completeStart,
            $badgesStart
                - $completeStart
        );


        $this->assertStringContainsString(
            'Pagamento registrado com sucesso.',
            $paymentMethod
        );

        $this->assertStringContainsString(
            "'quote-toast'",
            $paymentMethod
        );


        $this->assertStringContainsString(
            'Execução iniciada.',
            $startMethod
        );

        $this->assertStringContainsString(
            "'quote-toast'",
            $startMethod
        );


        $this->assertStringContainsString(
            'Execução concluída.',
            $completeMethod
        );

        $this->assertStringContainsString(
            "'quote-toast'",
            $completeMethod
        );


        /*
         * Garante que as ações distinguem
         * alteração real de operação idempotente.
         */
        $this->assertStringContainsString(
            'return false;',
            $paymentMethod
        );

        $this->assertStringContainsString(
            'return true;',
            $paymentMethod
        );

        $this->assertStringContainsString(
            'return false;',
            $startMethod
        );

        $this->assertStringContainsString(
            'return true;',
            $startMethod
        );

        $this->assertStringContainsString(
            'return false;',
            $completeMethod
        );

        $this->assertStringContainsString(
            'return true;',
            $completeMethod
        );
    }
}
