<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuotePipelineQuickActionsTest extends TestCase
{
    use RefreshDatabase;


    private function context(): array
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);


        $business = Business::factory()->create([
            'user_id' =>
                $user->id,

            'onboarding_completed_at' =>
                now(),
        ]);


        $client = Client::factory()->create([
            'business_id' =>
                $business->id,
        ]);


        return [
            $user,
            $business,
            $client,
        ];
    }


    public function test_pipeline_can_complete_post_acceptance_workflow(): void
    {
        [
            $user,
            $business,
            $client,
        ] = $this->context();


        $quote = Quote::factory()->create([
            'business_id' =>
                $business->id,

            'client_id' =>
                $client->id,

            'status' =>
                'accepted',

            'payment_status' =>
                'pending',

            'execution_status' =>
                'pending',

            'accepted_at' =>
                now()->subHour(),
        ]);


        $component = Livewire::actingAs(
            $user
        )
            ->test(
                'pages::quotes.pipeline'
            );


        $component->call(
            'markAsPaidFromPipeline',
            $quote->id
        );


        $quote->refresh();


        $this->assertSame(
            'paid',
            $quote->payment_status
        );

        $this->assertNotNull(
            $quote->paid_at
        );

        $this->assertDatabaseHas(
            'quote_events',
            [
                'quote_id' =>
                    $quote->id,

                'type' =>
                    'payment_received',
            ]
        );


        $component->call(
            'startExecutionFromPipeline',
            $quote->id
        );


        $quote->refresh();


        $this->assertSame(
            'in_progress',
            $quote->execution_status
        );

        $this->assertNotNull(
            $quote->execution_started_at
        );

        $this->assertDatabaseHas(
            'quote_events',
            [
                'quote_id' =>
                    $quote->id,

                'type' =>
                    'execution_started',
            ]
        );


        $component->call(
            'completeExecutionFromPipeline',
            $quote->id
        );


        $quote->refresh();


        $this->assertSame(
            'completed',
            $quote->execution_status
        );

        $this->assertNotNull(
            $quote->completed_at
        );

        $this->assertDatabaseHas(
            'quote_events',
            [
                'quote_id' =>
                    $quote->id,

                'type' =>
                    'execution_completed',
            ]
        );
    }


    public function test_execution_remains_independent_from_payment(): void
    {
        [
            $user,
            $business,
            $client,
        ] = $this->context();


        $quote = Quote::factory()->create([
            'business_id' =>
                $business->id,

            'client_id' =>
                $client->id,

            'status' =>
                'accepted',

            'payment_status' =>
                'pending',

            'execution_status' =>
                'pending',
        ]);


        $component = Livewire::actingAs(
            $user
        )
            ->test(
                'pages::quotes.pipeline'
            );


        $component->call(
            'startExecutionFromPipeline',
            $quote->id
        );


        $quote->refresh();


        $this->assertSame(
            'pending',
            $quote->payment_status
        );

        $this->assertSame(
            'in_progress',
            $quote->execution_status
        );


        $component->call(
            'markAsPaidFromPipeline',
            $quote->id
        );


        $quote->refresh();


        $this->assertSame(
            'paid',
            $quote->payment_status
        );

        $this->assertSame(
            'in_progress',
            $quote->execution_status
        );
    }


    public function test_pipeline_actions_are_idempotent(): void
    {
        [
            $user,
            $business,
            $client,
        ] = $this->context();


        $quote = Quote::factory()->create([
            'business_id' =>
                $business->id,

            'client_id' =>
                $client->id,

            'status' =>
                'accepted',

            'payment_status' =>
                'pending',

            'execution_status' =>
                'pending',
        ]);


        $component = Livewire::actingAs(
            $user
        )
            ->test(
                'pages::quotes.pipeline'
            );


        $component
            ->call(
                'markAsPaidFromPipeline',
                $quote->id
            )
            ->call(
                'markAsPaidFromPipeline',
                $quote->id
            );


        $this->assertSame(
            1,
            $quote
                ->events()
                ->where(
                    'type',
                    'payment_received'
                )
                ->count()
        );


        $component
            ->call(
                'startExecutionFromPipeline',
                $quote->id
            )
            ->call(
                'startExecutionFromPipeline',
                $quote->id
            );


        $this->assertSame(
            1,
            $quote
                ->events()
                ->where(
                    'type',
                    'execution_started'
                )
                ->count()
        );


        $component
            ->call(
                'completeExecutionFromPipeline',
                $quote->id
            )
            ->call(
                'completeExecutionFromPipeline',
                $quote->id
            );


        $this->assertSame(
            1,
            $quote
                ->events()
                ->where(
                    'type',
                    'execution_completed'
                )
                ->count()
        );
    }
}
