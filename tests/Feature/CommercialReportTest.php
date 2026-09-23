<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CommercialReportTest extends TestCase
{
    use RefreshDatabase;


    public function test_commercial_report_uses_period_business_and_latest_family(): void
    {
        Carbon::setTestNow(
            '2026-09-23 12:00:00'
        );

        $user = User::factory()->create();

        $business = Business::create([
            'user_id' => $user->id,
            'name' => 'Empresa Teste',
            'onboarding_completed_at' => now(),
        ]);

        $client = Client::create([
            'business_id' => $business->id,
            'name' => 'Cliente Teste',
        ]);


        $createQuote = function (
            int $number,
            string $status,
            float $total,
            string $createdAt,
            ?string $sentAt = null,
            ?string $acceptedAt = null,
            ?string $rejectedAt = null,
            ?string $paidAt = null,
            ?int $rootQuoteId = null,
            int $version = 1,
            ?int $businessId = null,
            ?int $clientId = null
        ) use ($business, $client): Quote {
            $quote = Quote::create([
                'business_id' =>
                    $businessId ?? $business->id,

                'client_id' =>
                    $clientId ?? $client->id,

                'number' => $number,
                'title' => 'Teste ' . $number,
                'subtotal' => $total,
                'discount' => 0,
                'total' => $total,
                'status' => $status,
                'sent_at' => $sentAt,
                'accepted_at' => $acceptedAt,
                'rejected_at' => $rejectedAt,
                'payment_status' =>
                    $paidAt ? 'paid' : (
                        $status === 'accepted'
                            ? 'pending'
                            : null
                    ),
                'paid_at' => $paidAt,
                'root_quote_id' => $rootQuoteId,
                'version' => $version,
            ]);

            $quote->forceFill([
                'created_at' => Carbon::parse(
                    $createdAt
                ),
                'updated_at' => Carbon::parse(
                    $createdAt
                ),
            ])->saveQuietly();

            return $quote;
        };


        /*
         * Aceita e paga:
         * entra em valor aceito, ticket e recebido.
         */
        $createQuote(
            number: 1,
            status: 'accepted',
            total: 100,
            createdAt: '2026-09-01 08:00:00',
            sentAt: '2026-09-02 08:00:00',
            acceptedAt: '2026-09-04 08:00:00',
            paidAt: '2026-09-05 08:00:00',
        );


        /*
         * Enviada / visualizada sem decisão.
         */
        $createQuote(
            number: 2,
            status: 'viewed',
            total: 200,
            createdAt: '2026-09-03 08:00:00',
            sentAt: '2026-09-04 08:00:00',
        )->update([
            'first_viewed_at' =>
                '2026-09-05 08:00:00',
        ]);


        /*
         * Aceita e ainda em aberto.
         */
        $createQuote(
            number: 3,
            status: 'accepted',
            total: 400,
            createdAt: '2026-09-06 08:00:00',
            sentAt: '2026-09-07 08:00:00',
            acceptedAt: '2026-09-09 08:00:00',
        );


        /*
         * Família versionada.
         *
         * A versão antiga aceita NÃO pode entrar.
         */
        $root = $createQuote(
            number: 4,
            status: 'accepted',
            total: 999,
            createdAt: '2026-09-08 08:00:00',
            sentAt: '2026-09-09 08:00:00',
            acceptedAt: '2026-09-10 08:00:00',
        );

        $createQuote(
            number: 5,
            status: 'rejected',
            total: 300,
            createdAt: '2026-09-11 08:00:00',
            sentAt: '2026-09-12 08:00:00',
            rejectedAt: '2026-09-13 08:00:00',
            rootQuoteId: $root->id,
            version: 2,
        );


        /*
         * Outra empresa não pode vazar.
         */
        $otherUser =
            User::factory()->create();

        $otherBusiness =
            Business::create([
                'user_id' => $otherUser->id,
                'name' => 'Outra empresa',
                'onboarding_completed_at' => now(),
            ]);

        $otherClient =
            Client::create([
                'business_id' =>
                    $otherBusiness->id,

                'name' =>
                    'Outro cliente',
            ]);

        $createQuote(
            number: 1,
            status: 'accepted',
            total: 9000,
            createdAt: '2026-09-01 08:00:00',
            sentAt: '2026-09-02 08:00:00',
            acceptedAt: '2026-09-03 08:00:00',
            businessId: $otherBusiness->id,
            clientId: $otherClient->id,
        );


        $response =
            $this
                ->actingAs($user)
                ->get(
                    route(
                        'reports.commercial'
                    )
                );


        $response
            ->assertOk()

            ->assertSee(
                'Relatórios comerciais'
            )

            /*
             * 4 propostas enviadas:
             * #1, #2, #3 e versão mais recente #5.
             */
            ->assertSee(
                '4'
            )

            /*
             * 2 aceitas da coorte de 4 enviadas.
             */
            ->assertSee(
                '50%'
            )

            /*
             * Valor aceito:
             * 100 + 400.
             *
             * A versão antiga de 999 não entra.
             */
            ->assertSee(
                'R$ 500,00'
            )

            /*
             * Ticket:
             * 500 / 2.
             */
            ->assertSee(
                'R$ 250,00'
            )

            ->assertSee(
                'R$ 100,00'
            )

            ->assertSee(
                'R$ 400,00'
            )

            ->assertDontSee(
                'R$ 9.000,00'
            )

            ->assertDontSee(
                'R$ 999,00'
            );


        Carbon::setTestNow();
    }
}
