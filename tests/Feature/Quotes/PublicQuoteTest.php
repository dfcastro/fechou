<?php

namespace Tests\Feature\Quotes;

use App\Enums\PlanFeature;
use App\Models\Business;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PublicQuoteTest extends TestCase
{
    use RefreshDatabase;

    private function createQuote(
        array $attributes = [],
        ?Business $business = null
    ): Quote {
        $business ??= Business::factory()->create();

        $client = Client::factory()->create([
            'business_id' => $business->id,
        ]);

        return Quote::factory()->create(array_merge([
            'business_id' => $business->id,
            'client_id' => $client->id,
            'status' => 'sent',
            'sent_at' => now(),
            'valid_until' => now()->addDays(7),
        ], $attributes));
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

    public function test_public_quote_can_be_opened_with_valid_token(): void
    {
        $quote = $this->createQuote();

        $response = $this->get(
            route('quotes.public', $quote->public_token)
        );

        $response->assertOk();
    }

    public function test_invalid_public_token_returns_404(): void
    {
        $response = $this->get(
            route('quotes.public', 'token-inexistente')
        );

        $response->assertNotFound();
    }

    public function test_draft_quote_cannot_be_opened_publicly(): void
    {
        $quote = $this->createQuote([
            'status' => 'draft',
            'sent_at' => null,
        ]);

        $response = $this->get(
            route('quotes.public', $quote->public_token)
        );

        $response->assertNotFound();
    }

    public function test_first_public_view_changes_status_to_viewed(): void
    {
        $quote = $this->createQuote([
            'status' => 'sent',
            'first_viewed_at' => null,
        ]);

        $this->get(
            route('quotes.public', $quote->public_token)
        )->assertOk();

        $quote->refresh();

        $this->assertSame('viewed', $quote->status);
        $this->assertNotNull($quote->first_viewed_at);

        $this->assertDatabaseHas('quote_events', [
            'quote_id' => $quote->id,
            'type' => 'viewed',
        ]);
    }

    public function test_view_event_is_recorded_only_once(): void
    {
        $quote = $this->createQuote([
            'status' => 'sent',
            'first_viewed_at' => null,
        ]);

        $this->get(
            route('quotes.public', $quote->public_token)
        )->assertOk();

        $this->get(
            route('quotes.public', $quote->public_token)
        )->assertOk();

        $this->assertDatabaseCount('quote_events', 1);

        $this->assertDatabaseHas('quote_events', [
            'quote_id' => $quote->id,
            'type' => 'viewed',
        ]);
    }

    public function test_public_quote_can_be_accepted(): void
    {
        $quote = $this->createQuote([
            'status' => 'sent',
        ]);

        Livewire::test('pages::quotes.public', [
            'token' => $quote->public_token,
        ])
            ->call('accept');

        $quote->refresh();

        $this->assertSame('accepted', $quote->status);
        $this->assertNotNull($quote->accepted_at);
        $this->assertNull($quote->rejected_at);

        $this->assertDatabaseHas('quote_events', [
            'quote_id' => $quote->id,
            'type' => 'accepted',
        ]);
    }

    public function test_public_quote_can_be_rejected(): void
    {
        $quote = $this->createQuote([
            'status' => 'sent',
        ]);

        Livewire::test('pages::quotes.public', [
            'token' => $quote->public_token,
        ])
            ->call('reject');

        $quote->refresh();

        $this->assertSame('rejected', $quote->status);
        $this->assertNotNull($quote->rejected_at);
        $this->assertNull($quote->accepted_at);

        $this->assertDatabaseHas('quote_events', [
            'quote_id' => $quote->id,
            'type' => 'rejected',
        ]);
    }

    public function test_rejection_can_store_reason_and_optional_comment(): void
    {
        $quote = $this->createQuote([
            'status' => 'sent',
        ]);

        Livewire::test('pages::quotes.public', [
            'token' => $quote->public_token,
        ])
            ->set('rejectReason', 'price')
            ->set(
                'rejectComment',
                'O valor ficou acima do esperado.'
            )
            ->call('reject')
            ->assertHasNoErrors();

        $quote->refresh();

        $event = $quote
            ->events()
            ->where('type', 'rejected')
            ->latest('id')
            ->firstOrFail();

        $metadata = is_array($event->metadata)
            ? $event->metadata
            : json_decode(
                (string) $event->metadata,
                true
            );

        $this->assertSame(
            'price',
            $metadata['rejection_reason']
        );

        $this->assertSame(
            'Preço',
            $metadata['rejection_reason_label']
        );

        $this->assertSame(
            'O valor ficou acima do esperado.',
            $metadata['rejection_comment']
        );
    }

    public function test_rejection_feedback_is_optional(): void
    {
        $quote = $this->createQuote([
            'status' => 'sent',
        ]);

        Livewire::test('pages::quotes.public', [
            'token' => $quote->public_token,
        ])
            ->call('reject')
            ->assertHasNoErrors();

        $quote->refresh();

        $this->assertSame(
            'rejected',
            $quote->status
        );
    }

    public function test_rejection_reason_is_validated(): void
    {
        $quote = $this->createQuote([
            'status' => 'sent',
        ]);

        Livewire::test('pages::quotes.public', [
            'token' => $quote->public_token,
        ])
            ->set('rejectReason', 'invalid-reason')
            ->call('reject')
            ->assertHasErrors([
                'rejectReason',
            ]);

        $quote->refresh();

        $this->assertNotSame(
            'rejected',
            $quote->status
        );
    }

    public function test_accepted_quote_cannot_be_rejected_afterwards(): void
    {
        $quote = $this->createQuote([
            'status' => 'sent',
        ]);

        $component = Livewire::test('pages::quotes.public', [
            'token' => $quote->public_token,
        ]);

        $component->call('accept');
        $component->call('reject');

        $quote->refresh();

        $this->assertSame('accepted', $quote->status);
        $this->assertNotNull($quote->accepted_at);
        $this->assertNull($quote->rejected_at);
    }

    public function test_rejected_quote_cannot_be_accepted_afterwards(): void
    {
        $quote = $this->createQuote([
            'status' => 'sent',
        ]);

        $component = Livewire::test('pages::quotes.public', [
            'token' => $quote->public_token,
        ]);

        $component->call('reject');
        $component->call('accept');

        $quote->refresh();

        $this->assertSame('rejected', $quote->status);
        $this->assertNotNull($quote->rejected_at);
        $this->assertNull($quote->accepted_at);
    }

    public function test_expired_quote_is_marked_as_expired(): void
    {
        $quote = $this->createQuote([
            'status' => 'sent',
            'valid_until' => now()->subDay(),
        ]);

        $this->get(
            route('quotes.public', $quote->public_token)
        )->assertOk();

        $quote->refresh();

        $this->assertSame('expired', $quote->status);
        $this->assertNull($quote->accepted_at);
        $this->assertNull($quote->rejected_at);
    }

    public function test_expired_quote_cannot_be_accepted(): void
    {
        $quote = $this->createQuote([
            'status' => 'sent',
            'valid_until' => now()->subDay(),
        ]);

        Livewire::test('pages::quotes.public', [
            'token' => $quote->public_token,
        ])
            ->call('accept');

        $quote->refresh();

        $this->assertSame('expired', $quote->status);
        $this->assertNull($quote->accepted_at);
    }
    public function test_accepting_quote_initializes_post_acceptance_workflow(): void
    {
        $quote = $this->createQuote([
            'status' => 'sent',
        ]);

        Livewire::test('pages::quotes.public', [
            'token' => $quote->public_token,
        ])
            ->call('accept');

        $quote->refresh();

        $this->assertSame(
            'accepted',
            $quote->status
        );

        $this->assertNotNull(
            $quote->accepted_at
        );

        $this->assertSame(
            'pending',
            $quote->payment_status
        );

        $this->assertNull(
            $quote->paid_at
        );

        $this->assertSame(
            'pending',
            $quote->execution_status
        );

        $this->assertNull(
            $quote->execution_started_at
        );

        $this->assertNull(
            $quote->completed_at
        );
    }
    public function test_public_token_is_locked_against_tampering(): void
    {
        $quoteA = $this->createQuote();
        $quoteB = $this->createQuote();

        $this->expectException(\Exception::class);

        Livewire::test('pages::quotes.public', [
            'token' => $quoteA->public_token,
        ])
            ->set('token', $quoteB->public_token);

        $quoteB->refresh();

        $this->assertNotSame('accepted', $quoteB->status);
    }

    public function test_free_plan_does_not_show_saved_logo_on_public_quote(): void
    {
        $business = Business::factory()->create([
            'name' => 'Empresa Free',
            'logo_path' =>
                'business-logos/logo-free.png',
        ]);

        $this->subscribeBusiness(
            $business,
            'free'
        );

        $quote = $this->createQuote(
            [],
            $business
        );

        $this
            ->get(
                route(
                    'quotes.public',
                    $quote->public_token
                )
            )
            ->assertOk()
            ->assertDontSee(
                'storage/business-logos/logo-free.png'
            );
    }

    public function test_pro_plan_shows_saved_logo_on_public_quote(): void
    {
        $business = Business::factory()->create([
            'name' => 'Empresa Pro',
            'logo_path' =>
                'business-logos/logo-pro.png',
        ]);

        $this->subscribeBusiness(
            $business,
            'pro'
        );

        $quote = $this->createQuote(
            [],
            $business
        );

        $this
            ->get(
                route(
                    'quotes.public',
                    $quote->public_token
                )
            )
            ->assertOk()
            ->assertSee(
                'storage/business-logos/logo-pro.png'
            );
    }
}
