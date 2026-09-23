<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Quote;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class ProductMetricsService
{
    /**
     * Retorna as principais métricas de produto para um período.
     */
    public function summary(
        CarbonInterface $start,
        CarbonInterface $end
    ): array {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->endOfDay();

        $quotesCreated = $this->quotesCreatedBetween($start, $end);

        $quotesSent = $this->quotesByDateField(
            'sent_at',
            $start,
            $end
        );

        $quotesViewed = $this->quotesByDateField(
            'first_viewed_at',
            $start,
            $end
        );

        $quotesAccepted = $this->quotesByDateField(
            'accepted_at',
            $start,
            $end
        );

        $quotesRejected = $this->quotesByDateField(
            'rejected_at',
            $start,
            $end
        );

        return [
            'period' => [
                'start' => $start,
                'end' => $end,
            ],

            'users' => [
                'new' => User::query()
                    ->whereBetween('created_at', [$start, $end])
                    ->count(),

                /*
                 * Nesta primeira versão, "ativo" significa:
                 * usuário cuja empresa criou ao menos uma proposta
                 * dentro do período.
                 */
                'active' => User::query()
                    ->whereHas('business.quotes', function (Builder $query) use ($start, $end) {
                        $query->whereBetween(
                            'created_at',
                            [$start, $end]
                        );
                    })
                    ->count(),
            ],

            'businesses' => [
                'created' => Business::query()
                    ->whereBetween('created_at', [$start, $end])
                    ->count(),
            ],

            'quotes' => [
                'created' => $quotesCreated,
                'sent' => $quotesSent,
                'viewed' => $quotesViewed,
                'accepted' => $quotesAccepted,
                'rejected' => $quotesRejected,
            ],

            'conversion' => [
                'created_to_sent' => $this->percentage(
                    $quotesSent,
                    $quotesCreated
                ),

                'sent_to_viewed' => $this->percentage(
                    $quotesViewed,
                    $quotesSent
                ),

                'sent_to_accepted' => $this->percentage(
                    $quotesAccepted,
                    $quotesSent
                ),

                'viewed_to_accepted' => $this->percentage(
                    $quotesAccepted,
                    $quotesViewed
                ),
            ],

            'timing' => [
                'average_hours_to_first_quote' =>
                    $this->averageHoursToFirstQuote(
                        $start,
                        $end
                    ),

                'average_hours_sent_to_viewed' =>
                    $this->averageHoursBetween(
                        'sent_at',
                        'first_viewed_at',
                        $start,
                        $end
                    ),

                'average_hours_sent_to_accepted' =>
                    $this->averageHoursBetween(
                        'sent_at',
                        'accepted_at',
                        $start,
                        $end
                    ),
            ],
        ];
    }

    private function quotesCreatedBetween(
        CarbonInterface $start,
        CarbonInterface $end
    ): int {
        return Quote::query()
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    private function quotesByDateField(
        string $field,
        CarbonInterface $start,
        CarbonInterface $end
    ): int {
        return Quote::query()
            ->whereNotNull($field)
            ->whereBetween($field, [$start, $end])
            ->count();
    }

    private function percentage(
        int $value,
        int $total
    ): float {
        if ($total === 0) {
            return 0.0;
        }

        return round(($value / $total) * 100, 2);
    }

    /**
     * Tempo médio entre cadastro do usuário
     * e criação da primeira proposta.
     *
     * Considera usuários cadastrados no período.
     */
    private function averageHoursToFirstQuote(
        CarbonInterface $start,
        CarbonInterface $end
    ): ?float {
        $users = User::query()
            ->whereBetween('created_at', [$start, $end])
            ->with([
                'business' => fn($query) =>
                    $query->with([
                        'quotes' => fn($query) =>
                            $query->orderBy('created_at'),
                    ]),
            ])
            ->get();

        $hours = [];

        foreach ($users as $user) {
            $firstQuote = $user->business
                ?->quotes
                    ?->first();

            if (!$firstQuote) {
                continue;
            }

            $hours[] = $user->created_at
                ->diffInMinutes(
                    $firstQuote->created_at
                ) / 60;
        }

        if ($hours === []) {
            return null;
        }

        return round(
            array_sum($hours) / count($hours),
            2
        );
    }

    /**
     * Tempo médio entre dois eventos da proposta.
     *
     * O período é definido pelo segundo evento,
     * pois é nele que a conversão efetivamente aconteceu.
     */
    private function averageHoursBetween(
        string $from,
        string $to,
        CarbonInterface $start,
        CarbonInterface $end
    ): ?float {
        $quotes = Quote::query()
            ->whereNotNull($from)
            ->whereNotNull($to)
            ->whereBetween($to, [$start, $end])
            ->get([$from, $to]);

        if ($quotes->isEmpty()) {
            return null;
        }

        $hours = $quotes
            ->map(function (Quote $quote) use ($from, $to) {
                return $quote->{$from}
                    ->diffInMinutes($quote->{$to}) / 60;
            });

        return round($hours->average(), 2);
    }
    public function dailySeries(
        CarbonInterface $start,
        CarbonInterface $end
    ): array {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->endOfDay();

        $days = collect();

        $current = $start->copy();

        while ($current->lte($end)) {
            $key = $current->format('Y-m-d');

            $days->put($key, [
                'date' => $key,
                'label' => $current->format('d/m'),
                'created' => 0,
                'sent' => 0,
                'viewed' => 0,
                'accepted' => 0,
            ]);

            /*
             * Importante:
             * funciona tanto com Carbon mutável
             * quanto com CarbonImmutable.
             */
            $current = $current->addDay();
        }

        $this->fillDailySeries(
            $days,
            'created_at',
            'created',
            $start,
            $end
        );

        $this->fillDailySeries(
            $days,
            'sent_at',
            'sent',
            $start,
            $end
        );

        $this->fillDailySeries(
            $days,
            'first_viewed_at',
            'viewed',
            $start,
            $end
        );

        $this->fillDailySeries(
            $days,
            'accepted_at',
            'accepted',
            $start,
            $end
        );

        return [
            'labels' => $days
                ->pluck('label')
                ->values()
                ->all(),

            'created' => $days
                ->pluck('created')
                ->values()
                ->all(),

            'sent' => $days
                ->pluck('sent')
                ->values()
                ->all(),

            'viewed' => $days
                ->pluck('viewed')
                ->values()
                ->all(),

            'accepted' => $days
                ->pluck('accepted')
                ->values()
                ->all(),
        ];
    }

    private function fillDailySeries(
        $days,
        string $field,
        string $target,
        CarbonInterface $start,
        CarbonInterface $end
    ): void {
        Quote::query()
            ->whereNotNull($field)
            ->whereBetween($field, [$start, $end])
            ->get([$field])
            ->each(function (Quote $quote) use ($days, $field, $target) {
                $date = $quote->{$field}->format('Y-m-d');

                if (!$days->has($date)) {
                    return;
                }

                $item = $days->get($date);

                $item[$target]++;

                $days->put($date, $item);
            });
    }
    public function engagement(
        CarbonInterface $start,
        CarbonInterface $end
    ): array {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->endOfDay();

        $activation = $this->activationMetrics(
            $start,
            $end
        );

        $retention7 = $this->retentionMetrics(
            $start,
            $end,
            7
        );

        $retention30 = $this->retentionMetrics(
            $start,
            $end,
            30
        );

        return [
            'activation' => $activation,

            'retention' => [
                'd7' => $retention7,
                'd30' => $retention30,
            ],
        ];
    }

    private function activationMetrics(
        CarbonInterface $start,
        CarbonInterface $end
    ): array {
        /*
         * Só entram no denominador usuários que
         * já tiveram as 24 horas completas para ativar.
         */
        $eligibilityEnd = $end
            ->copy()
            ->subHours(24);

        if ($eligibilityEnd->lt($start)) {
            return [
                'eligible' => 0,
                'activated' => 0,
                'rate' => 0.0,
            ];
        }

        $users = User::query()
            ->whereBetween(
                'created_at',
                [$start, $eligibilityEnd]
            )
            ->with([
                'business' => fn($query) =>
                    $query->with([
                        'quotes' => fn($query) =>
                            $query->orderBy('created_at'),
                    ]),
            ])
            ->get();

        $total = $users->count();

        $activated = $users
            ->filter(function (User $user) {
                $firstQuote = $user
                    ->business
                    ?->quotes
                        ?->first();

                if (!$firstQuote) {
                    return false;
                }

                $activationDeadline = $user
                    ->created_at
                    ->copy()
                    ->addHours(24);

                return $firstQuote
                    ->created_at
                    ->between(
                        $user->created_at,
                        $activationDeadline
                    );
            })
            ->count();

        return [
            'eligible' => $total,
            'activated' => $activated,
            'rate' => $this->percentage(
                $activated,
                $total
            ),
        ];
    }
    private function retentionMetrics(
        CarbonInterface $start,
        CarbonInterface $end,
        int $day
    ): array {

        /*
         * A janela possui sete dias completos:
         *
         * D7  = dias 7 a 13
         * D30 = dias 30 a 36
         *
         * Portanto, só avaliamos quem já teve
         * toda a janela disponível.
         */
        $eligibilityEnd = $end
            ->copy()
            ->subDays($day + 6);
        if ($eligibilityEnd->lt($start)) {
            return [
                'eligible' => 0,
                'retained' => 0,
                'rate' => 0.0,
            ];
        }

        $users = User::query()
            ->whereBetween(
                'created_at',
                [$start, $eligibilityEnd]
            )
            ->with([
                'business' => fn($query) =>
                    $query->with([
                        'quotes' => fn($query) =>
                            $query->orderBy('created_at'),
                    ]),
            ])
            ->get();

        /*
         * Para retenção, primeiro exigimos que o
         * usuário tenha sido ativado em até 24h.
         */
        $eligibleUsers = $users
            ->filter(function (User $user) {
                $firstQuote = $user
                    ->business
                    ?->quotes
                        ?->first();

                if (!$firstQuote) {
                    return false;
                }

                $activationDeadline = $user
                    ->created_at
                    ->copy()
                    ->addHours(24);

                return $firstQuote
                    ->created_at
                    ->between(
                        $user->created_at,
                        $activationDeadline
                    );
            });

        $eligible = $eligibleUsers->count();

        $retained = $eligibleUsers
            ->filter(function (User $user) use ($day) {
                if (!$user->business) {
                    return false;
                }

                $windowStart = $user
                    ->created_at
                    ->copy()
                    ->addDays($day)
                    ->startOfDay();

                /*
                 * Janela de sete dias:
                 *
                 * D7  = dias 7 a 13
                 * D30 = dias 30 a 36
                 */
                $windowEnd = $windowStart
                    ->copy()
                    ->addDays(6)
                    ->endOfDay();

                return $user
                    ->business
                    ->quotes
                    ->contains(function (Quote $quote) use ($windowStart, $windowEnd) {
                        return $quote
                            ->created_at
                            ->between(
                                $windowStart,
                                $windowEnd
                            );
                    });
            })
            ->count();

        return [
            'eligible' => $eligible,
            'retained' => $retained,
            'rate' => $this->percentage(
                $retained,
                $eligible
            ),
        ];
    }
}