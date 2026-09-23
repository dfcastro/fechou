<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\Business;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\QuoteEvent;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationsBellTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function createBusiness(
        User $user,
        array $overrides = [],
        bool $withNotifications = true
    ): Business {
        $business = Business::forceCreate(
            array_merge(
                [
                    'user_id' => $user->id,
                    'name' => 'Empresa Teste',

                    'follow_up_enabled' => true,

                    'follow_up_sent_after_days' => 2,

                    'follow_up_viewed_after_days' => 2,

                    'follow_up_expiry_warning_days' => 1,

                    'follow_up_cooldown_hours' => 24,
                ],
                $overrides
            )
        );

        $features = [
            PlanFeature::CLIENT_MANAGEMENT->value,
            PlanFeature::PUBLIC_QUOTE_LINK->value,
            PlanFeature::PDF_EXPORT->value,
            PlanFeature::WHATSAPP_SHARING->value,
        ];

        if ($withNotifications) {
            $features[] = PlanFeature::FOLLOW_UP->value;
            $features[] = PlanFeature::NOTIFICATIONS->value;
        }

        $plan = Plan::create([
            'name' =>
                $withNotifications
                ? 'Pro Teste'
                : 'Grátis Teste',

            'slug' =>
                ($withNotifications
                    ? 'pro-test-'
                    : 'free-test-')
                . $business->id,

            'price' =>
                $withNotifications
                ? 29.90
                : 0,

            'billing_interval' => 'month',

            'quote_limit' =>
                $withNotifications
                ? null
                : 5,

            'features' => $features,

            'is_active' => true,

            'sort_order' =>
                $withNotifications
                ? 20
                : 10,
        ]);

        Subscription::create([
            'business_id' => $business->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'current_period_starts_at' =>
                now()->startOfMonth(),
            'current_period_ends_at' =>
                now()->endOfMonth(),
        ]);

        return $business;
    }


    private function createClient(
        Business $business,
        array $overrides = []
    ): Client {
        return Client::forceCreate(
            array_merge(
                [
                    'business_id' => $business->id,
                    'name' => 'Cliente Teste',
                    'whatsapp' => '38999999999',
                ],
                $overrides
            )
        );
    }


    private function createQuote(
        Business $business,
        Client $client,
        array $overrides = []
    ): Quote {
        $nextNumber = (
            Quote::query()
                ->withTrashed()
                ->where(
                    'business_id',
                    $business->id
                )
                ->max('number')
            ?? 0
        ) + 1;


        return Quote::forceCreate(
            array_merge(
                [
                    'business_id' => $business->id,

                    'client_id' => $client->id,

                    'root_quote_id' => null,

                    'number' => $nextNumber,

                    'version' => 1,

                    'title' => 'Orçamento de teste',

                    'subtotal' => 1000,

                    'discount' => 0,

                    'total' => 1000,

                    'status' => 'sent',

                    'sent_at' => now()
                        ->subDays(3),

                    'valid_until' => now()
                        ->addDays(30),
                ],
                $overrides
            )
        );
    }


    public function test_sent_quote_after_configured_period_appears_in_notifications(): void
    {
        $user = User::factory()->create();

        $business = $this->createBusiness($user);

        $client = $this->createClient($business);

        $this->createQuote($business, $client);

        $this->actingAs($user);

        $component = Livewire::test(
            'notifications-bell'
        );

        $this->assertSame(
            1,
            $component
                ->instance()
                ->count()
        );
    }


    public function test_recently_sent_quote_does_not_appear_in_notifications(): void
    {
        $user = User::factory()->create();

        $business = $this->createBusiness($user);

        $client = $this->createClient($business);

        $this->createQuote(
            $business,
            $client,
            [
                'sent_at' => now()
                    ->subHours(12),
            ]
        );

        $this->actingAs($user);

        $component = Livewire::test(
            'notifications-bell'
        );

        $this->assertSame(
            0,
            $component
                ->instance()
                ->count()
        );
    }


    public function test_viewed_quote_after_configured_period_appears_in_notifications(): void
    {
        $user = User::factory()->create();

        $business = $this->createBusiness($user);

        $client = $this->createClient($business);

        $this->createQuote(
            $business,
            $client,
            [
                'status' => 'viewed',

                'sent_at' => now()
                    ->subDays(5),

                'first_viewed_at' => now()
                    ->subDays(3),
            ]
        );

        $this->actingAs($user);

        $component = Livewire::test(
            'notifications-bell'
        );

        $this->assertSame(
            1,
            $component
                ->instance()
                ->count()
        );
    }


    public function test_recent_follow_up_hides_quote_during_cooldown(): void
    {
        $user = User::factory()->create();

        $business = $this->createBusiness($user);

        $client = $this->createClient($business);

        $quote = $this->createQuote(
            $business,
            $client
        );

        $quote->events()->create([
            'type' => 'follow_up',

            'metadata' => [
                'channel' => 'whatsapp',
                'origin' => 'test',
            ],
        ]);

        $this->actingAs($user);

        $component = Livewire::test(
            'notifications-bell'
        );

        $this->assertSame(
            0,
            $component
                ->instance()
                ->count()
        );
    }


    public function test_quote_returns_after_follow_up_cooldown_expires(): void
    {
        $user = User::factory()->create();

        $business = $this->createBusiness(
            $user,
            [
                'follow_up_cooldown_hours' => 24,
            ]
        );

        $client = $this->createClient(
            $business
        );

        $quote = $this->createQuote(
            $business,
            $client
        );

        $event = $quote->events()->create([
            'type' => 'follow_up',

            'metadata' => [
                'channel' => 'whatsapp',
                'origin' => 'test',
            ],
        ]);

        $event->forceFill([
            'created_at' => now()
                ->subHours(25),

            'updated_at' => now()
                ->subHours(25),
        ])->save();

        $this->actingAs($user);

        $component = Livewire::test(
            'notifications-bell'
        );

        $this->assertSame(
            1,
            $component
                ->instance()
                ->count()
        );
    }


    public function test_register_follow_up_creates_event_with_correct_metadata(): void
    {
        $user = User::factory()->create();

        $business = $this->createBusiness($user);

        $client = $this->createClient($business);

        $quote = $this->createQuote(
            $business,
            $client
        );

        $this->actingAs($user);

        Livewire::test(
            'notifications-bell'
        )
            ->call(
                'registerFollowUp',
                $quote->id
            )
            ->assertHasNoErrors();

        $event = QuoteEvent::query()
            ->where(
                'quote_id',
                $quote->id
            )
            ->where(
                'type',
                'follow_up'
            )
            ->first();

        $this->assertNotNull(
            $event
        );

        $this->assertSame(
            'whatsapp',
            $event->metadata['channel']
        );

        $this->assertSame(
            'notifications',
            $event->metadata['origin']
        );
    }


    public function test_multiple_clicks_do_not_create_duplicate_follow_up_events(): void
    {
        $user = User::factory()->create();

        $business = $this->createBusiness($user);

        $client = $this->createClient($business);

        $quote = $this->createQuote(
            $business,
            $client
        );

        $this->actingAs($user);

        $component = Livewire::test(
            'notifications-bell'
        );

        $component->call(
            'registerFollowUp',
            $quote->id
        );

        $component->call(
            'registerFollowUp',
            $quote->id
        );

        $this->assertSame(
            1,
            QuoteEvent::query()
                ->where(
                    'quote_id',
                    $quote->id
                )
                ->where(
                    'type',
                    'follow_up'
                )
                ->count()
        );
    }


    public function test_user_only_sees_notifications_from_own_business(): void
    {
        $userA = User::factory()->create();

        $businessA = $this->createBusiness(
            $userA,
            [
                'name' => 'Empresa A',
            ]
        );

        $clientA = $this->createClient(
            $businessA,
            [
                'name' => 'Cliente A',
            ]
        );

        $this->createQuote(
            $businessA,
            $clientA
        );


        $userB = User::factory()->create();

        $businessB = $this->createBusiness(
            $userB,
            [
                'name' => 'Empresa B',
            ]
        );

        $clientB = $this->createClient(
            $businessB,
            [
                'name' => 'Cliente B',
            ]
        );

        $this->createQuote(
            $businessB,
            $clientB
        );

        $this->actingAs(
            $userA
        );

        $component = Livewire::test(
            'notifications-bell'
        );

        $this->assertSame(
            1,
            $component
                ->instance()
                ->count()
        );

        $component
            ->assertSee('Cliente A')
            ->assertDontSee('Cliente B');
    }


    public function test_only_latest_quote_version_counts_as_notification(): void
    {
        $user = User::factory()->create();

        $business = $this->createBusiness($user);

        $client = $this->createClient($business);

        $v1 = $this->createQuote(
            $business,
            $client,
            [
                'version' => 1,
            ]
        );

        $this->createQuote(
            $business,
            $client,
            [
                'root_quote_id' =>
                    $v1->id,

                'version' => 2,

                'sent_at' => now()
                    ->subDays(3),
            ]
        );

        $this->actingAs($user);

        $component = Livewire::test(
            'notifications-bell'
        );

        $this->assertSame(
            1,
            $component
                ->instance()
                ->count()
        );
    }


    public function test_disabled_follow_up_has_no_notifications(): void
    {
        $user = User::factory()->create();

        $business = $this->createBusiness(
            $user,
            [
                'follow_up_enabled' => false,
            ]
        );

        $client = $this->createClient(
            $business
        );

        $this->createQuote(
            $business,
            $client
        );

        $this->actingAs($user);

        $component = Livewire::test(
            'notifications-bell'
        );

        $this->assertSame(
            0,
            $component
                ->instance()
                ->count()
        );
    }


    public function test_free_plan_does_not_have_notifications_access(): void
    {
        $user = User::factory()->create();

        $business = $this->createBusiness(
            $user,
            [],
            false
        );

        $client = $this->createClient(
            $business
        );

        $this->createQuote(
            $business,
            $client
        );

        $this->actingAs($user);

        $component = Livewire::test(
            'notifications-bell'
        );

        $this->assertFalse(
            $component
                ->instance()
                ->hasAccess()
        );

        $this->assertSame(
            0,
            $component
                ->instance()
                ->count()
        );

        $component->assertDontSee(
            'Propostas que precisam de atenção'
        );
    }
}
