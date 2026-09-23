<?php

use App\Enums\PlanFeature;
use App\Models\Quote;
use App\Services\SubscriptionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard | Fechou')]
    class extends Component {
    /*
    |--------------------------------------------------------------------------
    | ONBOARDING INICIAL - DASHBOARD
    |--------------------------------------------------------------------------
    */

    public function mount(): void
    {
        $business = Auth::user()->business;

        if (
            !$business
            || !$business->onboarding_completed_at
        ) {
            $this->redirectRoute(
                'onboarding',
                navigate: true
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Empresa
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function business()
    {
        return Auth::user()->business;
    }

    /*
    |--------------------------------------------------------------------------
    | Recursos do plano
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function canUseFollowUp(): bool
    {
        if (!$this->business) {
            return false;
        }

        return app(SubscriptionService::class)
            ->hasFeature(
                $this->business,
                PlanFeature::FOLLOW_UP
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Apenas a versão mais recente de cada proposta
    |--------------------------------------------------------------------------
    |
    | Exemplo:
    |
    | #0012 V1 - enviado
    | #0021 V2 - rascunho
    |
    | Para os indicadores comerciais consideramos somente V2.
    |
    */

    private function latestFamilyQuery(): Builder
    {
        return Quote::query()
            ->where(
                'business_id',
                $this->business->id
            )
            ->whereNotExists(
                function ($subQuery) {
                    $subQuery
                        ->selectRaw('1')
                        ->from('quotes as newer')
                        ->whereColumn(
                            'newer.business_id',
                            'quotes.business_id'
                        )
                        ->whereNull(
                            'newer.deleted_at'
                        )
                        ->whereRaw(
                            '
                        COALESCE(
                            newer.root_quote_id,
                            newer.id
                        )
                        =
                        COALESCE(
                            quotes.root_quote_id,
                            quotes.id
                        )
                        '
                        )
                        ->whereColumn(
                            'newer.version',
                            '>',
                            'quotes.version'
                        );
                }
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Desempenho do negócio
    |--------------------------------------------------------------------------
    |
    | Considera somente a versão mais recente
    | de cada família de proposta.
    |
    */

    private function performanceEventAt(
        $quote,
        string $type,
        $fallback = null
    ) {
        $event = $quote
            ->events
            ->firstWhere(
                'type',
                $type
            );

        return $event?->created_at
            ?? $fallback;
    }


    private function performanceSeconds(
        $from,
        $to
    ): ?int {
        if (
            ! $from
            || ! $to
        ) {
            return null;
        }

        if ($to->lt($from)) {
            return null;
        }

        return (int) round(
            $from->diffInSeconds(
                $to
            )
        );
    }


    private function formatPerformanceDuration(
        int $seconds
    ): string {
        if ($seconds < 60) {
            return $seconds . ' s';
        }

        $minutes = intdiv(
            $seconds,
            60
        );

        if ($minutes < 60) {
            return $minutes . ' min';
        }

        $hours = intdiv(
            $minutes,
            60
        );

        $remainingMinutes =
            $minutes % 60;

        if ($hours < 24) {

            if ($remainingMinutes === 0) {
                return $hours . ' h';
            }

            return $hours
                . ' h '
                . $remainingMinutes
                . ' min';
        }

        $days = intdiv(
            $hours,
            24
        );

        $remainingHours =
            $hours % 24;

        if ($remainingHours === 0) {
            return $days . ' d';
        }

        return $days
            . ' d '
            . $remainingHours
            . ' h';
    }


    #[Computed]
    public function businessPerformance(): array
    {
        if (! $this->business) {
            return [];
        }

        $quotes = (
            clone $this->latestFamilyQuery()
        )
            ->with([
                'events' =>
                    fn ($query) =>
                        $query
                            ->orderBy(
                                'created_at'
                            )
                            ->orderBy(
                                'id'
                            ),
            ])
            ->get();


        $durations = [
            'viewed' => [],
            'accepted' => [],
            'payment' => [],
            'completed' => [],
        ];


        foreach ($quotes as $quote) {

            $createdAt =
                $this->performanceEventAt(
                    $quote,
                    'created',
                    $quote->created_at
                );


            $viewedAt =
                $this->performanceEventAt(
                    $quote,
                    'viewed'
                );

            $viewedSeconds =
                $this->performanceSeconds(
                    $createdAt,
                    $viewedAt
                );

            if ($viewedSeconds !== null) {
                $durations['viewed'][] =
                    $viewedSeconds;
            }


            $acceptedAt =
                $this->performanceEventAt(
                    $quote,
                    'accepted',
                    $quote->accepted_at
                );

            $acceptedSeconds =
                $this->performanceSeconds(
                    $createdAt,
                    $acceptedAt
                );

            if ($acceptedSeconds !== null) {
                $durations['accepted'][] =
                    $acceptedSeconds;
            }


            $paidAt =
                $this->performanceEventAt(
                    $quote,
                    'payment_received',
                    $quote->paid_at
                );

            $paymentSeconds =
                $this->performanceSeconds(
                    $acceptedAt,
                    $paidAt
                );

            if ($paymentSeconds !== null) {
                $durations['payment'][] =
                    $paymentSeconds;
            }


            $completedAt =
                $this->performanceEventAt(
                    $quote,
                    'execution_completed',
                    $quote->completed_at
                );

            $completedSeconds =
                $this->performanceSeconds(
                    $createdAt,
                    $completedAt
                );

            if ($completedSeconds !== null) {
                $durations['completed'][] =
                    $completedSeconds;
            }
        }


        $buildMetric =
            function (
                string $key,
                string $label,
                string $context
            ) use ($durations): array {

                $values =
                    $durations[$key];

                $count =
                    count($values);

                if ($count === 0) {
                    return [
                        'key' => $key,
                        'label' => $label,
                        'context' => $context,
                        'value' => '—',
                        'count' => 0,
                    ];
                }

                $average =
                    (int) round(
                        array_sum($values)
                        / $count
                    );

                return [
                    'key' => $key,
                    'label' => $label,
                    'context' => $context,

                    'value' =>
                        $this
                            ->formatPerformanceDuration(
                                $average
                            ),

                    'count' =>
                        $count,
                ];
            };


        return [

            $buildMetric(
                'viewed',
                'Tempo médio até visualizar',
                'Criação → visualização'
            ),

            $buildMetric(
                'accepted',
                'Tempo médio até aceitar',
                'Criação → aceite'
            ),

            $buildMetric(
                'payment',
                'Tempo médio até pagamento',
                'Aceite → pagamento'
            ),

            $buildMetric(
                'completed',
                'Ciclo médio do negócio',
                'Criação → conclusão'
            ),

        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Conversão comercial
    |--------------------------------------------------------------------------
    |
    | Utiliza somente a versão mais recente
    | de cada família de proposta.
    |
    | Rascunhos não entram no funil.
    |
    */

    private function commercialRate(
        int $numerator,
        int $denominator
    ): ?float {
        if ($denominator === 0) {
            return null;
        }

        return round(
            (
                $numerator
                / $denominator
            ) * 100,
            1
        );
    }


    private function formatCommercialRate(
        ?float $rate
    ): string {
        if ($rate === null) {
            return '—';
        }

        if (
            floor($rate) === $rate
        ) {
            return number_format(
                $rate,
                0,
                ',',
                '.'
            ) . '%';
        }

        return number_format(
            $rate,
            1,
            ',',
            '.'
        ) . '%';
    }


    #[Computed]
    public function commercialConversion(): array
    {
        if (! $this->business) {
            return [];
        }


        $quotes = (
            clone $this->latestFamilyQuery()
        )
            ->with([
                'events' =>
                    fn ($query) =>
                        $query
                            ->select([
                                'id',
                                'quote_id',
                                'type',
                                'created_at',
                            ]),
            ])
            ->get();


        /*
         * Chegou ao envio:
         *
         * qualquer proposta cuja versão atual
         * esteja em uma etapa posterior ao rascunho.
         */
        $sentQuotes =
            $quotes->filter(
                fn ($quote) =>
                    in_array(
                        $quote->status,
                        [
                            'sent',
                            'viewed',
                            'accepted',
                            'rejected',
                            'expired',
                        ],
                        true
                    )
            );


        $sentCount =
            $sentQuotes->count();


        /*
         * Visualizada:
         *
         * - possui evento viewed;
         * - ou está atualmente como viewed;
         * - ou chegou a aceite/recusa, o que
         *   pressupõe interação com a proposta.
         *
         * Assim também preservamos casos históricos
         * em que o evento viewed não existia ainda.
         */
        $viewedCount =
            $sentQuotes
                ->filter(
                    function ($quote) {

                        if (
                            in_array(
                                $quote->status,
                                [
                                    'viewed',
                                    'accepted',
                                    'rejected',
                                ],
                                true
                            )
                        ) {
                            return true;
                        }

                        return $quote
                            ->events
                            ->contains(
                                'type',
                                'viewed'
                            );
                    }
                )
                ->count();


        $acceptedCount =
            $quotes
                ->where(
                    'status',
                    'accepted'
                )
                ->count();


        $rejectedCount =
            $quotes
                ->where(
                    'status',
                    'rejected'
                )
                ->count();


        /*
         * Decisão significa que o cliente
         * efetivamente aceitou ou recusou.
         *
         * Enviadas, visualizadas e expiradas
         * não são tratadas como recusa.
         */
        $decisionCount =
            $acceptedCount
            + $rejectedCount;


        $viewRate =
            $this->commercialRate(
                $viewedCount,
                $sentCount
            );


        $acceptRate =
            $this->commercialRate(
                $acceptedCount,
                $decisionCount
            );


        $conversionRate =
            $this->commercialRate(
                $acceptedCount,
                $sentCount
            );


        $rejectRate =
            $this->commercialRate(
                $rejectedCount,
                $decisionCount
            );


        return [

            [
                'key' =>
                    'viewed',

                'label' =>
                    'Taxa de visualização',

                'context' =>
                    'Visualizadas / enviadas',

                'value' =>
                    $this->formatCommercialRate(
                        $viewRate
                    ),

                'percentage' =>
                    $viewRate,

                'numerator' =>
                    $viewedCount,

                'denominator' =>
                    $sentCount,

                'base' =>
                    'propostas',
            ],

            [
                'key' =>
                    'accepted',

                'label' =>
                    'Taxa de aceite',

                'context' =>
                    'Aceitas / decisões',

                'value' =>
                    $this->formatCommercialRate(
                        $acceptRate
                    ),

                'percentage' =>
                    $acceptRate,

                'numerator' =>
                    $acceptedCount,

                'denominator' =>
                    $decisionCount,

                'base' =>
                    'decisões',
            ],

            [
                'key' =>
                    'conversion',

                'label' =>
                    'Conversão em negócio',

                'context' =>
                    'Aceitas / enviadas',

                'value' =>
                    $this->formatCommercialRate(
                        $conversionRate
                    ),

                'percentage' =>
                    $conversionRate,

                'numerator' =>
                    $acceptedCount,

                'denominator' =>
                    $sentCount,

                'base' =>
                    'propostas',
            ],

            [
                'key' =>
                    'rejected',

                'label' =>
                    'Taxa de recusa',

                'context' =>
                    'Recusadas / decisões',

                'value' =>
                    $this->formatCommercialRate(
                        $rejectRate
                    ),

                'percentage' =>
                    $rejectRate,

                'numerator' =>
                    $rejectedCount,

                'denominator' =>
                    $decisionCount,

                'base' =>
                    'decisões',
            ],

        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Visão compacta do desempenho comercial
    |--------------------------------------------------------------------------
    |
    | Apenas organiza as métricas que já existem.
    | Nenhuma regra de cálculo é duplicada aqui.
    |
    */

    #[Computed]
    public function commercialOverview(): array
    {
        $conversion = collect(
            $this->commercialConversion
        )->keyBy('key');

        $performance = collect(
            $this->businessPerformance
        )->keyBy('key');


        $rateMetric =
            function (
                string $key,
                string $label
            ) use ($conversion): array {

                $metric =
                    $conversion->get(
                        $key
                    );

                if (! $metric) {
                    return [
                        'key' => $key,
                        'label' => $label,
                        'value' => '—',
                        'context' => '',
                        'support' =>
                            'Sem dados suficientes',
                    ];
                }


                $support =
                    $metric['denominator'] > 0
                        ? (
                            $metric['numerator']
                            . ' de '
                            . $metric['denominator']
                            . ' '
                            . $metric['base']
                        )
                        : 'Sem dados suficientes';


                return [
                    'key' => $key,
                    'label' => $label,

                    'value' =>
                        $metric['value'],

                    'context' =>
                        $metric['context'],

                    'support' =>
                        $support,
                ];
            };


        $durationMetric =
            function (
                string $key,
                string $label
            ) use ($performance): array {

                $metric =
                    $performance->get(
                        $key
                    );

                if (! $metric) {
                    return [
                        'key' => $key,
                        'label' => $label,
                        'value' => '—',
                        'context' => '',
                        'support' =>
                            'Sem dados suficientes',
                    ];
                }


                if ($metric['count'] === 0) {
                    $support =
                        'Sem dados suficientes';
                } else {
                    $support =
                        'Baseado em '
                        . $metric['count']
                        . ' '
                        . (
                            $metric['count'] === 1
                                ? 'proposta'
                                : 'propostas'
                        );
                }


                return [
                    'key' => $key,
                    'label' => $label,

                    'value' =>
                        $metric['value'],

                    'context' =>
                        $metric['context'],

                    'support' =>
                        $support,
                ];
            };


        return [

            /*
             * O que fica sempre visível.
             */
            'primary' => [

                $rateMetric(
                    'conversion',
                    'Conversão em negócio'
                ),

                $rateMetric(
                    'viewed',
                    'Taxa de visualização'
                ),

                $durationMetric(
                    'accepted',
                    'Tempo médio até aceitar'
                ),

                $durationMetric(
                    'completed',
                    'Ciclo médio do negócio'
                ),

            ],


            /*
             * Métricas de conversão que ficam
             * dentro de "Ver detalhes".
             */
            'conversion_details' => [

                $rateMetric(
                    'accepted',
                    'Taxa de aceite'
                ),

                $rateMetric(
                    'rejected',
                    'Taxa de recusa'
                ),

            ],


            /*
             * Métricas de tempo secundárias.
             */
            'performance_details' => [

                $durationMetric(
                    'viewed',
                    'Tempo médio até visualizar'
                ),

                $durationMetric(
                    'payment',
                    'Tempo médio até pagamento'
                ),

            ],

        ];
    }



    private function attentionBaseQuery(): Builder
    {
        if (
            !$this->business
            || !$this->canUseFollowUp
            || !$this->business->follow_up_enabled
        ) {
            return Quote::query()
                ->whereRaw('1 = 0');
        }

        $sentThreshold = now()->subDays(
            $this->business->follow_up_sent_after_days
            ?? 2
        );

        $viewedThreshold = now()->subDays(
            $this->business->follow_up_viewed_after_days
            ?? 2
        );

        $cooldownThreshold = now()->subHours(
            $this->business->follow_up_cooldown_hours
            ?? 24
        );

        $today = now()
            ->startOfDay()
            ->toDateString();

        $expiryEnd = now()
            ->startOfDay()
            ->addDays(
                $this->business->follow_up_expiry_warning_days
                ?? 1
            )
            ->toDateString();

        return (clone $this->latestFamilyQuery())

            ->whereIn(
                'status',
                ['sent', 'viewed']
            )

            ->where(function ($query) use ($sentThreshold, $viewedThreshold, $today, $expiryEnd) {

                /*
                 * Próxima do vencimento.
                 */
                $query->whereBetween(
                    'valid_until',
                    [
                        $today,
                        $expiryEnd,
                    ]
                );

                /*
                 * Visualizada e sem resposta.
                 */
                $query->orWhere(
                    function ($query) use ($viewedThreshold) {
                        $query
                            ->where(
                                'status',
                                'viewed'
                            )
                            ->whereNotNull(
                                'first_viewed_at'
                            )
                            ->where(
                                'first_viewed_at',
                                '<=',
                                $viewedThreshold
                            );
                    }
                );

                /*
                 * Enviada e ainda não aberta.
                 */
                $query->orWhere(
                    function ($query) use ($sentThreshold) {
                        $query
                            ->where(
                                'status',
                                'sent'
                            )
                            ->whereNotNull(
                                'sent_at'
                            )
                            ->where(
                                'sent_at',
                                '<=',
                                $sentThreshold
                            );
                    }
                );
            })

            /*
             * Se houve follow-up recentemente,
             * não volta para a fila ainda.
             */
            ->whereDoesntHave(
                'events',
                function ($query) use ($cooldownThreshold) {
                    $query
                        ->where(
                            'type',
                            'follow_up'
                        )
                        ->where(
                            'created_at',
                            '>=',
                            $cooldownThreshold
                        );
                }
            );
    }
    /*
    |--------------------------------------------------------------------------
    | Indicadores
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function waitingCount(): int
    {
        if (!$this->business) {
            return 0;
        }

        return (clone $this->latestFamilyQuery())
            ->whereIn(
                'status',
                ['sent', 'viewed']
            )
            ->count();
    }

    #[Computed]
    public function waitingAmount(): float
    {
        if (!$this->business) {
            return 0;
        }

        return (float) (clone $this->latestFamilyQuery())
            ->whereIn(
                'status',
                ['sent', 'viewed']
            )
            ->sum('total');
    }

    #[Computed]
    public function viewedCount(): int
    {
        if (!$this->business) {
            return 0;
        }

        return (clone $this->latestFamilyQuery())
            ->where('status', 'viewed')
            ->count();
    }

    #[Computed]
    public function acceptedCount(): int
    {
        if (!$this->business) {
            return 0;
        }

        return (clone $this->latestFamilyQuery())
            ->where('status', 'accepted')
            ->count();
    }

    #[Computed]
    public function acceptedAmount(): float
    {
        if (!$this->business) {
            return 0;
        }

        return (float) (clone $this->latestFamilyQuery())
            ->where('status', 'accepted')
            ->sum('total');
    }

    /*
    |--------------------------------------------------------------------------
    | Precisam de atenção
    |--------------------------------------------------------------------------
    |
    | Regras iniciais:
    |
    | - proposta vence hoje;
    | - proposta vence amanhã;
    | - visualizada há 2 dias ou mais sem resposta;
    | - enviada há 2 dias ou mais sem visualização;
    |
    | Depois de um follow-up, escondemos por 24 horas.
    |
    */


    /*
    |--------------------------------------------------------------------------
    | Pós-aceite
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function closedDealsCount(): int
    {
        if (! $this->business) {
            return 0;
        }

        return (clone $this->latestFamilyQuery())
            ->where(
                'status',
                'accepted'
            )
            ->count();
    }


    #[Computed]
    public function closedDealsAmount(): float
    {
        if (! $this->business) {
            return 0;
        }

        return (float) (
            clone $this->latestFamilyQuery()
        )
            ->where(
                'status',
                'accepted'
            )
            ->sum('total');
    }


    #[Computed]
    public function receivableCount(): int
    {
        if (! $this->business) {
            return 0;
        }

        return (clone $this->latestFamilyQuery())
            ->where(
                'status',
                'accepted'
            )
            ->where(
                function ($query) {
                    $query
                        ->whereNull(
                            'payment_status'
                        )
                        ->orWhere(
                            'payment_status',
                            '!=',
                            'paid'
                        );
                }
            )
            ->count();
    }


    #[Computed]
    public function receivableAmount(): float
    {
        if (! $this->business) {
            return 0;
        }

        return (float) (
            clone $this->latestFamilyQuery()
        )
            ->where(
                'status',
                'accepted'
            )
            ->where(
                function ($query) {
                    $query
                        ->whereNull(
                            'payment_status'
                        )
                        ->orWhere(
                            'payment_status',
                            '!=',
                            'paid'
                        );
                }
            )
            ->sum('total');
    }


    #[Computed]
    public function awaitingExecutionCount(): int
    {
        if (! $this->business) {
            return 0;
        }

        return (clone $this->latestFamilyQuery())
            ->where(
                'status',
                'accepted'
            )
            ->where(
                function ($query) {
                    $query
                        ->whereNull(
                            'execution_status'
                        )
                        ->orWhere(
                            'execution_status',
                            'pending'
                        );
                }
            )
            ->count();
    }


    #[Computed]
    public function inExecutionCount(): int
    {
        if (! $this->business) {
            return 0;
        }

        return (clone $this->latestFamilyQuery())
            ->where(
                'status',
                'accepted'
            )
            ->where(
                'execution_status',
                'in_progress'
            )
            ->count();
    }


    #[Computed]
    public function completedDealsCount(): int
    {
        if (! $this->business) {
            return 0;
        }

        return (clone $this->latestFamilyQuery())
            ->where(
                'status',
                'accepted'
            )
            ->where(
                'execution_status',
                'completed'
            )
            ->count();
    }


    #[Computed]
    public function postAcceptanceQuotes()
    {
        if (! $this->business) {
            return collect();
        }

        return (clone $this->latestFamilyQuery())
            ->with('client')
            ->where(
                'status',
                'accepted'
            )
            ->where(
                function ($query) {
                    $query

                        /*
                         * Pagamento ainda pendente.
                         */
                        ->where(
                            function ($query) {
                                $query
                                    ->whereNull(
                                        'payment_status'
                                    )
                                    ->orWhere(
                                        'payment_status',
                                        '!=',
                                        'paid'
                                    );
                            }
                        )

                        /*
                         * Ou execução ainda não concluída.
                         */
                        ->orWhere(
                            function ($query) {
                                $query
                                    ->whereNull(
                                        'execution_status'
                                    )
                                    ->orWhere(
                                        'execution_status',
                                        '!=',
                                        'completed'
                                    );
                            }
                        );
                }
            )
            ->orderByDesc('accepted_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();
    }


    #[Computed]
    public function attentionQuotes()
    {
        if (
            !$this->business
            || !$this->canUseFollowUp
            || !$this->business->follow_up_enabled
        ) {
            return collect();
        }

        $today = now()
            ->startOfDay()
            ->toDateString();

        $expiryEnd = now()
            ->startOfDay()
            ->addDays(
                $this->business->follow_up_expiry_warning_days
                ?? 1
            )
            ->toDateString();

        return (clone $this->attentionBaseQuery())
            ->with('client')

            ->orderByRaw(
                "
                CASE
                    WHEN valid_until BETWEEN ? AND ?
                        THEN 1

                    WHEN status = 'viewed'
                        THEN 2

                    ELSE 3
                END
            ",
                [
                    $today,
                    $expiryEnd,
                ]
            )

            ->orderBy('valid_until')
            ->orderBy('first_viewed_at')
            ->orderBy('sent_at')

            ->limit(6)
            ->get();
    }

    #[Computed]
    public function attentionCount(): int
    {
        if (
            !$this->business
            || !$this->canUseFollowUp
            || !$this->business->follow_up_enabled
        ) {
            return 0;
        }

        return (clone $this->attentionBaseQuery())
            ->count();
    }

    private function needsAttention(Quote $quote): bool
    {
        /*
         * Se já fizemos follow-up nas últimas 24h,
         * tiramos temporariamente do Dashboard.
         */
        $lastFollowUp = $quote
            ->events
            ->first();

        if (
            $lastFollowUp
            && $lastFollowUp
                ->created_at
                ->greaterThan(
                    now()->subDay()
                )
        ) {
            return false;
        }

        /*
         * Vence hoje ou amanhã.
         */
        if ($quote->valid_until) {

            $validUntil = $quote
                ->valid_until
                ->copy()
                ->startOfDay();

            if (
                $validUntil->isToday()
                || $validUntil->isTomorrow()
            ) {
                return true;
            }
        }

        /*
         * Cliente visualizou e não respondeu
         * há pelo menos 2 dias.
         */
        if (
            $quote->status === 'viewed'
            && $quote->first_viewed_at
            && $quote
                ->first_viewed_at
                ->lte(
                    now()->subDays(2)
                )
        ) {
            return true;
        }

        /*
         * Foi enviada mas ainda não visualizada.
         */
        if (
            $quote->status === 'sent'
            && $quote->sent_at
            && $quote
                ->sent_at
                ->lte(
                    now()->subDays(2)
                )
        ) {
            return true;
        }

        return false;
    }

    private function attentionPriority(
        Quote $quote
    ): int {
        if ($quote->valid_until) {

            if ($quote->valid_until->isToday()) {
                return 1;
            }

            if ($quote->valid_until->isTomorrow()) {
                return 2;
            }
        }

        if ($quote->status === 'viewed') {
            return 3;
        }

        return 4;
    }

    /*
    |--------------------------------------------------------------------------
    | Texto da atenção
    |--------------------------------------------------------------------------
    */

    public function attentionLabel(
        Quote $quote
    ): string {
        if ($quote->valid_until) {

            $expiry = $quote
                ->valid_until
                ->copy()
                ->startOfDay();

            $today = now()
                ->startOfDay();

            $warningEnd = now()
                ->startOfDay()
                ->addDays(
                    $this->business
                        ->follow_up_expiry_warning_days
                    ?? 1
                );

            if (
                $expiry->betweenIncluded(
                    $today,
                    $warningEnd
                )
            ) {

                if ($expiry->isToday()) {
                    return 'Vence hoje';
                }

                if ($expiry->isTomorrow()) {
                    return 'Vence amanhã';
                }

                $days = $today
                    ->diffInDays($expiry);

                return "Vence em {$days} dias";
            }
        }

        if (
            $quote->status === 'viewed'
            && $quote->first_viewed_at
        ) {
            return 'Visualizado há '
                . $this->daysAgo(
                    $quote->first_viewed_at
                );
        }

        if (
            $quote->status === 'sent'
            && $quote->sent_at
        ) {
            return 'Enviado há '
                . $this->daysAgo(
                    $quote->sent_at
                );
        }

        return 'Precisa de atenção';
    }
    public function attentionDescription(
        Quote $quote
    ): string {
        if (
            $quote->valid_until
            && $quote->valid_until->isToday()
        ) {
            return 'A proposta perde a validade hoje.';
        }

        if (
            $quote->valid_until
            && $quote->valid_until->isTomorrow()
        ) {
            return 'A proposta perde a validade amanhã.';
        }

        if ($quote->status === 'viewed') {
            return 'O cliente ainda não respondeu à proposta.';
        }

        return 'O cliente ainda não visualizou a proposta.';
    }

    private function daysAgo($date): string
    {
        $hours = max(
            1,
            (int) floor(
                $date->diffInHours(now())
            )
        );

        if ($hours < 48) {
            return $hours . ' horas';
        }

        $days = max(
            1,
            (int) floor(
                $hours / 24
            )
        );

        return $days . ' dias';
    }

    /*
    |--------------------------------------------------------------------------
    | WhatsApp do follow-up
    |--------------------------------------------------------------------------
    */

    public function followUpUrl(
        Quote $quote
    ): string {
        $phone = preg_replace(
            '/\D+/',
            '',
            $quote->client->whatsapp
            ?: $quote->client->phone
            ?: ''
        );

        if (
            $phone
            && !str_starts_with(
                $phone,
                '55'
            )
        ) {
            $phone = '55' . $phone;
        }

        $number = str_pad(
            $quote->number,
            4,
            '0',
            STR_PAD_LEFT
        );

        $publicUrl = route(
            'quotes.public',
            [
                'token' =>
                    $quote->public_token,
            ]
        );

        /*
         * Mensagem muda quando a validade
         * está muito próxima.
         */
        if (
            $quote->valid_until
            && $quote->valid_until->isToday()
        ) {
            $message =
                "Olá, {$quote->client->name}! Tudo bem?\n\n"
                . "Passando para lembrar que a proposta "
                . "#{$number} da {$this->business->name} "
                . "vence hoje.\n\n"
                . "Caso queira ajustar algum ponto ou tenha "
                . "alguma dúvida, estou à disposição.\n\n"
                . $publicUrl;
        } elseif (
            $quote->valid_until
            && $quote->valid_until->isTomorrow()
        ) {
            $message =
                "Olá, {$quote->client->name}! Tudo bem?\n\n"
                . "Passando para lembrar que a proposta "
                . "#{$number} da {$this->business->name} "
                . "é válida até amanhã.\n\n"
                . "Caso tenha alguma dúvida ou queira ajustar "
                . "algum ponto, estou à disposição.\n\n"
                . $publicUrl;
        } else {
            $message =
                "Olá, {$quote->client->name}! Tudo bem?\n\n"
                . "Passando para saber se conseguiu analisar "
                . "a proposta #{$number} que enviamos.\n\n"
                . "Caso tenha alguma dúvida ou queira ajustar "
                . "algum ponto, estou à disposição.\n\n"
                . $publicUrl;
        }

        if ($phone) {
            return 'https://wa.me/'
                . $phone
                . '?text='
                . rawurlencode($message);
        }

        return 'https://wa.me/?text='
            . rawurlencode($message);
    }

    /*
    |--------------------------------------------------------------------------
    | Registrar follow-up
    |--------------------------------------------------------------------------
    */

    public function registerFollowUp(
        int $quoteId
    ): void {

        /*
         * E-mail confirmado é obrigatório para follow-up via WhatsApp.
         * Esta checagem também bloqueia chamadas diretas pelo Livewire.
         */
        abort_unless(
            auth()->user()?->hasVerifiedEmail(),
            403
        );
        $business = Auth::user()->business;

        abort_unless(
            $business,
            403
        );

        /*
         * Mesmo que alguém tente chamar a action diretamente,
         * Follow-up continua protegido pelo plano.
         */
        abort_unless(
            $this->canUseFollowUp,
            403
        );

        $quote = $business
            ->quotes()
            ->whereKey($quoteId)
            ->whereIn(
                'status',
                ['sent', 'viewed']
            )
            ->firstOrFail();

        /*
         * Impede múltiplos registros causados
         * por duplo clique.
         */
        $recentFollowUp = $quote
            ->events()
            ->where(
                'type',
                'follow_up'
            )
            ->where(
                'created_at',
                '>=',
                now()->subMinutes(5)
            )
            ->exists();

        if (!$recentFollowUp) {

            $quote
                ->events()
                ->create([
                    'type' =>
                        'follow_up',

                    'metadata' => [
                        'channel' =>
                            'whatsapp',

                        'origin' =>
                            'dashboard',
                    ],
                ]);
        }

        /*
         * Atualiza a lista imediatamente.
         */
        unset(
            $this->attentionQuotes
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Propostas recentes
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function recentQuotes()
    {
        if (!$this->business) {
            return collect();
        }

        return (clone $this->latestFamilyQuery())
            ->with('client')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    */

    public function statusLabel(
        string $status
    ): string {
        return match ($status) {
            'draft' => 'Rascunho',
            'sent' => 'Enviado',
            'viewed' => 'Visualizado',
            'accepted' => 'Aceito',
            'rejected' => 'Recusado',
            'expired' => 'Expirado',

            default =>
            ucfirst($status),
        };
    }

    public function statusClasses(
        string $status
    ): string {
        return match ($status) {
            'draft' =>
            'bg-zinc-100 text-zinc-700
                 dark:bg-zinc-800 dark:text-zinc-200',

            'sent' =>
            'bg-blue-100 text-blue-700
                 dark:bg-blue-950/60 dark:text-blue-300',

            'viewed' =>
            'bg-amber-100 text-amber-800
                 dark:bg-amber-950/60 dark:text-amber-300',

            'accepted' =>
            'bg-emerald-100 text-emerald-700
                 dark:bg-emerald-950/60 dark:text-emerald-300',

            'rejected' =>
            'bg-red-100 text-red-700
                 dark:bg-red-950/60 dark:text-red-300',

            'expired' =>
            'bg-zinc-100 text-zinc-500
                 dark:bg-zinc-800 dark:text-zinc-400',

            default =>
            'bg-zinc-100 text-zinc-700
                 dark:bg-zinc-800 dark:text-zinc-200',
        };
    }
};
?>

<div class="mx-auto w-full max-w-7xl space-y-6">

    {{-- ========================================================= --}}
    {{-- CABEÇALHO --}}
    {{-- ========================================================= --}}

    <div class="
            flex flex-col gap-4

            sm:flex-row
            sm:items-center
            sm:justify-between
        ">

        <div>

            <h1 class="
                    text-2xl
                    font-semibold
                    tracking-tight

                    text-zinc-950
                    dark:text-white
                ">
                Dashboard
            </h1>

            <p class="
                    mt-1
                    text-sm

                    text-zinc-500
                    dark:text-zinc-400
                ">
                Acompanhe suas propostas e veja onde agir para fechar mais.
            </p>

        </div>


        {{-- PRIMEIRO ACESSO - CTA DO CABECALHO --}}
        @if ($this->recentQuotes->isNotEmpty())

        <a href="{{ route('quotes.create') }}" wire:navigate class="
                inline-flex
                items-center
                justify-center
                gap-2

                rounded-lg

                bg-emerald-600

                px-4 py-2.5

                text-sm
                font-semibold
                text-white

                shadow-sm

                transition

                hover:bg-emerald-700

                dark:bg-emerald-500
                dark:text-zinc-950
                dark:hover:bg-emerald-400
            ">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" d="M12 5v14M5 12h14" />
            </svg>

            Nova proposta
        </a>

        @endif

    </div>


    {{-- ========================================================= --}}
    {{-- PRIMEIRO ACESSO - DASHBOARD --}}
    {{-- ========================================================= --}}

    @if ($this->recentQuotes->isEmpty())

        <section class="
                overflow-hidden

                rounded-2xl

                border border-emerald-200
                bg-white

                shadow-sm

                dark:border-emerald-900/60
                dark:bg-zinc-900
            ">

            <div class="
                    border-b border-emerald-100
                    bg-gradient-to-br
                    from-emerald-50
                    via-white
                    to-white

                    px-6 py-8

                    sm:px-8
                    sm:py-10

                    dark:border-emerald-900/40
                    dark:from-emerald-950/30
                    dark:via-zinc-900
                    dark:to-zinc-900
                ">

                <div class="
                        flex flex-col
                        gap-6

                        lg:flex-row
                        lg:items-center
                        lg:justify-between
                    ">

                    <div class="max-w-2xl">

                        <div class="
                                inline-flex
                                items-center
                                gap-2

                                rounded-full

                                bg-emerald-100

                                px-3 py-1.5

                                text-xs
                                font-semibold
                                text-emerald-700

                                dark:bg-emerald-500/10
                                dark:text-emerald-400
                            ">

                            <svg
                                class="size-3.5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.5"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M5 12l4 4L19 6"
                                />
                            </svg>

                            Tudo pronto para começar

                        </div>


                        <h2 class="
                                mt-4

                                text-2xl
                                font-bold
                                tracking-tight

                                text-zinc-950

                                sm:text-3xl

                                dark:text-white
                            ">
                            Crie sua primeira proposta
                        </h2>


                        <p class="
                                mt-3
                                max-w-xl

                                text-sm
                                leading-6

                                text-zinc-600

                                sm:text-base

                                dark:text-zinc-300
                            ">
                            Monte um orçamento profissional, adicione o cliente
                            e os itens do serviço e deixe o Fechou organizar
                            todo o acompanhamento para você.
                        </p>


                        <div class="
                                mt-6
                                flex flex-col
                                gap-3

                                sm:flex-row
                                sm:items-center
                            ">

                            <a
                                href="{{ route('quotes.create') }}"
                                wire:navigate
                                class="
                                    inline-flex
                                    items-center
                                    justify-center
                                    gap-2

                                    rounded-xl

                                    bg-emerald-600

                                    px-5 py-3

                                    text-sm
                                    font-semibold
                                    text-white

                                    shadow-sm
                                    transition

                                    hover:bg-emerald-700

                                    dark:bg-emerald-500
                                    dark:text-zinc-950
                                    dark:hover:bg-emerald-400
                                "
                            >

                                <svg
                                    class="size-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.5"
                                >
                                    <path
                                        stroke-linecap="round"
                                        d="M12 5v14M5 12h14"
                                    />
                                </svg>

                                Criar primeira proposta

                            </a>


                            <p class="
                                    text-xs
                                    leading-5

                                    text-zinc-500
                                    dark:text-zinc-400
                                ">
                                Você pode cadastrar o cliente
                                durante a criação.
                            </p>

                        </div>

                    </div>


                    <div class="
                            hidden
                            shrink-0

                            lg:block
                        ">

                        <div class="
                                flex size-28
                                items-center
                                justify-center

                                rounded-3xl

                                border border-emerald-200
                                bg-emerald-100/70

                                text-emerald-700

                                dark:border-emerald-900
                                dark:bg-emerald-500/10
                                dark:text-emerald-400
                            ">

                            <svg
                                class="size-12"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.7"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"
                                />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M14 2v6h6M8 13h8M8 17h5"
                                />
                            </svg>

                        </div>

                    </div>

                </div>

            </div>


            <div class="
                    grid
                    gap-0

                    sm:grid-cols-3
                    sm:divide-x

                    dark:divide-zinc-800
                ">

                <div class="
                        border-b border-zinc-100
                        p-5

                        sm:border-b-0

                        dark:border-zinc-800
                    ">

                    <div class="
                            flex size-8
                            items-center
                            justify-center

                            rounded-lg

                            bg-blue-100

                            text-sm
                            font-bold
                            text-blue-700

                            dark:bg-blue-950
                            dark:text-blue-300
                        ">
                        1
                    </div>

                    <p class="
                            mt-3
                            text-sm
                            font-semibold

                            text-zinc-900
                            dark:text-zinc-100
                        ">
                        Escolha o cliente
                    </p>

                    <p class="
                            mt-1
                            text-xs
                            leading-5

                            text-zinc-500
                            dark:text-zinc-400
                        ">
                        Se ele ainda não existir, cadastre sem sair da proposta.
                    </p>

                </div>


                <div class="
                        border-b border-zinc-100
                        p-5

                        sm:border-b-0

                        dark:border-zinc-800
                    ">

                    <div class="
                            flex size-8
                            items-center
                            justify-center

                            rounded-lg

                            bg-violet-100

                            text-sm
                            font-bold
                            text-violet-700

                            dark:bg-violet-950
                            dark:text-violet-300
                        ">
                        2
                    </div>

                    <p class="
                            mt-3
                            text-sm
                            font-semibold

                            text-zinc-900
                            dark:text-zinc-100
                        ">
                        Adicione os itens
                    </p>

                    <p class="
                            mt-1
                            text-xs
                            leading-5

                            text-zinc-500
                            dark:text-zinc-400
                        ">
                        Informe serviços, materiais, quantidades e valores.
                    </p>

                </div>


                <div class="p-5">

                    <div class="
                            flex size-8
                            items-center
                            justify-center

                            rounded-lg

                            bg-amber-100

                            text-sm
                            font-bold
                            text-amber-700

                            dark:bg-amber-950
                            dark:text-amber-300
                        ">
                        3
                    </div>

                    <p class="
                            mt-3
                            text-sm
                            font-semibold

                            text-zinc-900
                            dark:text-zinc-100
                        ">
                        Envie e acompanhe
                    </p>

                    <p class="
                            mt-1
                            text-xs
                            leading-5

                            text-zinc-500
                            dark:text-zinc-400
                        ">
                        Compartilhe a proposta e acompanhe visualização e resposta.
                    </p>

                </div>

            </div>

        </section>

    @else

    {{-- ========================================================= --}}
    {{-- INDICADORES --}}
    {{-- ========================================================= --}}

    <div class="
            grid grid-cols-1
            gap-3

            sm:grid-cols-2
            lg:grid-cols-4
        ">

        {{-- AGUARDANDO --}}

        <div class="
                rounded-xl

                border border-amber-200
                bg-amber-50

                px-4 py-3

                dark:border-amber-900/60
                dark:bg-amber-950/20
            ">

            <p class="
                    text-xs font-semibold

                    text-amber-700
                    dark:text-amber-400
                ">
                Aguardando cliente
            </p>

            <div class="
                    mt-1

                    flex items-end
                    justify-between
                    gap-3
                ">

                <p class="
                        text-xl font-bold

                        text-zinc-950
                        dark:text-zinc-100
                    ">
                    {{ $this->waitingCount }}
                </p>

                <p class="
                        text-sm font-semibold

                        text-zinc-700
                        dark:text-zinc-300
                    ">
                    R$ {{ number_format(
    $this->waitingAmount,
    2,
    ',',
    '.'
) }}
                </p>

            </div>

        </div>


        {{-- VISUALIZADOS --}}

        <div class="
                rounded-xl

                border border-blue-200
                bg-blue-50

                px-4 py-3

                dark:border-blue-900/60
                dark:bg-blue-950/20
            ">

            <p class="
                    text-xs font-semibold

                    text-blue-700
                    dark:text-blue-400
                ">
                Visualizados
            </p>

            <p class="
                    mt-1

                    text-xl font-bold

                    text-zinc-950
                    dark:text-zinc-100
                ">
                {{ $this->viewedCount }}
            </p>

        </div>


        {{-- ACEITOS --}}

        <div class="
                rounded-xl

                border border-emerald-200
                bg-emerald-50

                px-4 py-3

                dark:border-emerald-900/60
                dark:bg-emerald-950/20
            ">

            <p class="
                    text-xs font-semibold

                    text-emerald-700
                    dark:text-emerald-400
                ">
                Aceitos
            </p>

            <div class="
                    mt-1

                    flex items-end
                    justify-between
                    gap-3
                ">

                <p class="
                        text-xl font-bold

                        text-zinc-950
                        dark:text-zinc-100
                    ">
                    {{ $this->acceptedCount }}
                </p>

                <p class="
                        text-sm font-semibold

                        text-zinc-700
                        dark:text-zinc-300
                    ">
                    R$ {{ number_format(
    $this->acceptedAmount,
    2,
    ',',
    '.'
) }}
                </p>

            </div>

        </div>


        {{-- PRECISAM DE ATENÇÃO / FOLLOW-UP --}}

        @if ($this->canUseFollowUp)

            <div class="
                    rounded-xl

                    border border-red-200
                    bg-red-50

                    px-4 py-3

                    dark:border-red-900/60
                    dark:bg-red-950/20
                ">

                <p class="
                        text-xs font-semibold

                        text-red-700
                        dark:text-red-400
                    ">
                    Precisam de atenção
                </p>

                <p class="
                        mt-1

                        text-xl font-bold

                        text-zinc-950
                        dark:text-zinc-100
                    ">
                    {{ $this->attentionCount }}
                </p>

            </div>

        @else

            <a
                href="{{ route('settings.subscription') }}"
                wire:navigate
                class="
                    group
                    rounded-xl

                    border border-violet-200
                    bg-violet-50

                    px-4 py-3

                    transition

                    hover:border-violet-300
                    hover:bg-violet-100

                    dark:border-violet-900/60
                    dark:bg-violet-950/20
                    dark:hover:border-violet-800
                    dark:hover:bg-violet-950/40
                ">

                <div class="flex items-center justify-between gap-3">

                    <p class="
                            text-xs font-semibold

                            text-violet-700
                            dark:text-violet-300
                        ">
                        Follow-up inteligente
                    </p>

                    <span class="
                            rounded-full

                            bg-violet-600

                            px-2 py-0.5

                            text-[10px]
                            font-bold
                            text-white

                            dark:bg-violet-500
                            dark:text-zinc-950
                        ">
                        PRO
                    </span>

                </div>

                <p class="
                        mt-1

                        text-xs font-semibold

                        text-zinc-600
                        dark:text-zinc-300
                    ">
                    Veja propostas que precisam de atenção →
                </p>

            </a>

        @endif

    </div>



    {{-- ========================================================= --}}
    {{-- PAINEL PÓS-ACEITE --}}
    {{-- ========================================================= --}}

    @if ($this->closedDealsCount > 0)

        <section
            class="
                overflow-hidden
                rounded-2xl
                border border-zinc-200
                bg-white
                shadow-sm

                dark:border-zinc-800
                dark:bg-zinc-900
            "
        >

            {{-- CABEÇALHO --}}
            <div
                class="
                    flex flex-col gap-3
                    border-b border-zinc-200
                    px-5 py-4

                    sm:flex-row
                    sm:items-center
                    sm:justify-between

                    dark:border-zinc-800
                "
            >

                <div>

                    <div
                        class="
                            flex items-center gap-2
                            text-sm font-semibold
                            text-emerald-700

                            dark:text-emerald-300
                        "
                    >
                        <span
                            class="
                                flex size-7
                                items-center justify-center
                                rounded-full
                                bg-emerald-100

                                dark:bg-emerald-950
                            "
                        >
                            <svg
                                class="size-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M5 12l4 4L19 6"
                                />
                            </svg>
                        </span>

                        Pós-aceite
                    </div>

                    <h2
                        class="
                            mt-2
                            text-lg font-semibold
                            text-zinc-950

                            dark:text-white
                        "
                    >
                        Negócios fechados
                    </h2>

                    <p
                        class="
                            mt-1
                            text-sm
                            text-zinc-500

                            dark:text-zinc-400
                        "
                    >
                        Acompanhe pagamentos e a execução
                        dos negócios depois do aceite.
                    </p>

                </div>


                <a
                    href="{{ route('quotes.index') }}"
                    wire:navigate

                    class="
                        inline-flex
                        w-fit
                        items-center
                        justify-center
                        gap-2

                        rounded-lg
                        border border-zinc-300

                        bg-white
                        px-3.5 py-2

                        text-sm font-semibold
                        text-zinc-700

                        transition

                        hover:bg-zinc-100

                        dark:border-zinc-700
                        dark:bg-zinc-900
                        dark:text-zinc-200
                        dark:hover:bg-zinc-800
                    "
                >
                    Ver propostas

                    <svg
                        class="size-4"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="m9 18 6-6-6-6"
                        />
                    </svg>
                </a>

            </div>


            {{-- RESUMO --}}
            <div
                class="
                    grid
                    border-b border-zinc-200

                    sm:grid-cols-2
                    xl:grid-cols-5

                    dark:border-zinc-800
                "
            >

                {{-- FECHADOS --}}
                <a
                    href="{{
                        route(
                            'quotes.index',
                            [
                                'post' => 'closed',
                            ]
                        )
                    }}"
                    wire:navigate

                    class="
                        group
                        border-b border-zinc-200
                        p-5

                        transition

                        hover:bg-zinc-50

                        sm:border-r
                        xl:border-b-0

                        dark:border-zinc-800
                        dark:hover:bg-zinc-800/40
                    "
                >
                    <p
                        class="
                            text-xs font-semibold
                            uppercase tracking-wide
                            text-zinc-500

                            dark:text-zinc-400
                        "
                    >
                        Negócios fechados
                    </p>

                    <div
                        class="
                            mt-2
                            flex items-end
                            justify-between gap-3
                        "
                    >
                        <span
                            class="
                                text-2xl font-bold
                                text-zinc-950

                                dark:text-white
                            "
                        >
                            {{
                                $this
                                    ->closedDealsCount
                            }}
                        </span>

                        <span
                            class="
                                text-zinc-400
                                transition

                                group-hover:
                                translate-x-0.5
                            "
                        >
                            →
                        </span>
                    </div>

                    <p
                        class="
                            mt-1
                            text-sm font-semibold
                            text-emerald-500
                        "
                    >
                        R$
                        {{ number_format(
                            $this->closedDealsAmount,
                            2,
                            ',',
                            '.'
                        ) }}
                    </p>
                </a>


                {{-- A RECEBER --}}
                <a
                    href="{{
                        route(
                            'quotes.index',
                            [
                                'post' =>
                                    'receivable',
                            ]
                        )
                    }}"
                    wire:navigate

                    class="
                        group
                        border-b border-zinc-200
                        p-5

                        transition

                        hover:bg-zinc-50

                        xl:border-b-0
                        xl:border-r

                        dark:border-zinc-800
                        dark:hover:bg-zinc-800/40
                    "
                >
                    <p
                        class="
                            text-xs font-semibold
                            uppercase tracking-wide
                            text-zinc-500

                            dark:text-zinc-400
                        "
                    >
                        A receber
                    </p>

                    <div
                        class="
                            mt-2
                            flex items-end
                            justify-between gap-3
                        "
                    >
                        <span
                            class="
                                text-2xl font-bold
                                text-amber-500
                            "
                        >
                            {{
                                $this
                                    ->receivableCount
                            }}
                        </span>

                        <span
                            class="
                                text-zinc-400
                                transition

                                group-hover:
                                translate-x-0.5
                            "
                        >
                            →
                        </span>
                    </div>

                    <p
                        class="
                            mt-1
                            text-sm font-semibold
                            text-zinc-700

                            dark:text-zinc-300
                        "
                    >
                        R$
                        {{ number_format(
                            $this->receivableAmount,
                            2,
                            ',',
                            '.'
                        ) }}
                    </p>
                </a>


                {{-- AGUARDANDO EXECUÇÃO --}}
                <a
                    href="{{
                        route(
                            'quotes.index',
                            [
                                'post' =>
                                    'awaiting',
                            ]
                        )
                    }}"
                    wire:navigate

                    class="
                        group
                        border-b border-zinc-200
                        p-5

                        transition

                        hover:bg-zinc-50

                        sm:border-r
                        xl:border-b-0

                        dark:border-zinc-800
                        dark:hover:bg-zinc-800/40
                    "
                >
                    <p
                        class="
                            text-xs font-semibold
                            uppercase tracking-wide
                            text-zinc-500

                            dark:text-zinc-400
                        "
                    >
                        Aguardando execução
                    </p>

                    <div
                        class="
                            mt-2
                            flex items-end
                            justify-between gap-3
                        "
                    >
                        <span
                            class="
                                text-2xl font-bold
                                text-zinc-700

                                dark:text-zinc-200
                            "
                        >
                            {{
                                $this
                                    ->awaitingExecutionCount
                            }}
                        </span>

                        <span
                            class="
                                text-zinc-400
                                transition

                                group-hover:
                                translate-x-0.5
                            "
                        >
                            →
                        </span>
                    </div>

                    <p
                        class="
                            mt-1
                            text-sm
                            text-zinc-500

                            dark:text-zinc-400
                        "
                    >
                        Ainda não iniciados
                    </p>
                </a>


                {{-- EM EXECUÇÃO --}}
                <a
                    href="{{
                        route(
                            'quotes.index',
                            [
                                'post' =>
                                    'in_progress',
                            ]
                        )
                    }}"
                    wire:navigate

                    class="
                        group
                        border-b border-zinc-200
                        p-5

                        transition

                        hover:bg-zinc-50

                        xl:border-b-0
                        xl:border-r

                        dark:border-zinc-800
                        dark:hover:bg-zinc-800/40
                    "
                >
                    <p
                        class="
                            text-xs font-semibold
                            uppercase tracking-wide
                            text-zinc-500

                            dark:text-zinc-400
                        "
                    >
                        Em execução
                    </p>

                    <div
                        class="
                            mt-2
                            flex items-end
                            justify-between gap-3
                        "
                    >
                        <span
                            class="
                                text-2xl font-bold
                                text-blue-500
                            "
                        >
                            {{
                                $this
                                    ->inExecutionCount
                            }}
                        </span>

                        <span
                            class="
                                text-zinc-400
                                transition

                                group-hover:
                                translate-x-0.5
                            "
                        >
                            →
                        </span>
                    </div>

                    <p
                        class="
                            mt-1
                            text-sm
                            text-zinc-500

                            dark:text-zinc-400
                        "
                    >
                        Serviços ou pedidos em andamento
                    </p>
                </a>


                {{-- CONCLUÍDOS --}}
                <a
                    href="{{
                        route(
                            'quotes.index',
                            [
                                'post' =>
                                    'completed',
                            ]
                        )
                    }}"
                    wire:navigate

                    class="
                        group
                        p-5

                        transition

                        hover:bg-zinc-50
                        dark:hover:bg-zinc-800/40
                    "
                >
                    <p
                        class="
                            text-xs font-semibold
                            uppercase tracking-wide
                            text-zinc-500

                            dark:text-zinc-400
                        "
                    >
                        Concluídos
                    </p>

                    <div
                        class="
                            mt-2
                            flex items-end
                            justify-between gap-3
                        "
                    >
                        <span
                            class="
                                text-2xl font-bold
                                text-emerald-500
                            "
                        >
                            {{
                                $this
                                    ->completedDealsCount
                            }}
                        </span>

                        <span
                            class="
                                text-zinc-400
                                transition

                                group-hover:
                                translate-x-0.5
                            "
                        >
                            →
                        </span>
                    </div>

                    <p
                        class="
                            mt-1
                            text-sm
                            text-zinc-500

                            dark:text-zinc-400
                        "
                    >
                        Execuções finalizadas
                    </p>
                </a>

            </div>


            {{-- NEGÓCIOS QUE AINDA PRECISAM DE ACOMPANHAMENTO --}}
            @if ($this->postAcceptanceQuotes->isNotEmpty())

                <div class="px-5 py-4">

                    <div
                        class="
                            mb-3
                            flex items-center justify-between
                        "
                    >
                        <h3
                            class="
                                text-sm font-semibold
                                text-zinc-900

                                dark:text-zinc-100
                            "
                        >
                            Em acompanhamento
                        </h3>

                        <span
                            class="
                                text-xs
                                text-zinc-500

                                dark:text-zinc-400
                            "
                        >
                            Até 5 negócios
                        </span>
                    </div>


                    <div
                        class="
                            divide-y divide-zinc-200

                            dark:divide-zinc-800
                        "
                    >

                        @foreach (
                            $this->postAcceptanceQuotes
                            as $quote
                        )

                            <a
                                href="{{
                                    route(
                                        'quotes.show',
                                        $quote->id
                                    )
                                }}"
                                wire:navigate

                                class="
                                    flex flex-col gap-3
                                    py-3

                                    transition

                                    first:pt-0
                                    last:pb-0

                                    hover:bg-zinc-50

                                    sm:flex-row
                                    sm:items-center
                                    sm:justify-between

                                    dark:hover:bg-zinc-800/40
                                "
                            >

                                <div class="min-w-0">

                                    <div
                                        class="
                                            flex flex-wrap
                                            items-center gap-2
                                        "
                                    >
                                        <span
                                            class="
                                                text-sm font-semibold
                                                text-zinc-950

                                                dark:text-white
                                            "
                                        >
                                            #{{
                                                str_pad(
                                                    $quote->number,
                                                    4,
                                                    '0',
                                                    STR_PAD_LEFT
                                                )
                                            }}
                                        </span>

                                        <span
                                            class="
                                                truncate
                                                text-sm
                                                text-zinc-600

                                                dark:text-zinc-300
                                            "
                                        >
                                            {{
                                                $quote->client?->name
                                                ?? 'Cliente'
                                            }}
                                        </span>
                                    </div>

                                    <p
                                        class="
                                            mt-1 truncate
                                            text-xs
                                            text-zinc-500

                                            dark:text-zinc-400
                                        "
                                    >
                                        {{ $quote->title }}
                                    </p>

                                </div>


                                <div
                                    class="
                                        flex flex-wrap
                                        items-center gap-2
                                    "
                                >

                                    @if (
                                        $quote->payment_status
                                        !== 'paid'
                                    )

                                        <span
                                            class="
                                                rounded-full
                                                bg-amber-100
                                                px-2.5 py-1
                                                text-xs font-semibold
                                                text-amber-700

                                                dark:bg-amber-950
                                                dark:text-amber-300
                                            "
                                        >
                                            Pagamento pendente
                                        </span>

                                    @endif


                                    @if (
                                        $quote->execution_status
                                        === 'in_progress'
                                    )

                                        <span
                                            class="
                                                rounded-full
                                                bg-blue-100
                                                px-2.5 py-1
                                                text-xs font-semibold
                                                text-blue-700

                                                dark:bg-blue-950
                                                dark:text-blue-300
                                            "
                                        >
                                            Em execução
                                        </span>

                                    @elseif (
                                        $quote->execution_status
                                        !== 'completed'
                                    )

                                        <span
                                            class="
                                                rounded-full
                                                bg-zinc-100
                                                px-2.5 py-1
                                                text-xs font-semibold
                                                text-zinc-600

                                                dark:bg-zinc-800
                                                dark:text-zinc-300
                                            "
                                        >
                                            Aguardando execução
                                        </span>

                                    @endif


                                    <span
                                        class="
                                            ml-1
                                            text-sm font-semibold
                                            text-zinc-950

                                            dark:text-white
                                        "
                                    >
                                        R$
                                        {{ number_format(
                                            (float) $quote->total,
                                            2,
                                            ',',
                                            '.'
                                        ) }}
                                    </span>

                                    <svg
                                        class="
                                            size-4
                                            text-zinc-400
                                        "
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="m9 18 6-6-6-6"
                                        />
                                    </svg>

                                </div>

                            </a>

                        @endforeach

                    </div>

                </div>

            @endif

        </section>

    @endif


    {{-- ========================================================= --}}
    {{-- PROPOSTAS RECENTES --}}
    {{-- ========================================================= --}}

    <section class="
            overflow-hidden

            rounded-2xl

            border border-zinc-200

            bg-white

            shadow-sm

            dark:border-zinc-800
            dark:bg-zinc-900
        ">

        <div class="
                flex items-center
                justify-between
                gap-4

                border-b
                border-zinc-200

                px-5 py-4

                sm:px-6

                dark:border-zinc-800
            ">

            <div>

                <h2 class="
                        font-semibold

                        text-zinc-950
                        dark:text-white
                    ">
                    Propostas recentes
                </h2>

                <p class="
                        mt-0.5

                        text-xs

                        text-zinc-500
                        dark:text-zinc-400
                    ">
                    Últimas propostas movimentadas.
                </p>

            </div>


            <a href="{{ route('quotes.index') }}" wire:navigate class="
                    text-sm
                    font-semibold

                    text-emerald-600

                    hover:text-emerald-700

                    dark:text-emerald-400
                    dark:hover:text-emerald-300
                ">
                Ver todos
            </a>

        </div>


        @forelse ($this->recentQuotes as $quote)

                <a href="{{ route(
                'quotes.show',
                $quote->id
            ) }}" wire:navigate wire:key="recent-{{ $quote->id }}" class="
                                                            group

                                                            flex
                                                            items-center
                                                            justify-between
                                                            gap-4

                                                            border-b
                                                            border-zinc-100

                                                            px-5 py-4

                                                            transition

                                                            last:border-0

                                                            hover:bg-zinc-50

                                                            sm:px-6

                                                            dark:border-zinc-800
                                                            dark:hover:bg-zinc-800/40
                                                        ">

                    <div class="min-w-0">

                        <div class="
                                                                    flex flex-wrap
                                                                    items-center
                                                                    gap-2
                                                                ">

                            <span class="
                                                                        text-sm
                                                                        font-bold

                                                                        text-zinc-950
                                                                        dark:text-white
                                                                    ">
                                #{{ str_pad(
                $quote->number,
                4,
                '0',
                STR_PAD_LEFT
            ) }}
                            </span>


                            <span class="
                                                                        rounded-full

                                                                        px-2 py-0.5

                                                                        text-[11px]
                                                                        font-semibold

                                                                        {{ $this->statusClasses(
                $quote->status
            ) }}
                                                                    ">
                                {{ $this->statusLabel(
                $quote->status
            ) }}
                            </span>


                            @if ($quote->version > 1)

                                <span class="
                                                                                                rounded-full

                                                                                                bg-violet-100

                                                                                                px-2 py-0.5

                                                                                                text-[10px]
                                                                                                font-semibold
                                                                                                text-violet-700

                                                                                                dark:bg-violet-950
                                                                                                dark:text-violet-300
                                                                                            ">
                                    V{{ $quote->version }}
                                </span>

                            @endif

                        </div>


                        <p class="
                                                                    mt-1
                                                                    truncate

                                                                    text-sm
                                                                    font-medium

                                                                    text-zinc-700
                                                                    dark:text-zinc-300
                                                                ">
                            {{ $quote->client->name }}
                        </p>


                        <p class="
                                                                    mt-0.5
                                                                    truncate

                                                                    text-xs

                                                                    text-zinc-500
                                                                    dark:text-zinc-400
                                                                ">
                            {{ $quote->title }}
                        </p>

                    </div>


                    <div class="
                                                                flex shrink-0
                                                                items-center
                                                                gap-4
                                                            ">

                        <p class="
                                                                    font-bold

                                                                    text-zinc-950
                                                                    dark:text-zinc-100
                                                                ">
                            R$ {{ number_format(
                (float) $quote->total,
                2,
                ',',
                '.'
            ) }}
                        </p>


                        <svg class="
                                                                    size-4

                                                                    text-zinc-300

                                                                    transition

                                                                    group-hover:translate-x-1
                                                                    group-hover:text-zinc-500

                                                                    dark:text-zinc-600
                                                                " viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
                        </svg>

                    </div>

                </a>

        @empty

            <div class="px-6 py-10 text-center">

                <p class="
                                            text-sm

                                            text-zinc-500
                                            dark:text-zinc-400
                                        ">
                    Nenhuma proposta criada ainda.
                </p>

            </div>

        @endforelse

    </section>


    {{-- ========================================================= --}}
    {{-- DESEMPENHO COMERCIAL COMPACTO --}}
    {{-- ========================================================= --}}

    <section
        x-data="{
            detailsOpen: false
        }"

        class="
            overflow-hidden

            rounded-2xl

            border
            border-zinc-200

            bg-white

            shadow-sm

            dark:border-zinc-800
            dark:bg-zinc-900
        "
    >

        {{-- CABEÇALHO --}}

        <div
            class="
                flex
                flex-col
                gap-3

                border-b
                border-zinc-200

                px-5 py-4

                sm:flex-row
                sm:items-center
                sm:justify-between
                sm:px-6

                dark:border-zinc-800
            "
        >

            <div
                class="
                    flex
                    items-start
                    gap-3
                "
            >

                <span
                    class="
                        flex
                        size-9
                        shrink-0
                        items-center
                        justify-center

                        rounded-xl

                        bg-emerald-50
                        text-emerald-600

                        dark:bg-emerald-950/50
                        dark:text-emerald-300
                    "
                >
                    <svg
                        class="size-4"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M4 17l5-5 4 4 7-9"
                        />

                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M15 7h5v5"
                        />
                    </svg>
                </span>


                <div>

                    <h2
                        class="
                            font-semibold

                            text-zinc-950
                            dark:text-white
                        "
                    >
                        Desempenho comercial
                    </h2>

                    <p
                        class="
                            mt-0.5

                            text-xs

                            text-zinc-500
                            dark:text-zinc-400
                        "
                    >
                        Conversão e velocidade do seu
                        ciclo de vendas.
                    </p>

                </div>

            </div>


            <button
                type="button"

                @click="
                    detailsOpen =
                        ! detailsOpen
                "

                :aria-expanded="
                    detailsOpen.toString()
                "

                class="
                    inline-flex
                    w-fit
                    items-center
                    gap-2

                    rounded-lg

                    px-2.5 py-2

                    text-xs
                    font-semibold

                    text-zinc-600

                    transition

                    hover:bg-zinc-100
                    hover:text-zinc-950

                    dark:text-zinc-300
                    dark:hover:bg-zinc-800
                    dark:hover:text-white
                "
            >

                <span
                    x-text="
                        detailsOpen
                            ? 'Ocultar detalhes'
                            : 'Ver detalhes'
                    "
                >
                    Ver detalhes
                </span>

                <svg
                    class="
                        size-4

                        transition-transform
                    "

                    :class="
                        detailsOpen
                            ? 'rotate-180'
                            : ''
                    "

                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="m6 9 6 6 6-6"
                    />
                </svg>

            </button>

        </div>


        {{-- MÉTRICAS PRINCIPAIS --}}

        <div
            class="
                grid

                sm:grid-cols-2
                xl:grid-cols-4
            "
        >

            @foreach (
                $this
                    ->commercialOverview[
                        'primary'
                    ]
                as $metric
            )

                <div
                    data-commercial-primary="{{
                        $metric['key']
                    }}"

                    class="
                        min-w-0

                        border-b
                        border-zinc-200

                        p-5

                        sm:odd:border-r

                        xl:border-b-0
                        xl:border-r
                        xl:last:border-r-0

                        dark:border-zinc-800
                    "
                >

                    <p
                        class="
                            text-xs
                            font-semibold

                            text-zinc-500
                            dark:text-zinc-400
                        "
                    >
                        {{ $metric['label'] }}
                    </p>


                    <p
                        class="
                            mt-2

                            text-2xl
                            font-bold
                            tracking-tight

                            text-zinc-950
                            dark:text-white
                        "
                    >
                        {{ $metric['value'] }}
                    </p>


                    <p
                        class="
                            mt-1

                            text-xs

                            text-zinc-500
                            dark:text-zinc-500
                        "
                    >
                        {{ $metric['context'] }}
                    </p>


                    <p
                        class="
                            mt-3

                            text-[11px]
                            font-medium

                            text-zinc-500
                            dark:text-zinc-400
                        "
                    >
                        {{ $metric['support'] }}
                    </p>

                </div>

            @endforeach

        </div>


        {{-- DETALHES --}}

        <div
            x-cloak
            x-show="detailsOpen"

            x-transition.opacity.duration.150ms

            class="
                border-t
                border-zinc-200

                dark:border-zinc-800
            "
        >

            <div
                class="
                    grid

                    lg:grid-cols-2
                "
            >

                {{-- DETALHES DE CONVERSÃO --}}

                <div
                    class="
                        border-b
                        border-zinc-200

                        p-5

                        lg:border-b-0
                        lg:border-r

                        sm:p-6

                        dark:border-zinc-800
                    "
                >

                    <h3
                        class="
                            text-sm
                            font-semibold

                            text-zinc-900
                            dark:text-zinc-100
                        "
                    >
                        Conversão comercial
                    </h3>

                    <p
                        class="
                            mt-1

                            text-xs

                            text-zinc-500
                            dark:text-zinc-400
                        "
                    >
                        Rascunhos não entram no cálculo.
                    </p>


                    <div
                        class="
                            mt-4

                            grid
                            gap-3

                            sm:grid-cols-2
                        "
                    >

                        @foreach (
                            $this
                                ->commercialOverview[
                                    'conversion_details'
                                ]
                            as $metric
                        )

                            <div
                                class="
                                    rounded-xl

                                    border
                                    border-zinc-200

                                    bg-zinc-50/60

                                    p-3.5

                                    dark:border-zinc-800
                                    dark:bg-zinc-950/40
                                "
                            >

                                <p
                                    class="
                                        text-xs
                                        font-semibold

                                        text-zinc-500
                                        dark:text-zinc-400
                                    "
                                >
                                    {{ $metric['label'] }}
                                </p>

                                <p
                                    class="
                                        mt-1.5

                                        text-lg
                                        font-bold

                                        text-zinc-950
                                        dark:text-white
                                    "
                                >
                                    {{ $metric['value'] }}
                                </p>

                                <p
                                    class="
                                        mt-1

                                        text-[11px]

                                        text-zinc-500
                                        dark:text-zinc-500
                                    "
                                >
                                    {{ $metric['context'] }}
                                </p>

                                <p
                                    class="
                                        mt-2

                                        text-[11px]
                                        font-medium

                                        text-zinc-500
                                        dark:text-zinc-400
                                    "
                                >
                                    {{ $metric['support'] }}
                                </p>

                            </div>

                        @endforeach

                    </div>

                </div>


                {{-- DETALHES DE DESEMPENHO --}}

                <div class="p-5 sm:p-6">

                    <h3
                        class="
                            text-sm
                            font-semibold

                            text-zinc-900
                            dark:text-zinc-100
                        "
                    >
                        Desempenho do negócio
                    </h3>

                    <p
                        class="
                            mt-1

                            text-xs

                            text-zinc-500
                            dark:text-zinc-400
                        "
                    >
                        Apenas versões atuais das propostas.
                    </p>


                    <div
                        class="
                            mt-4

                            grid
                            gap-3

                            sm:grid-cols-2
                        "
                    >

                        @foreach (
                            $this
                                ->commercialOverview[
                                    'performance_details'
                                ]
                            as $metric
                        )

                            <div
                                class="
                                    rounded-xl

                                    border
                                    border-zinc-200

                                    bg-zinc-50/60

                                    p-3.5

                                    dark:border-zinc-800
                                    dark:bg-zinc-950/40
                                "
                            >

                                <p
                                    class="
                                        text-xs
                                        font-semibold

                                        text-zinc-500
                                        dark:text-zinc-400
                                    "
                                >
                                    {{ $metric['label'] }}
                                </p>

                                <p
                                    class="
                                        mt-1.5

                                        text-lg
                                        font-bold

                                        text-zinc-950
                                        dark:text-white
                                    "
                                >
                                    {{ $metric['value'] }}
                                </p>

                                <p
                                    class="
                                        mt-1

                                        text-[11px]

                                        text-zinc-500
                                        dark:text-zinc-500
                                    "
                                >
                                    {{ $metric['context'] }}
                                </p>

                                <p
                                    class="
                                        mt-2

                                        text-[11px]
                                        font-medium

                                        text-zinc-500
                                        dark:text-zinc-400
                                    "
                                >
                                    {{ $metric['support'] }}
                                </p>

                            </div>

                        @endforeach

                    </div>

                </div>

            </div>

        </div>

    </section>


    {{-- ========================================================= --}}
    {{-- FOLLOW-UP --}}
    {{-- ========================================================= --}}

    @if ($this->canUseFollowUp)

    {{-- ========================================================= --}}
    {{-- PRECISAM DE ATENÇÃO --}}
    {{-- ========================================================= --}}

    <section class="
            overflow-hidden

            rounded-2xl

            border border-zinc-200

            bg-white

            shadow-sm

            dark:border-zinc-800
            dark:bg-zinc-900
        ">

        <div class="
                flex items-center
                justify-between
                gap-4

                border-b
                border-zinc-200

                px-5 py-4

                sm:px-6

                dark:border-zinc-800
            ">

            <div>

                <div class="flex items-center gap-2">

                    <div class="
                            flex size-8
                            items-center justify-center

                            rounded-lg

                            bg-amber-100
                            text-amber-700

                            dark:bg-amber-950
                            dark:text-amber-300
                        ">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v4m0 4h.01M10.3 4.3 2.5 18a2 2 0 0 0 1.7 3h15.6a2 2 0 0 0 1.7-3L13.7 4.3a2 2 0 0 0-3.4 0Z" />
                        </svg>
                    </div>


                    <div>

                        <h2 class="
                                font-semibold

                                text-zinc-950
                                dark:text-white
                            ">
                            Precisam de atenção
                        </h2>

                        <p class="
                                text-xs

                                text-zinc-500
                                dark:text-zinc-400
                            ">
                            Propostas que podem precisar de um contato.
                        </p>

                    </div>

                </div>

            </div>

        </div>


        @if ($this->attentionQuotes->isNotEmpty())

            <div class="
                                        divide-y divide-zinc-100

                                        dark:divide-zinc-800
                                    ">

                @foreach ($this->attentionQuotes as $quote)

                        <div wire:key="attention-{{ $quote->id }}" class="
                                                                                        px-5 py-4

                                                                                        sm:px-6
                                                                                    ">

                            <div class="
                                                                                            flex flex-col
                                                                                            gap-4

                                                                                            lg:flex-row
                                                                                            lg:items-center
                                                                                            lg:justify-between
                                                                                        ">

                                {{-- INFORMAÇÕES --}}

                                <div class="min-w-0">

                                    <div class="
                                                                                                    flex flex-wrap
                                                                                                    items-center
                                                                                                    gap-2
                                                                                                ">

                                        <a href="{{ route(
                        'quotes.show',
                        $quote->id
                    ) }}" wire:navigate class="
                                                                                                        text-sm font-bold

                                                                                                        text-zinc-950

                                                                                                        hover:text-emerald-600

                                                                                                        dark:text-white
                                                                                                        dark:hover:text-emerald-400
                                                                                                    ">
                                            #{{ str_pad(
                        $quote->number,
                        4,
                        '0',
                        STR_PAD_LEFT
                    ) }}
                                        </a>


                                        @if ($quote->version > 1)

                                            <span class="
                                                                                                                                rounded-full

                                                                                                                                bg-violet-100

                                                                                                                                px-2 py-0.5

                                                                                                                                text-[10px]
                                                                                                                                font-semibold
                                                                                                                                text-violet-700

                                                                                                                                dark:bg-violet-950
                                                                                                                                dark:text-violet-300
                                                                                                                            ">
                                                V{{ $quote->version }}
                                            </span>

                                        @endif


                                        <span class="
                                                                                                        inline-flex
                                                                                                        items-center
                                                                                                        gap-1

                                                                                                        rounded-full

                                                                                                        bg-amber-100

                                                                                                        px-2 py-0.5

                                                                                                        text-[11px]
                                                                                                        font-semibold
                                                                                                        text-amber-800

                                                                                                        dark:bg-amber-950
                                                                                                        dark:text-amber-300
                                                                                                    ">
                                            {{ $this->attentionLabel(
                        $quote
                    ) }}
                                        </span>

                                    </div>


                                    <p class="
                                                                                                    mt-1

                                                                                                    truncate

                                                                                                    font-semibold

                                                                                                    text-zinc-800
                                                                                                    dark:text-zinc-200
                                                                                                ">
                                        {{ $quote->client->name }}
                                    </p>


                                    <p class="
                                                                                                    mt-0.5

                                                                                                    text-sm

                                                                                                    text-zinc-500
                                                                                                    dark:text-zinc-400
                                                                                                ">
                                        {{ $this->attentionDescription(
                        $quote
                    ) }}
                                    </p>

                                </div>


                                {{-- VALOR + AÇÃO --}}

                                <div class="
                                                                                                flex flex-col
                                                                                                gap-3

                                                                                                sm:flex-row
                                                                                                sm:items-center

                                                                                                lg:shrink-0
                                                                                            ">

                                    <div class="sm:text-right">

                                        <p class="
                                                                                                        text-xs

                                                                                                        text-zinc-400
                                                                                                        dark:text-zinc-500
                                                                                                    ">
                                            Valor
                                        </p>

                                        <p class="
                                                                                                        font-bold

                                                                                                        text-zinc-950
                                                                                                        dark:text-zinc-100
                                                                                                    ">
                                            R$ {{ number_format(
                        (float) $quote->total,
                        2,
                        ',',
                        '.'
                    ) }}
                                        </p>

                                    </div>


                                    @if (auth()->user()?->hasVerifiedEmail())

                                    <a href="{{ $this->followUpUrl(
                        $quote
                    ) }}" target="_blank" rel="noopener noreferrer" wire:click="registerFollowUp({{ $quote->id }})"
                                        class="
                                                                                                    inline-flex
                                                                                                    items-center
                                                                                                    justify-center
                                                                                                    gap-2

                                                                                                    rounded-lg

                                                                                                    bg-emerald-600

                                                                                                    px-4 py-2.5

                                                                                                    text-sm
                                                                                                    font-semibold
                                                                                                    text-white

                                                                                                    shadow-sm

                                                                                                    transition

                                                                                                    hover:bg-emerald-700

                                                                                                    dark:bg-emerald-500
                                                                                                    dark:text-zinc-950
                                                                                                    dark:hover:bg-emerald-400
                                                                                                ">

                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M21 11.5a8.5 8.5 0 0 1-12.6 7.4L3 20l1.2-5A8.5 8.5 0 1 1 21 11.5Z" />

                                            <path stroke-linecap="round" d="M8.5 8.5c.7 3 2.2 4.5 5 5" />
                                        </svg>

                                        Fazer follow-up

                                    </a>
                                    @else

                                        {-- VERIFICACAO DE E-MAIL - FOLLOW-UP DASHBOARD --}

                                        <a
                                            href="{{ route('verification.notice') }}"
                                            wire:navigate
                                            class="
                                                inline-flex items-center justify-center gap-2
                                                rounded-lg
                                                border border-amber-300 bg-amber-50
                                                px-4 py-2.5
                                                text-sm font-semibold text-amber-800
                                                shadow-sm transition
                                                hover:bg-amber-100
                                                dark:border-amber-800
                                                dark:bg-amber-950/30
                                                dark:text-amber-200
                                                dark:hover:bg-amber-950/50
                                            "
                                        >
                                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6.5h16v11H4z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m5 8 7 5 7-5" />
                                            </svg>

                                            Confirme seu e-mail
                                        </a>

                                    @endif

                                </div>

                            </div>

                        </div>

                @endforeach

            </div>


        @else

            <div class="px-6 py-10 text-center">

                <div class="
                                            mx-auto

                                            flex size-10
                                            items-center
                                            justify-center

                                            rounded-full

                                            bg-emerald-100
                                            text-emerald-700

                                            dark:bg-emerald-950
                                            dark:text-emerald-300
                                        ">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12l4 4L19 6" />
                    </svg>
                </div>


                <p class="
                                            mt-3

                                            font-medium

                                            text-zinc-800
                                            dark:text-zinc-200
                                        ">
                    Tudo em dia
                </p>


                <p class="
                                            mt-1

                                            text-sm

                                            text-zinc-500
                                            dark:text-zinc-400
                                        ">
                    Nenhuma proposta precisa de follow-up agora.
                </p>

            </div>

        @endif

    </section>


    @else




    @endif


    @endif

</div>