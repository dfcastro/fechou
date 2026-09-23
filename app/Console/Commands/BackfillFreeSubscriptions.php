<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class BackfillFreeSubscriptions extends Command
{
    protected $signature = 'subscriptions:backfill-free
                            {--dry-run : Apenas mostra o que seria feito}';

    protected $description =
        'Cria assinatura gratuita para empresas que ainda não possuem assinatura atual';

    public function handle(
        SubscriptionService $subscriptionService
    ): int {
        $businesses = Business::query()
            ->whereDoesntHave('subscriptions', function ($query) {
                $query->whereIn('status', [
                    'trialing',
                    'active',
                    'past_due',
                ]);
            })
            ->get();

        if ($businesses->isEmpty()) {
            $this->info(
                'Nenhuma empresa precisa de assinatura.'
            );

            return self::SUCCESS;
        }

        $this->info(
            "{$businesses->count()} empresa(s) sem assinatura atual."
        );

        if ($this->option('dry-run')) {
            foreach ($businesses as $business) {
                $this->line(
                    "#{$business->id} - {$business->name}"
                );
            }

            $this->warn(
                'Dry-run: nenhuma alteração foi realizada.'
            );

            return self::SUCCESS;
        }

        $created = 0;

        foreach ($businesses as $business) {
            $subscriptionService
                ->ensureDefaultSubscription(
                    $business
                );

            $created++;
        }

        $this->info(
            "{$created} assinatura(s) gratuita(s) criada(s)."
        );

        return self::SUCCESS;
    }
}