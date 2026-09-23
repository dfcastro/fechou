<?php

namespace Tests\Feature\Quotes;

use App\Enums\PlanFeature;
use App\Models\Business;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\Subscription;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class QuoteAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithBusiness(): array
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $business = Business::factory()->create([
            'user_id' => $user->id,
        ]);

        return [$user, $business];
    }

    private function createQuoteForBusiness(
        Business $business
    ): Quote {
        $client = Client::factory()->create([
            'business_id' => $business->id,
        ]);

        return Quote::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,
        ]);
    }

    private function subscribeBusiness(
        Business $business,
        string $slug
    ): Subscription {
        $isPro = $slug === 'pro';

        $features = [
            PlanFeature::CLIENT_MANAGEMENT->value,
            PlanFeature::PUBLIC_QUOTE_LINK->value,
            PlanFeature::PDF_EXPORT->value,
            PlanFeature::WHATSAPP_SHARING->value,
        ];

        if ($isPro) {
            $features = [
                ...$features,
                PlanFeature::QUOTE_VERSIONING->value,
                PlanFeature::FOLLOW_UP->value,
                PlanFeature::NOTIFICATIONS->value,
                PlanFeature::CUSTOM_BRANDING->value,
            ];
        }

        $plan = Plan::create([
            'name' => $isPro ? 'Pro' : 'Grátis',
            'slug' => $slug,
            'description' => 'Plano de teste',
            'price' => $isPro ? 29.90 : 0,
            'billing_interval' => 'month',
            'quote_limit' => $isPro ? null : 5,
            'features' => $features,
            'is_active' => true,
            'sort_order' => $isPro ? 20 : 10,
        ]);

        return Subscription::create([
            'business_id' => $business->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'current_period_starts_at' =>
                now()->startOfMonth(),
            'current_period_ends_at' =>
                now()->endOfMonth(),
        ]);
    }

    private function fakePdfStream(
        callable $assertViewData
    ): void {
        /*
         * O facade do DomPDF possui retorno tipado como
         * Barryvdh\DomPDF\PDF em loadView().
         *
         * Por isso o objeto retornado pelo mock também precisa
         * ser um mock dessa classe concreta, e não um Mockery
         * genérico.
         */
        $pdfDocument = Mockery::mock(
            DomPdfDocument::class
        );

        $pdfDocument
            ->shouldReceive('setPaper')
            ->once()
            ->with('a4', 'portrait')
            ->andReturnSelf();

        $pdfDocument
            ->shouldReceive('stream')
            ->once()
            ->andReturn(
                response(
                    'fake-pdf',
                    200,
                    [
                        'Content-Type' =>
                            'application/pdf',
                    ]
                )
            );

        Pdf::shouldReceive('loadView')
            ->once()
            ->with(
                'pdf.quote',
                Mockery::on(
                    function (array $data) use ($assertViewData): bool {
                        $assertViewData($data);

                        return true;
                    }
                )
            )
            ->andReturn($pdfDocument);
    }

    public function test_user_cannot_view_quote_from_another_business(): void
    {
        [$userA] = $this->createUserWithBusiness();

        [, $businessB] = $this->createUserWithBusiness();

        $quoteB = $this->createQuoteForBusiness($businessB);

        $response = $this
            ->actingAs($userA)
            ->get(route('quotes.show', $quoteB));

        $response->assertNotFound();
    }

    public function test_user_cannot_edit_quote_from_another_business(): void
    {
        [$userA] = $this->createUserWithBusiness();

        [, $businessB] = $this->createUserWithBusiness();

        $quoteB = $this->createQuoteForBusiness($businessB);

        $response = $this
            ->actingAs($userA)
            ->get(route('quotes.edit', $quoteB));

        $response->assertNotFound();
    }

    public function test_user_cannot_download_pdf_from_another_business(): void
    {
        [$userA] = $this->createUserWithBusiness();

        [, $businessB] = $this->createUserWithBusiness();

        $quoteB = $this->createQuoteForBusiness($businessB);

        $response = $this
            ->actingAs($userA)
            ->get(route('quotes.pdf', $quoteB));

        $response->assertNotFound();
    }

    public function test_user_cannot_preview_pdf_from_another_business(): void
    {
        [$userA] = $this->createUserWithBusiness();

        [, $businessB] = $this->createUserWithBusiness();

        $quoteB = $this->createQuoteForBusiness($businessB);

        $response = $this
            ->actingAs($userA)
            ->get(route('quotes.pdf.preview', $quoteB));

        $response->assertNotFound();
    }

    public function test_user_can_view_own_quote(): void
    {
        [$user, $business] = $this->createUserWithBusiness();

        $quote = $this->createQuoteForBusiness($business);

        $response = $this
            ->actingAs($user)
            ->get(route('quotes.show', $quote));

        $response->assertOk();
    }

    public function test_user_can_edit_own_quote(): void
    {
        [$user, $business] = $this->createUserWithBusiness();

        $quote = $this->createQuoteForBusiness($business);

        $response = $this
            ->actingAs($user)
            ->get(route('quotes.edit', $quote));

        $response->assertOk();
    }

    public function test_free_plan_does_not_send_saved_logo_to_pdf_view(): void
    {
        Storage::fake('public');

        [$user, $business] =
            $this->createUserWithBusiness();

        $business->update([
            'logo_path' =>
                'business-logos/logo-free.png',
        ]);

        Storage::disk('public')->put(
            'business-logos/logo-free.png',
            base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAAB'
                . 'CAQAAAC1HAwCAAAAC0lEQVR42mP8/x8A'
                . 'AgMBApLz9WQAAAAASUVORK5CYII='
            )
        );

        $this->subscribeBusiness(
            $business,
            'free'
        );

        $quote = $this
            ->createQuoteForBusiness($business);

        $this->fakePdfStream(
            function (array $data) use ($quote): void {
                $this->assertSame(
                    $quote->id,
                    $data['quote']->id
                );

                $this->assertNull(
                    $data['logoDataUri']
                );
            }
        );

        $this
            ->actingAs($user)
            ->get(
                route(
                    'quotes.pdf.preview',
                    $quote
                )
            )
            ->assertOk();
    }

    public function test_pro_plan_sends_saved_logo_to_pdf_view(): void
    {
        Storage::fake('public');

        [$user, $business] =
            $this->createUserWithBusiness();

        $business->update([
            'logo_path' =>
                'business-logos/logo-pro.png',
        ]);

        Storage::disk('public')->put(
            'business-logos/logo-pro.png',
            base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAAB'
                . 'CAQAAAC1HAwCAAAAC0lEQVR42mP8/x8A'
                . 'AgMBApLz9WQAAAAASUVORK5CYII='
            )
        );

        $this->subscribeBusiness(
            $business,
            'pro'
        );

        $quote = $this
            ->createQuoteForBusiness($business);

        $this->fakePdfStream(
            function (array $data) use ($quote): void {
                $this->assertSame(
                    $quote->id,
                    $data['quote']->id
                );

                $this->assertIsString(
                    $data['logoDataUri']
                );

                $this->assertStringStartsWith(
                    'data:image/png;base64,',
                    $data['logoDataUri']
                );
            }
        );

        $this
            ->actingAs($user)
            ->get(
                route(
                    'quotes.pdf.preview',
                    $quote
                )
            )
            ->assertOk();
    }
}
