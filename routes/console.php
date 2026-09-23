<?php

use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(
        Inspiring::quote()
    );
})->purpose(
    'Display an inspiring quote'
);

Artisan::command(
    'billing:process-subscriptions',
    function () {
        $suspended = 0;
        $canceled = 0;

        /*
         * Marca explicitamente como suspenso quem já ultrapassou
         * a tolerância. O SubscriptionService também verifica a
         * data em tempo real, então o bloqueio não depende apenas
         * deste comando rodar no segundo exato.
         */
        Subscription::query()
            ->where(
                'status',
                'past_due'
            )
            ->whereNotNull(
                'grace_ends_at'
            )
            ->where(
                'grace_ends_at',
                '<=',
                now()
            )
            ->whereNull(
                'access_suspended_at'
            )
            ->chunkById(
                100,
                function ($subscriptions) use (
                    &$suspended
                ): void {
                    foreach (
                        $subscriptions
                        as $subscription
                    ) {
                        $subscription->update([
                            'access_suspended_at' =>
                                now(),
                        ]);

                        $suspended++;
                    }
                }
            );

        /*
         * Finaliza cancelamentos somente após o período já pago.
         */
        Subscription::query()
            ->with('business')
            ->whereNotNull(
                'canceled_at'
            )
            ->whereNotNull(
                'ends_at'
            )
            ->where(
                'ends_at',
                '<=',
                now()
            )
            ->where(
                'status',
                '!=',
                'canceled'
            )
            ->chunkById(
                100,
                function ($subscriptions) use (
                    &$canceled
                ): void {
                    foreach (
                        $subscriptions
                        as $subscription
                    ) {
                        $business =
                            $subscription
                                ->business;

                        $subscription->update([
                            'status' =>
                                'canceled',

                            'billing_status' =>
                                'canceled',

                            'access_suspended_at' =>
                                now(),
                        ]);

                        if ($business) {
                            app(
                                SubscriptionService::class
                            )->ensureDefaultSubscription(
                                $business
                            );
                        }

                        $canceled++;
                    }
                }
            );

        $this->info(
            "Billing lifecycle processed. Suspended: {$suspended}; canceled: {$canceled}."
        );
    }
)->purpose(
    'Process subscription grace periods and scheduled cancellations'
);

Schedule::command(
    'billing:process-subscriptions'
)
    ->hourly()
    ->withoutOverlapping();
