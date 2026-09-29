<?php

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessAccessGrant;

class PlatformOverviewService
{
    public function summary(): array
    {
        $businesses = Business::query()
            ->with([
                'user',
                'currentSubscription.plan',
            ])
            ->get();


        /*
        |--------------------------------------------------------------------------
        | Empresas / atividade
        |--------------------------------------------------------------------------
        */

        $total = $businesses->count();

        $activeNow = $businesses
            ->filter(
                fn (Business $business) =>
                    $business->user?->last_seen_at
                        ?->gte(now()->subMinutes(15))
                    === true
            )
            ->count();

        $activeToday = $businesses
            ->filter(
                fn (Business $business) =>
                    $business->user?->last_seen_at
                        ?->gte(now()->startOfDay())
                    === true
            )
            ->count();

        $activeSevenDays = $businesses
            ->filter(
                fn (Business $business) =>
                    $business->user?->last_seen_at
                        ?->gte(now()->subDays(7))
                    === true
            )
            ->count();


        /*
        |--------------------------------------------------------------------------
        | Planos contratados
        |--------------------------------------------------------------------------
        */

        $free = 0;
        $pro = 0;
        $mrr = 0.0;


        /*
        |--------------------------------------------------------------------------
        | Saúde das assinaturas
        |--------------------------------------------------------------------------
        */

        $health = [
            'pro_active' => 0,
            'payment_pending' => 0,
            'grace_period' => 0,
            'scheduled_cancellation' => 0,
            'suspended' => 0,
            'without_subscription' => 0,
        ];

        $attention = collect();


        foreach ($businesses as $business) {

            $subscription =
                $business->currentSubscription;

            $plan =
                $subscription?->plan;


            /*
             * Empresa sem assinatura atual.
             */
            if (!$subscription || !$plan) {
                $health['without_subscription']++;

                continue;
            }


            /*
             * Plano contratado.
             */
            if ($plan->isFree()) {
                $free++;
            } else {
                $pro++;
            }


            /*
             * Pro financeiramente ativo.
             */
            if (
                !$plan->isFree()
                && $subscription->status === 'active'
            ) {
                $health['pro_active']++;

                $price = (float) $plan->price;

                $mrr += match (
                    $plan->billing_interval
                ) {
                    'year',
                    'yearly',
                    'annual' =>
                        $price / 12,

                    default =>
                        $price,
                };
            }


            /*
             * Pagamento pendente.
             */
            if (
                !$plan->isFree()
                && $subscription->status === 'past_due'
            ) {
                $health['payment_pending']++;


                /*
                 * Suspensão tem prioridade visual
                 * sobre simples pagamento pendente.
                 */
                if (
                    $subscription
                        ->access_suspended_at
                    !== null
                ) {
                    $health['suspended']++;

                    $attention->push([
                        'type' =>
                            'suspended',

                        'level' =>
                            'danger',

                        'priority' =>
                            1,

                        'title' =>
                            'Acesso suspenso',

                        'business_id' =>
                            $business->id,

                        'business_name' =>
                            $business->name,

                        'detail' =>
                            'Acesso Pro suspenso por pendência de cobrança.',

                        'date' =>
                            $subscription
                                ->access_suspended_at,
                    ]);
                } else {

                    if (
                        $subscription
                            ->isInGracePeriod()
                    ) {
                        $health['grace_period']++;

                        $detail =
                            'Em tolerância até '
                            . $subscription
                                ->grace_ends_at
                                ?->format(
                                    'd/m/Y H:i'
                                );
                    } else {
                        $detail =
                            'Pagamento pendente fora do período de tolerância.';
                    }


                    $attention->push([
                        'type' =>
                            'payment_pending',

                        'level' =>
                            'warning',

                        'priority' =>
                            2,

                        'title' =>
                            'Pagamento pendente',

                        'business_id' =>
                            $business->id,

                        'business_name' =>
                            $business->name,

                        'detail' =>
                            $detail,

                        'date' =>
                            $subscription
                                ->grace_ends_at
                            ?? $subscription
                                ->past_due_at,
                    ]);
                }
            }


            /*
             * Cancelamento agendado.
             */
            if (
                !$plan->isFree()
                && $subscription
                    ->hasScheduledCancellation()
            ) {
                $health[
                    'scheduled_cancellation'
                ]++;

                $attention->push([
                    'type' =>
                        'scheduled_cancellation',

                    'level' =>
                        'neutral',

                    'priority' =>
                        3,

                    'title' =>
                        'Cancelamento agendado',

                    'business_id' =>
                        $business->id,

                    'business_name' =>
                        $business->name,

                    'detail' =>
                        'Acesso Pro previsto até '
                        . $subscription
                            ->ends_at
                            ?->format(
                                'd/m/Y H:i'
                            ),

                    'date' =>
                        $subscription->ends_at,
                ]);
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Cortesias
        |--------------------------------------------------------------------------
        */

        $activeGrants =
            BusinessAccessGrant::query()
                ->active();

        $courtesies = (
            clone $activeGrants
        )
            ->distinct()
            ->count('business_id');


        /*
         * Cortesias vencendo nos próximos 7 dias.
         */
        $expiringCourtesy = (
            clone $activeGrants
        )
            ->with([
                'business:id,name',
                'plan:id,name,slug',
            ])
            ->where(
                'ends_at',
                '<=',
                now()->addDays(7)
            )
            ->orderBy('ends_at')
            ->get()
            ->unique('business_id');


        foreach ($expiringCourtesy as $grant) {
            if (!$grant->business) {
                continue;
            }

            $attention->push([
                'type' =>
                    'courtesy_expiring',

                'level' =>
                    'info',

                'priority' =>
                    4,

                'title' =>
                    'Cortesia perto de expirar',

                'business_id' =>
                    $grant->business->id,

                'business_name' =>
                    $grant->business->name,

                'detail' =>
                    ($grant->plan?->name ?? 'Acesso')
                    . ' válido até '
                    . $grant
                        ->ends_at
                        ->format(
                            'd/m/Y H:i'
                        ),

                'date' =>
                    $grant->ends_at,
            ]);
        }


        /*
         * Ordenação das pendências:
         *
         * 1. suspenso
         * 2. cobrança pendente
         * 3. cancelamento
         * 4. cortesia expirando
         */
        $attention = $attention
            ->sortBy(
                function (array $item): string {
                    $date =
                        $item['date']
                            ?->format(
                                'YmdHis'
                            )
                        ?? '99999999999999';

                    return str_pad(
                        (string) $item['priority'],
                        2,
                        '0',
                        STR_PAD_LEFT
                    ) . '-' . $date;
                }
            )
            ->take(10)
            ->values()
            ->map(function (array $item) {
                unset(
                    $item['priority']
                );

                return $item;
            })
            ->all();


        return [
            'businesses' => [
                'total' =>
                    $total,

                'active_now' =>
                    $activeNow,

                'active_today' =>
                    $activeToday,

                'active_7_days' =>
                    $activeSevenDays,
            ],

            'plans' => [
                'free' =>
                    $free,

                'pro' =>
                    $pro,

                'courtesy' =>
                    $courtesies,
            ],

            'health' => [
                ...$health,

                'courtesy' =>
                    $courtesies,
            ],

            'revenue' => [
                'mrr' =>
                    round(
                        $mrr,
                        2
                    ),
            ],

            'attention' =>
                $attention,
        ];
    }
}
