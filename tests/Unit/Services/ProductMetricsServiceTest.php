<?php

namespace Tests\Unit\Services;

use App\Models\Business;
use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use App\Services\ProductMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProductMetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductMetricsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ProductMetricsService::class);
    }

    private function createUserWithBusiness(
        Carbon $userCreatedAt,
        ?Carbon $businessCreatedAt = null
    ): array {
        $user = User::factory()->create([
            'created_at' => $userCreatedAt,
            'updated_at' => $userCreatedAt,
        ]);

        $business = Business::factory()->create([
            'user_id' => $user->id,
            'created_at' => $businessCreatedAt ?? $userCreatedAt,
            'updated_at' => $businessCreatedAt ?? $userCreatedAt,
        ]);

        return [$user, $business];
    }

    private function createQuote(
        Business $business,
        array $attributes = []
    ): Quote {
        $client = Client::factory()->create([
            'business_id' => $business->id,
        ]);

        return Quote::factory()->create(array_merge([
            'business_id' => $business->id,
            'client_id' => $client->id,
        ], $attributes));
    }

    public function test_empty_period_returns_zero_metrics(): void
    {
        $start = Carbon::parse('2026-08-01');
        $end = Carbon::parse('2026-08-07');

        $metrics = $this->service->summary($start, $end);

        $this->assertSame(0, $metrics['users']['new']);
        $this->assertSame(0, $metrics['users']['active']);
        $this->assertSame(0, $metrics['businesses']['created']);

        $this->assertSame(0, $metrics['quotes']['created']);
        $this->assertSame(0, $metrics['quotes']['sent']);
        $this->assertSame(0, $metrics['quotes']['viewed']);
        $this->assertSame(0, $metrics['quotes']['accepted']);
        $this->assertSame(0, $metrics['quotes']['rejected']);

        $this->assertSame(0.0, $metrics['conversion']['created_to_sent']);
        $this->assertSame(0.0, $metrics['conversion']['sent_to_viewed']);
        $this->assertSame(0.0, $metrics['conversion']['sent_to_accepted']);
        $this->assertSame(0.0, $metrics['conversion']['viewed_to_accepted']);

        $this->assertNull(
            $metrics['timing']['average_hours_to_first_quote']
        );

        $this->assertNull(
            $metrics['timing']['average_hours_sent_to_viewed']
        );

        $this->assertNull(
            $metrics['timing']['average_hours_sent_to_accepted']
        );
    }

    public function test_counts_new_and_active_users(): void
    {
        [$activeUser, $activeBusiness] =
            $this->createUserWithBusiness(
                Carbon::parse('2026-08-02 08:00')
            );

        $this->createQuote($activeBusiness, [
            'created_at' => Carbon::parse('2026-08-03 10:00'),
            'updated_at' => Carbon::parse('2026-08-03 10:00'),
        ]);

        $this->createUserWithBusiness(
            Carbon::parse('2026-08-04 09:00')
        );

        $this->createUserWithBusiness(
            Carbon::parse('2026-07-20 09:00')
        );

        $metrics = $this->service->summary(
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-07')
        );

        $this->assertSame(2, $metrics['users']['new']);
        $this->assertSame(1, $metrics['users']['active']);

        $this->assertNotNull($activeUser);
    }

    public function test_counts_businesses_created_in_period(): void
    {
        $this->createUserWithBusiness(
            Carbon::parse('2026-08-01 08:00')
        );

        $this->createUserWithBusiness(
            Carbon::parse('2026-07-20 08:00')
        );

        $metrics = $this->service->summary(
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-07')
        );

        $this->assertSame(1, $metrics['businesses']['created']);
    }

    public function test_counts_quote_funnel_events(): void
    {
        [, $business] = $this->createUserWithBusiness(
            Carbon::parse('2026-08-01 08:00')
        );

        $this->createQuote($business, [
            'created_at' => Carbon::parse('2026-08-02 08:00'),
            'updated_at' => Carbon::parse('2026-08-02 08:00'),
            'sent_at' => Carbon::parse('2026-08-02 09:00'),
            'first_viewed_at' => Carbon::parse('2026-08-02 10:00'),
            'accepted_at' => Carbon::parse('2026-08-02 12:00'),
            'rejected_at' => null,
            'status' => 'accepted',
        ]);

        $this->createQuote($business, [
            'created_at' => Carbon::parse('2026-08-03 08:00'),
            'updated_at' => Carbon::parse('2026-08-03 08:00'),
            'sent_at' => Carbon::parse('2026-08-03 09:00'),
            'first_viewed_at' => Carbon::parse('2026-08-03 10:00'),
            'accepted_at' => null,
            'rejected_at' => Carbon::parse('2026-08-03 11:00'),
            'status' => 'rejected',
        ]);

        $this->createQuote($business, [
            'created_at' => Carbon::parse('2026-08-04 08:00'),
            'updated_at' => Carbon::parse('2026-08-04 08:00'),
            'sent_at' => null,
            'first_viewed_at' => null,
            'accepted_at' => null,
            'rejected_at' => null,
            'status' => 'draft',
        ]);

        $metrics = $this->service->summary(
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-07')
        );

        $this->assertSame(3, $metrics['quotes']['created']);
        $this->assertSame(2, $metrics['quotes']['sent']);
        $this->assertSame(2, $metrics['quotes']['viewed']);
        $this->assertSame(1, $metrics['quotes']['accepted']);
        $this->assertSame(1, $metrics['quotes']['rejected']);
    }

    public function test_calculates_conversion_rates(): void
    {
        [, $business] = $this->createUserWithBusiness(
            Carbon::parse('2026-08-01 08:00')
        );

        for ($i = 0; $i < 4; $i++) {
            $this->createQuote($business, [
                'created_at' => Carbon::parse(
                    "2026-08-0" . ($i + 2) . " 08:00"
                ),
                'updated_at' => Carbon::parse(
                    "2026-08-0" . ($i + 2) . " 08:00"
                ),
                'sent_at' => $i < 3
                    ? Carbon::parse(
                        "2026-08-0" . ($i + 2) . " 09:00"
                    )
                    : null,
                'first_viewed_at' => $i < 2
                    ? Carbon::parse(
                        "2026-08-0" . ($i + 2) . " 10:00"
                    )
                    : null,
                'accepted_at' => $i === 0
                    ? Carbon::parse('2026-08-02 12:00')
                    : null,
            ]);
        }

        $metrics = $this->service->summary(
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-07')
        );

        $this->assertSame(
            75.0,
            $metrics['conversion']['created_to_sent']
        );

        $this->assertSame(
            66.67,
            $metrics['conversion']['sent_to_viewed']
        );

        $this->assertSame(
            33.33,
            $metrics['conversion']['sent_to_accepted']
        );

        $this->assertSame(
            50.0,
            $metrics['conversion']['viewed_to_accepted']
        );
    }

    public function test_calculates_average_time_to_first_quote(): void
    {
        [, $businessA] = $this->createUserWithBusiness(
            Carbon::parse('2026-08-01 08:00')
        );

        $this->createQuote($businessA, [
            'created_at' => Carbon::parse('2026-08-01 10:00'),
            'updated_at' => Carbon::parse('2026-08-01 10:00'),
        ]);

        [, $businessB] = $this->createUserWithBusiness(
            Carbon::parse('2026-08-02 08:00')
        );

        $this->createQuote($businessB, [
            'created_at' => Carbon::parse('2026-08-02 14:00'),
            'updated_at' => Carbon::parse('2026-08-02 14:00'),
        ]);

        $metrics = $this->service->summary(
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-07')
        );

        $this->assertSame(
            4.0,
            $metrics['timing']['average_hours_to_first_quote']
        );
    }

    public function test_calculates_average_sent_to_viewed_time(): void
    {
        [, $business] = $this->createUserWithBusiness(
            Carbon::parse('2026-08-01 08:00')
        );

        $this->createQuote($business, [
            'sent_at' => Carbon::parse('2026-08-02 08:00'),
            'first_viewed_at' => Carbon::parse('2026-08-02 10:00'),
        ]);

        $this->createQuote($business, [
            'sent_at' => Carbon::parse('2026-08-03 08:00'),
            'first_viewed_at' => Carbon::parse('2026-08-03 14:00'),
        ]);

        $metrics = $this->service->summary(
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-07')
        );

        $this->assertSame(
            4.0,
            $metrics['timing']['average_hours_sent_to_viewed']
        );
    }

    public function test_calculates_average_sent_to_accepted_time(): void
    {
        [, $business] = $this->createUserWithBusiness(
            Carbon::parse('2026-08-01 08:00')
        );

        $this->createQuote($business, [
            'sent_at' => Carbon::parse('2026-08-02 08:00'),
            'accepted_at' => Carbon::parse('2026-08-02 12:00'),
        ]);

        $this->createQuote($business, [
            'sent_at' => Carbon::parse('2026-08-03 08:00'),
            'accepted_at' => Carbon::parse('2026-08-03 16:00'),
        ]);

        $metrics = $this->service->summary(
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-07')
        );

        $this->assertSame(
            6.0,
            $metrics['timing']['average_hours_sent_to_accepted']
        );
    }
    public function test_builds_daily_quote_series(): void
    {
        [, $business] = $this->createUserWithBusiness(
            Carbon::parse('2026-08-01 08:00')
        );

        $this->createQuote($business, [
            'created_at' => Carbon::parse('2026-08-01 09:00'),
            'updated_at' => Carbon::parse('2026-08-01 09:00'),
            'sent_at' => Carbon::parse('2026-08-01 10:00'),
            'first_viewed_at' => Carbon::parse('2026-08-02 11:00'),
            'accepted_at' => Carbon::parse('2026-08-03 12:00'),
            'status' => 'accepted',
        ]);

        $this->createQuote($business, [
            'created_at' => Carbon::parse('2026-08-01 14:00'),
            'updated_at' => Carbon::parse('2026-08-01 14:00'),
            'sent_at' => Carbon::parse('2026-08-02 08:00'),
            'first_viewed_at' => Carbon::parse('2026-08-02 09:00'),
            'accepted_at' => null,
            'status' => 'viewed',
        ]);

        $this->createQuote($business, [
            'created_at' => Carbon::parse('2026-08-03 09:00'),
            'updated_at' => Carbon::parse('2026-08-03 09:00'),
            'sent_at' => null,
            'first_viewed_at' => null,
            'accepted_at' => null,
            'status' => 'draft',
        ]);

        $series = $this->service->dailySeries(
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-03')
        );

        $this->assertSame(
            ['01/08', '02/08', '03/08'],
            $series['labels']
        );

        $this->assertSame(
            [2, 0, 1],
            $series['created']
        );

        $this->assertSame(
            [1, 1, 0],
            $series['sent']
        );

        $this->assertSame(
            [0, 2, 0],
            $series['viewed']
        );

        $this->assertSame(
            [0, 0, 1],
            $series['accepted']
        );
    }
    public function test_calculates_user_activation_within_24_hours(): void
    {
        [, $businessA] = $this->createUserWithBusiness(
            Carbon::parse('2026-08-01 08:00')
        );

        $this->createQuote($businessA, [
            'created_at' => Carbon::parse('2026-08-01 12:00'),
            'updated_at' => Carbon::parse('2026-08-01 12:00'),
        ]);

        [, $businessB] = $this->createUserWithBusiness(
            Carbon::parse('2026-08-02 08:00')
        );

        $this->createQuote($businessB, [
            'created_at' => Carbon::parse('2026-08-03 12:00'),
            'updated_at' => Carbon::parse('2026-08-03 12:00'),
        ]);

        $this->createUserWithBusiness(
            Carbon::parse('2026-08-03 08:00')
        );

        $metrics = $this->service->engagement(
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-10')
        );

        $this->assertSame(
            3,
            $metrics['activation']['eligible']
        );

        $this->assertSame(
            1,
            $metrics['activation']['activated']
        );

        $this->assertSame(
            33.33,
            $metrics['activation']['rate']
        );
    }

    public function test_calculates_d7_retention(): void
    {
        [, $businessA] = $this->createUserWithBusiness(
            Carbon::parse('2026-08-01 08:00')
        );

        /*
         * Ativação.
         */
        $this->createQuote($businessA, [
            'created_at' => Carbon::parse('2026-08-01 09:00'),
            'updated_at' => Carbon::parse('2026-08-01 09:00'),
        ]);

        /*
         * Retorno no D7.
         */
        $this->createQuote($businessA, [
            'created_at' => Carbon::parse('2026-08-08 10:00'),
            'updated_at' => Carbon::parse('2026-08-08 10:00'),
        ]);


        [, $businessB] = $this->createUserWithBusiness(
            Carbon::parse('2026-08-02 08:00')
        );

        /*
         * Ativado, mas não volta.
         */
        $this->createQuote($businessB, [
            'created_at' => Carbon::parse('2026-08-02 09:00'),
            'updated_at' => Carbon::parse('2026-08-02 09:00'),
        ]);


        $metrics = $this->service->engagement(
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-20')
        );

        $this->assertSame(
            2,
            $metrics['retention']['d7']['eligible']
        );

        $this->assertSame(
            1,
            $metrics['retention']['d7']['retained']
        );

        $this->assertSame(
            50.0,
            $metrics['retention']['d7']['rate']
        );
    }

    public function test_calculates_d30_retention(): void
    {
        [, $businessA] = $this->createUserWithBusiness(
            Carbon::parse('2026-07-01 08:00')
        );

        $this->createQuote($businessA, [
            'created_at' => Carbon::parse('2026-07-01 09:00'),
            'updated_at' => Carbon::parse('2026-07-01 09:00'),
        ]);

        /*
         * Retorno exatamente na janela D30.
         */
        $this->createQuote($businessA, [
            'created_at' => Carbon::parse('2026-07-31 10:00'),
            'updated_at' => Carbon::parse('2026-07-31 10:00'),
        ]);


        [, $businessB] = $this->createUserWithBusiness(
            Carbon::parse('2026-07-02 08:00')
        );

        $this->createQuote($businessB, [
            'created_at' => Carbon::parse('2026-07-02 09:00'),
            'updated_at' => Carbon::parse('2026-07-02 09:00'),
        ]);


        $metrics = $this->service->engagement(
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-08-15')
        );

        $this->assertSame(
            2,
            $metrics['retention']['d30']['eligible']
        );

        $this->assertSame(
            1,
            $metrics['retention']['d30']['retained']
        );

        $this->assertSame(
            50.0,
            $metrics['retention']['d30']['rate']
        );
    }

    public function test_recent_users_are_not_counted_as_d30_retention_failures(): void
    {
        [, $business] = $this->createUserWithBusiness(
            Carbon::parse('2026-08-10 08:00')
        );

        $this->createQuote($business, [
            'created_at' => Carbon::parse('2026-08-10 09:00'),
            'updated_at' => Carbon::parse('2026-08-10 09:00'),
        ]);

        $metrics = $this->service->engagement(
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-20')
        );

        $this->assertSame(
            0,
            $metrics['retention']['d30']['eligible']
        );

        $this->assertSame(
            0,
            $metrics['retention']['d30']['retained']
        );

        $this->assertSame(
            0.0,
            $metrics['retention']['d30']['rate']
        );
    }
    public function test_recent_users_are_not_counted_as_activation_failures(): void
    {
        [, $businessA] = $this->createUserWithBusiness(
            Carbon::parse('2026-08-09 08:00')
        );

        $this->createQuote($businessA, [
            'created_at' => Carbon::parse('2026-08-09 09:00'),
            'updated_at' => Carbon::parse('2026-08-09 09:00'),
        ]);

        /*
         * Ainda não completou 24 horas no fim
         * do período e não deve prejudicar a taxa.
         */
        $this->createUserWithBusiness(
            Carbon::parse('2026-08-10 12:00')
        );

        $metrics = $this->service->engagement(
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-10')
        );

        $this->assertSame(
            1,
            $metrics['activation']['eligible']
        );

        $this->assertSame(
            1,
            $metrics['activation']['activated']
        );

        $this->assertSame(
            100.0,
            $metrics['activation']['rate']
        );
    }

    public function test_user_with_incomplete_d7_window_is_not_eligible(): void
    {
        [, $businessA] = $this->createUserWithBusiness(
            Carbon::parse('2026-08-07 08:00')
        );

        $this->createQuote($businessA, [
            'created_at' => Carbon::parse('2026-08-07 09:00'),
            'updated_at' => Carbon::parse('2026-08-07 09:00'),
        ]);

        /*
         * Janela D7: 14/08 até 20/08.
         * Janela completamente observada.
         */
        $this->createQuote($businessA, [
            'created_at' => Carbon::parse('2026-08-15 10:00'),
            'updated_at' => Carbon::parse('2026-08-15 10:00'),
        ]);


        [, $businessB] = $this->createUserWithBusiness(
            Carbon::parse('2026-08-10 08:00')
        );

        $this->createQuote($businessB, [
            'created_at' => Carbon::parse('2026-08-10 09:00'),
            'updated_at' => Carbon::parse('2026-08-10 09:00'),
        ]);

        /*
         * A janela deste usuário seria:
         * 17/08 até 23/08.
         *
         * Como nosso período acaba em 20/08,
         * ele ainda não pode entrar como falha.
         */

        $metrics = $this->service->engagement(
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-20')
        );

        $this->assertSame(
            1,
            $metrics['retention']['d7']['eligible']
        );

        $this->assertSame(
            1,
            $metrics['retention']['d7']['retained']
        );

        $this->assertSame(
            100.0,
            $metrics['retention']['d7']['rate']
        );
    }
}