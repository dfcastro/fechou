<?php

use App\Models\Quote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Relatórios | Fechou')] class extends Component
{
    public string $period = 'this_month';

    public string $dateFrom = '';

    public string $dateTo = '';


    public function mount(): void
    {
        $this->applyPresetDates();
    }


    public function updatedPeriod(): void
    {
        if ($this->period !== 'custom') {
            $this->applyPresetDates();
        }

        unset($this->report);
    }


    public function updatedDateFrom(): void
    {
        unset($this->report);
    }


    public function updatedDateTo(): void
    {
        unset($this->report);
    }


    #[Computed]
    public function business()
    {
        return Auth::user()?->business;
    }


    private function applyPresetDates(): void
    {
        [$start, $end] =
            $this->presetBounds(
                $this->period
            );

        $this->dateFrom =
            $start->toDateString();

        $this->dateTo =
            $end->toDateString();
    }


    private function presetBounds(
        string $period
    ): array {
        $now = now();

        return match ($period) {
            'last_month' => [
                $now
                    ->copy()
                    ->subMonthNoOverflow()
                    ->startOfMonth()
                    ->startOfDay(),

                $now
                    ->copy()
                    ->subMonthNoOverflow()
                    ->endOfMonth()
                    ->endOfDay(),
            ],

            'last_3_months' => [
                $now
                    ->copy()
                    ->subMonthsNoOverflow(2)
                    ->startOfMonth()
                    ->startOfDay(),

                $now
                    ->copy()
                    ->endOfDay(),
            ],

            'last_6_months' => [
                $now
                    ->copy()
                    ->subMonthsNoOverflow(5)
                    ->startOfMonth()
                    ->startOfDay(),

                $now
                    ->copy()
                    ->endOfDay(),
            ],

            'this_year' => [
                $now
                    ->copy()
                    ->startOfYear()
                    ->startOfDay(),

                $now
                    ->copy()
                    ->endOfDay(),
            ],

            default => [
                $now
                    ->copy()
                    ->startOfMonth()
                    ->startOfDay(),

                $now
                    ->copy()
                    ->endOfDay(),
            ],
        };
    }


    private function periodBounds(): array
    {
        if ($this->period !== 'custom') {
            return $this->presetBounds(
                $this->period
            );
        }

        try {
            $start = Carbon::parse(
                $this->dateFrom
            )->startOfDay();

            $end = Carbon::parse(
                $this->dateTo
            )->endOfDay();
        } catch (\Throwable) {
            return $this->presetBounds(
                'this_month'
            );
        }

        if ($start->gt($end)) {
            [$start, $end] = [
                $end->copy()->startOfDay(),
                $start->copy()->endOfDay(),
            ];
        }

        return [$start, $end];
    }


    private function latestFamilyQuery(): Builder
    {
        if (! $this->business) {
            return Quote::query()
                ->whereRaw('1 = 0');
        }

        return Quote::query()
            ->where(
                'quotes.business_id',
                $this->business->id
            )
            ->whereNotExists(
                function ($query) {
                    $query
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
                            'COALESCE(newer.root_quote_id, newer.id) = '
                            . 'COALESCE(quotes.root_quote_id, quotes.id)'
                        )
                        ->whereColumn(
                            'newer.version',
                            '>',
                            'quotes.version'
                        );
                }
            );
    }


    private function eventAt(
        $quote,
        string $type,
        $fallback = null
    ) {
        return $quote
            ->events
            ->firstWhere(
                'type',
                $type
            )
            ?->created_at
            ?? $fallback;
    }


    private function between(
        $date,
        CarbonInterface $start,
        CarbonInterface $end
    ): bool {
        if (! $date) {
            return false;
        }

        return $date->between(
            $start,
            $end,
            true
        );
    }


    private function atOrBefore(
        $date,
        CarbonInterface $end
    ): bool {
        return $date
            && $date->lte($end);
    }


    private function quoteDates(
        $quote
    ): array {
        return [
            'created' =>
                $this->eventAt(
                    $quote,
                    'created',
                    $quote->created_at
                ),

            'sent' =>
                $this->eventAt(
                    $quote,
                    'sent',
                    $quote->sent_at
                ),

            'viewed' =>
                $this->eventAt(
                    $quote,
                    'viewed',
                    $quote->first_viewed_at
                ),

            'accepted' =>
                $this->eventAt(
                    $quote,
                    'accepted',
                    $quote->accepted_at
                ),

            'rejected' =>
                $this->eventAt(
                    $quote,
                    'rejected',
                    $quote->rejected_at
                ),

            'paid' =>
                $this->eventAt(
                    $quote,
                    'payment_received',
                    $quote->paid_at
                ),
        ];
    }


    private function rate(
        int $numerator,
        int $denominator
    ): ?float {
        if ($denominator === 0) {
            return null;
        }

        return round(
            ($numerator / $denominator) * 100,
            1
        );
    }


    public function formatRate(
        ?float $rate
    ): string {
        if ($rate === null) {
            return '—';
        }

        return number_format(
            $rate,
            floor($rate) === $rate ? 0 : 1,
            ',',
            '.'
        ) . '%';
    }


    public function money(
        float|int|string $value
    ): string {
        return 'R$ '
            . number_format(
                (float) $value,
                2,
                ',',
                '.'
            );
    }


    public function duration(
        ?int $seconds
    ): string {
        if ($seconds === null) {
            return '—';
        }

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
            return $remainingMinutes
                ? (
                    $hours
                    . ' h '
                    . $remainingMinutes
                    . ' min'
                )
                : $hours . ' h';
        }

        $days = intdiv(
            $hours,
            24
        );

        $remainingHours =
            $hours % 24;

        return $remainingHours
            ? (
                $days
                . ' d '
                . $remainingHours
                . ' h'
            )
            : $days . ' d';
    }


    public function periodLabel(): string
    {
        [$start, $end] =
            $this->periodBounds();

        return $start->format('d/m/Y')
            . ' a '
            . $end->format('d/m/Y');
    }


    private function averageAcceptanceSeconds(
        Collection $quotes,
        array $datesById
    ): ?int {
        $values = [];

        foreach ($quotes as $quote) {
            $dates =
                $datesById[$quote->id];

            if (
                ! $dates['created']
                || ! $dates['accepted']
                || $dates['accepted']->lt(
                    $dates['created']
                )
            ) {
                continue;
            }

            $values[] =
                (int) round(
                    $dates['created']
                        ->diffInSeconds(
                            $dates['accepted']
                        )
                );
        }

        if ($values === []) {
            return null;
        }

        return (int) round(
            array_sum($values)
            / count($values)
        );
    }


    private function openAt(
        Collection $quotes,
        array $datesById,
        CarbonInterface $end
    ): Collection {
        return $quotes
            ->filter(
                function ($quote) use (
                    $datesById,
                    $end
                ) {
                    $dates =
                        $datesById[$quote->id];

                    if (
                        ! $this->atOrBefore(
                            $dates['accepted'],
                            $end
                        )
                    ) {
                        return false;
                    }

                    return (
                        ! $dates['paid']
                        || $dates['paid']->gt($end)
                    );
                }
            );
    }


    private function monthRows(
        Collection $quotes,
        array $datesById,
        CarbonInterface $reportStart,
        CarbonInterface $reportEnd
    ): array {
        $cursor =
            $reportStart
                ->copy()
                ->startOfMonth();

        $lastMonth =
            $reportEnd
                ->copy()
                ->startOfMonth();

        $rows = [];

        while ($cursor->lte($lastMonth)) {
            $monthStart =
                $cursor
                    ->copy()
                    ->startOfMonth()
                    ->startOfDay();

            $monthEnd =
                $cursor
                    ->copy()
                    ->endOfMonth()
                    ->endOfDay();

            $bucketStart =
                $monthStart->lt($reportStart)
                    ? $reportStart->copy()
                    : $monthStart;

            $bucketEnd =
                $monthEnd->gt($reportEnd)
                    ? $reportEnd->copy()
                    : $monthEnd;

            $sent =
                $quotes->filter(
                    fn ($quote) =>
                        $this->between(
                            $datesById[
                                $quote->id
                            ]['sent'],
                            $bucketStart,
                            $bucketEnd
                        )
                );

            $acceptedInMonth =
                $quotes->filter(
                    fn ($quote) =>
                        $this->between(
                            $datesById[
                                $quote->id
                            ]['accepted'],
                            $bucketStart,
                            $bucketEnd
                        )
                );

            $acceptedFromSent =
                $sent->filter(
                    fn ($quote) =>
                        $this->atOrBefore(
                            $datesById[
                                $quote->id
                            ]['accepted'],
                            $bucketEnd
                        )
                );

            $paid =
                $quotes->filter(
                    fn ($quote) =>
                        $this->between(
                            $datesById[
                                $quote->id
                            ]['paid'],
                            $bucketStart,
                            $bucketEnd
                        )
                );

            $open =
                $this->openAt(
                    $quotes,
                    $datesById,
                    $bucketEnd
                );

            $rows[] = [
                'key' =>
                    $cursor->format('Y-m'),

                'label' =>
                    ucfirst(
                        $cursor
                            ->locale('pt_BR')
                            ->translatedFormat(
                                'M/Y'
                            )
                    ),

                'sent' =>
                    $sent->count(),

                'accepted' =>
                    $acceptedInMonth
                        ->count(),

                'conversion' =>
                    $this->rate(
                        $acceptedFromSent->count(),
                        $sent->count()
                    ),

                'ticket' =>
                    $acceptedInMonth->isEmpty()
                        ? 0
                        : (float)
                            $acceptedInMonth
                                ->avg('total'),

                'received' =>
                    (float)
                    $paid->sum('total'),

                'open' =>
                    (float)
                    $open->sum('total'),
            ];

            $cursor = $cursor->addMonth();
        }

        $volumeMax =
            max(
                1,
                ...array_map(
                    fn ($row) =>
                        max(
                            $row['sent'],
                            $row['accepted']
                        ),
                    $rows
                )
            );

        $financeMax =
            max(
                1,
                ...array_map(
                    fn ($row) =>
                        max(
                            $row['received'],
                            $row['open']
                        ),
                    $rows
                )
            );

        foreach ($rows as &$row) {
            $row['sent_width'] =
                round(
                    (
                        $row['sent']
                        / $volumeMax
                    ) * 100,
                    1
                );

            $row['accepted_width'] =
                round(
                    (
                        $row['accepted']
                        / $volumeMax
                    ) * 100,
                    1
                );

            $row['received_width'] =
                round(
                    (
                        $row['received']
                        / $financeMax
                    ) * 100,
                    1
                );

            $row['open_width'] =
                round(
                    (
                        $row['open']
                        / $financeMax
                    ) * 100,
                    1
                );
        }

        unset($row);

        return $rows;
    }


    #[Computed]
    public function report(): array
    {
        [$start, $end] =
            $this->periodBounds();

        if (! $this->business) {
            return [
                'created' => 0,
                'sent' => 0,
                'viewed' => 0,
                'accepted' => 0,
                'rejected' => 0,
                'conversion' => null,
                'view_rate' => null,
                'accepted_value' => 0,
                'ticket' => 0,
                'acceptance_seconds' => null,
                'received' => 0,
                'open' => 0,
                'months' => [],
            ];
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
                            ])
                            ->orderBy(
                                'created_at'
                            )
                            ->orderBy('id'),
            ])
            ->get();

        $datesById = [];

        foreach ($quotes as $quote) {
            $datesById[$quote->id] =
                $this->quoteDates(
                    $quote
                );
        }

        $created =
            $quotes->filter(
                fn ($quote) =>
                    $this->between(
                        $datesById[
                            $quote->id
                        ]['created'],
                        $start,
                        $end
                    )
            );

        /*
         * Conversão:
         *
         * A base é a coorte de propostas enviadas
         * dentro do período selecionado.
         *
         * Uma proposta conta como aceita somente se
         * o aceite ocorreu até o fim do período.
         */
        $sent =
            $quotes->filter(
                fn ($quote) =>
                    $this->between(
                        $datesById[
                            $quote->id
                        ]['sent'],
                        $start,
                        $end
                    )
            );

        $viewedFromSent =
            $sent->filter(
                function ($quote) use (
                    $datesById,
                    $end
                ) {
                    $dates =
                        $datesById[$quote->id];

                    return (
                        $this->atOrBefore(
                            $dates['viewed'],
                            $end
                        )
                        || $this->atOrBefore(
                            $dates['accepted'],
                            $end
                        )
                        || $this->atOrBefore(
                            $dates['rejected'],
                            $end
                        )
                    );
                }
            );

        $acceptedFromSent =
            $sent->filter(
                fn ($quote) =>
                    $this->atOrBefore(
                        $datesById[
                            $quote->id
                        ]['accepted'],
                        $end
                    )
            );

        $rejectedFromSent =
            $sent->filter(
                fn ($quote) =>
                    $this->atOrBefore(
                        $datesById[
                            $quote->id
                        ]['rejected'],
                        $end
                    )
            );

        /*
         * Indicadores de negócios:
         *
         * Aceites ocorridos dentro do período,
         * independentemente de quando a proposta
         * foi criada/enviada.
         */
        $acceptedInPeriod =
            $quotes->filter(
                fn ($quote) =>
                    $this->between(
                        $datesById[
                            $quote->id
                        ]['accepted'],
                        $start,
                        $end
                    )
            );

        $paidInPeriod =
            $quotes->filter(
                fn ($quote) =>
                    $this->between(
                        $datesById[
                            $quote->id
                        ]['paid'],
                        $start,
                        $end
                    )
            );

        /*
         * Em aberto:
         *
         * saldo que existia ao final do período.
         *
         * Aceito até a data final e ainda não pago
         * naquela data.
         */
        $openAtEnd =
            $this->openAt(
                $quotes,
                $datesById,
                $end
            );

        return [
            'created' =>
                $created->count(),

            'sent' =>
                $sent->count(),

            'viewed' =>
                $viewedFromSent->count(),

            'accepted' =>
                $acceptedFromSent->count(),

            'rejected' =>
                $rejectedFromSent->count(),

            'conversion' =>
                $this->rate(
                    $acceptedFromSent->count(),
                    $sent->count()
                ),

            'view_rate' =>
                $this->rate(
                    $viewedFromSent->count(),
                    $sent->count()
                ),

            'accepted_value' =>
                (float)
                $acceptedInPeriod
                    ->sum('total'),

            'ticket' =>
                $acceptedInPeriod->isEmpty()
                    ? 0
                    : (float)
                        $acceptedInPeriod
                            ->avg('total'),

            'acceptance_seconds' =>
                $this
                    ->averageAcceptanceSeconds(
                        $acceptedInPeriod,
                        $datesById
                    ),

            'received' =>
                (float)
                $paidInPeriod
                    ->sum('total'),

            'open' =>
                (float)
                $openAtEnd
                    ->sum('total'),

            'months' =>
                $this->monthRows(
                    $quotes,
                    $datesById,
                    $start,
                    $end
                ),
        ];
    }
};
?>

<div
    class="
        mx-auto
        w-full
        max-w-7xl
        space-y-5
    "
>

    {{-- ===================================================== --}}
    {{-- CABEÇALHO --}}
    {{-- ===================================================== --}}

    <div
        class="
            flex
            flex-col
            gap-4

            lg:flex-row
            lg:items-end
            lg:justify-between
        "
    >

        <div>
            <h1
                class="
                    text-2xl
                    font-semibold
                    tracking-tight

                    text-zinc-950
                    dark:text-white
                "
            >
                Relatórios comerciais
            </h1>

            <p
                class="
                    mt-1
                    text-sm
                    text-zinc-500
                    dark:text-zinc-400
                "
            >
                Acompanhe evolução, conversão, ticket e financeiro por período.
            </p>
        </div>


        <div
            class="
                flex
                flex-col
                gap-2

                sm:flex-row
                sm:items-end
            "
        >

            <div>
                <label
                    class="
                        mb-1.5
                        block

                        text-xs
                        font-semibold

                        text-zinc-500
                        dark:text-zinc-400
                    "
                >
                    Período
                </label>

                <select
                    wire:model.live="period"
                    class="
                        rounded-lg
                        border border-zinc-300

                        bg-white

                        px-3 py-2.5

                        text-sm
                        text-zinc-900

                        dark:border-zinc-700
                        dark:bg-zinc-950
                        dark:text-white
                    "
                >
                    <option value="this_month">
                        Este mês
                    </option>

                    <option value="last_month">
                        Mês passado
                    </option>

                    <option value="last_3_months">
                        Últimos 3 meses
                    </option>

                    <option value="last_6_months">
                        Últimos 6 meses
                    </option>

                    <option value="this_year">
                        Este ano
                    </option>

                    <option value="custom">
                        Personalizado
                    </option>
                </select>
            </div>


            @if ($period === 'custom')

                <div>
                    <label
                        class="
                            mb-1.5 block
                            text-xs font-semibold
                            text-zinc-500
                            dark:text-zinc-400
                        "
                    >
                        De
                    </label>

                    <input
                        type="date"
                        wire:model.live="dateFrom"
                        class="
                            rounded-lg
                            border border-zinc-300
                            bg-white
                            px-3 py-2.5
                            text-sm
                            text-zinc-900

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-white
                        "
                    >
                </div>

                <div>
                    <label
                        class="
                            mb-1.5 block
                            text-xs font-semibold
                            text-zinc-500
                            dark:text-zinc-400
                        "
                    >
                        Até
                    </label>

                    <input
                        type="date"
                        wire:model.live="dateTo"
                        class="
                            rounded-lg
                            border border-zinc-300
                            bg-white
                            px-3 py-2.5
                            text-sm
                            text-zinc-900

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-white
                        "
                    >
                </div>

            @endif

        </div>

    </div>


    <p
        class="
            text-xs
            text-zinc-500
            dark:text-zinc-400
        "
    >
        {{ $this->periodLabel() }}
    </p>


    {{-- ===================================================== --}}
    {{-- COMERCIAL --}}
    {{-- ===================================================== --}}

    <div
        class="
            grid
            grid-cols-1
            gap-3

            sm:grid-cols-2
            xl:grid-cols-4
        "
    >

        <article
            class="
                rounded-xl
                border border-blue-200
                bg-blue-50
                px-4 py-3

                dark:border-blue-900/60
                dark:bg-blue-950/20
            "
        >
            <p
                class="
                    text-xs font-semibold
                    text-blue-700
                    dark:text-blue-400
                "
            >
                Propostas enviadas
            </p>

            <p
                class="
                    mt-1
                    text-xl font-bold
                    text-zinc-950
                    dark:text-white
                "
            >
                {{ $this->report['sent'] }}
            </p>

            <p
                class="
                    mt-1 text-xs
                    text-zinc-500
                    dark:text-zinc-400
                "
            >
                {{ $this->report['created'] }}
                criadas no período
            </p>
        </article>


        <article
            class="
                rounded-xl
                border border-emerald-200
                bg-emerald-50
                px-4 py-3

                dark:border-emerald-900/60
                dark:bg-emerald-950/20
            "
        >
            <p
                class="
                    text-xs font-semibold
                    text-emerald-700
                    dark:text-emerald-400
                "
            >
                Conversão em negócio
            </p>

            <p
                class="
                    mt-1
                    text-xl font-bold
                    text-zinc-950
                    dark:text-white
                "
            >
                {{
                    $this->formatRate(
                        $this->report['conversion']
                    )
                }}
            </p>

            <p
                class="
                    mt-1 text-xs
                    text-zinc-500
                    dark:text-zinc-400
                "
            >
                {{ $this->report['accepted'] }}
                de
                {{ $this->report['sent'] }}
                enviadas
            </p>
        </article>


        <article
            class="
                rounded-xl
                border border-zinc-200
                bg-white
                px-4 py-3

                dark:border-zinc-800
                dark:bg-zinc-900
            "
        >
            <p
                class="
                    text-xs font-semibold
                    text-zinc-500
                    dark:text-zinc-400
                "
            >
                Ticket médio
            </p>

            <p
                class="
                    mt-1
                    text-xl font-bold
                    text-zinc-950
                    dark:text-white
                "
            >
                {{
                    $this->money(
                        $this->report['ticket']
                    )
                }}
            </p>

            <p
                class="
                    mt-1 text-xs
                    text-zinc-500
                    dark:text-zinc-400
                "
            >
                Negócios aceitos no período
            </p>
        </article>


        <article
            class="
                rounded-xl
                border border-zinc-200
                bg-white
                px-4 py-3

                dark:border-zinc-800
                dark:bg-zinc-900
            "
        >
            <p
                class="
                    text-xs font-semibold
                    text-zinc-500
                    dark:text-zinc-400
                "
            >
                Tempo médio até aceitar
            </p>

            <p
                class="
                    mt-1
                    text-xl font-bold
                    text-zinc-950
                    dark:text-white
                "
            >
                {{
                    $this->duration(
                        $this->report[
                            'acceptance_seconds'
                        ]
                    )
                }}
            </p>

            <p
                class="
                    mt-1 text-xs
                    text-zinc-500
                    dark:text-zinc-400
                "
            >
                Criação → aceite
            </p>
        </article>

    </div>


    {{-- ===================================================== --}}
    {{-- FINANCEIRO --}}
    {{-- ===================================================== --}}

    <div
        class="
            grid
            grid-cols-1
            gap-3

            md:grid-cols-3
        "
    >

        <article
            class="
                rounded-xl
                border border-zinc-200
                bg-white
                p-4

                dark:border-zinc-800
                dark:bg-zinc-900
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
                Valor aceito
            </p>

            <p
                class="
                    mt-2
                    text-2xl font-bold
                    text-zinc-950
                    dark:text-white
                "
            >
                {{
                    $this->money(
                        $this->report[
                            'accepted_value'
                        ]
                    )
                }}
            </p>

            <p
                class="
                    mt-1 text-xs
                    text-zinc-500
                    dark:text-zinc-400
                "
            >
                Aceites ocorridos no período
            </p>
        </article>


        <article
            class="
                rounded-xl
                border border-emerald-200
                bg-emerald-50/70
                p-4

                dark:border-emerald-900/60
                dark:bg-emerald-950/20
            "
        >
            <p
                class="
                    text-xs font-semibold
                    uppercase tracking-wide
                    text-emerald-700
                    dark:text-emerald-400
                "
            >
                Recebido
            </p>

            <p
                class="
                    mt-2
                    text-2xl font-bold
                    text-zinc-950
                    dark:text-white
                "
            >
                {{
                    $this->money(
                        $this->report[
                            'received'
                        ]
                    )
                }}
            </p>

            <p
                class="
                    mt-1 text-xs
                    text-zinc-500
                    dark:text-zinc-400
                "
            >
                Pagamentos registrados no período
            </p>
        </article>


        <article
            class="
                rounded-xl
                border border-amber-200
                bg-amber-50/70
                p-4

                dark:border-amber-900/60
                dark:bg-amber-950/20
            "
        >
            <p
                class="
                    text-xs font-semibold
                    uppercase tracking-wide
                    text-amber-700
                    dark:text-amber-400
                "
            >
                Em aberto
            </p>

            <p
                class="
                    mt-2
                    text-2xl font-bold
                    text-zinc-950
                    dark:text-white
                "
            >
                {{
                    $this->money(
                        $this->report['open']
                    )
                }}
            </p>

            <p
                class="
                    mt-1 text-xs
                    text-zinc-500
                    dark:text-zinc-400
                "
            >
                Saldo pendente no fim do período
            </p>
        </article>

    </div>


    {{-- ===================================================== --}}
    {{-- FUNIL --}}
    {{-- ===================================================== --}}

    <section
        class="
            overflow-hidden
            rounded-2xl
            border border-zinc-200
            bg-white

            dark:border-zinc-800
            dark:bg-zinc-900
        "
    >

        <div
            class="
                border-b border-zinc-200
                px-5 py-4

                dark:border-zinc-800
            "
        >
            <h2
                class="
                    font-semibold
                    text-zinc-950
                    dark:text-white
                "
            >
                Funil comercial
            </h2>

            <p
                class="
                    mt-1 text-sm
                    text-zinc-500
                    dark:text-zinc-400
                "
            >
                Desempenho das propostas enviadas no período.
            </p>
        </div>


        <div
            class="
                grid
                divide-y divide-zinc-200

                sm:grid-cols-4
                sm:divide-x
                sm:divide-y-0

                dark:divide-zinc-800
            "
        >

            @foreach ([
                [
                    'label' => 'Enviadas',
                    'value' => $this->report['sent'],
                    'support' => 'Base do funil',
                ],
                [
                    'label' => 'Visualizadas',
                    'value' => $this->report['viewed'],
                    'support' => $this->formatRate(
                        $this->report['view_rate']
                    ),
                ],
                [
                    'label' => 'Aceitas',
                    'value' => $this->report['accepted'],
                    'support' => $this->formatRate(
                        $this->report['conversion']
                    ),
                ],
                [
                    'label' => 'Recusadas',
                    'value' => $this->report['rejected'],
                    'support' => 'Decisões negativas',
                ],
            ] as $item)

                <div class="p-4">
                    <p
                        class="
                            text-xs font-semibold
                            uppercase tracking-wide
                            text-zinc-500
                            dark:text-zinc-400
                        "
                    >
                        {{ $item['label'] }}
                    </p>

                    <p
                        class="
                            mt-2
                            text-2xl font-bold
                            text-zinc-950
                            dark:text-white
                        "
                    >
                        {{ $item['value'] }}
                    </p>

                    <p
                        class="
                            mt-1 text-xs
                            text-zinc-500
                            dark:text-zinc-400
                        "
                    >
                        {{ $item['support'] }}
                    </p>
                </div>

            @endforeach

        </div>

    </section>


    {{-- ===================================================== --}}
    {{-- EVOLUÇÃO --}}
    {{-- ===================================================== --}}

    <section
        class="
            rounded-2xl
            border border-zinc-200
            bg-white
            p-5

            dark:border-zinc-800
            dark:bg-zinc-900
        "
    >

        <div>
            <h2
                class="
                    font-semibold
                    text-zinc-950
                    dark:text-white
                "
            >
                Evolução comercial
            </h2>

            <p
                class="
                    mt-1 text-sm
                    text-zinc-500
                    dark:text-zinc-400
                "
            >
                Enviadas e aceitas ao longo dos meses.
            </p>
        </div>


        <div class="mt-5 space-y-4">

            @forelse ($this->report['months'] as $month)

                <div
                    wire:key="commercial-month-{{ $month['key'] }}"
                    class="
                        grid
                        gap-2

                        md:grid-cols-[90px_1fr_1fr]
                        md:items-center
                    "
                >
                    <p
                        class="
                            text-xs font-semibold
                            text-zinc-600
                            dark:text-zinc-300
                        "
                    >
                        {{ $month['label'] }}
                    </p>

                    <div>
                        <div
                            class="
                                mb-1
                                flex items-center
                                justify-between
                                gap-3

                                text-[11px]
                                text-zinc-500
                                dark:text-zinc-400
                            "
                        >
                            <span>Enviadas</span>
                            <span>{{ $month['sent'] }}</span>
                        </div>

                        <div
                            class="
                                h-2
                                overflow-hidden
                                rounded-full
                                bg-zinc-100

                                dark:bg-zinc-800
                            "
                        >
                            <div
                                class="
                                    h-full
                                    rounded-full
                                    bg-blue-500
                                "
                                style="width: {{ $month['sent_width'] }}%"
                            ></div>
                        </div>
                    </div>

                    <div>
                        <div
                            class="
                                mb-1
                                flex items-center
                                justify-between
                                gap-3

                                text-[11px]
                                text-zinc-500
                                dark:text-zinc-400
                            "
                        >
                            <span>Aceitas</span>
                            <span>
                                {{ $month['accepted'] }}
                            </span>
                        </div>

                        <div
                            class="
                                h-2
                                overflow-hidden
                                rounded-full
                                bg-zinc-100

                                dark:bg-zinc-800
                            "
                        >
                            <div
                                class="
                                    h-full
                                    rounded-full
                                    bg-emerald-500
                                "
                                style="width: {{ $month['accepted_width'] }}%"
                            ></div>
                        </div>
                    </div>
                </div>

            @empty

                <p
                    class="
                        text-sm
                        text-zinc-500
                        dark:text-zinc-400
                    "
                >
                    Nenhum dado disponível para o período.
                </p>

            @endforelse

        </div>

    </section>


    {{-- ===================================================== --}}
    {{-- RESUMO MENSAL --}}
    {{-- ===================================================== --}}

    <section
        class="
            overflow-hidden
            rounded-2xl
            border border-zinc-200
            bg-white

            dark:border-zinc-800
            dark:bg-zinc-900
        "
    >

        <div
            class="
                border-b border-zinc-200
                px-5 py-4

                dark:border-zinc-800
            "
        >
            <h2
                class="
                    font-semibold
                    text-zinc-950
                    dark:text-white
                "
            >
                Resumo mensal
            </h2>

            <p
                class="
                    mt-1 text-sm
                    text-zinc-500
                    dark:text-zinc-400
                "
            >
                Comercial e financeiro lado a lado.
            </p>
        </div>


        <div class="overflow-x-auto">

            <table
                class="
                    min-w-full
                    divide-y divide-zinc-200

                    text-sm

                    dark:divide-zinc-800
                "
            >
                <thead
                    class="
                        bg-zinc-50
                        dark:bg-zinc-950/50
                    "
                >
                    <tr>
                        @foreach ([
                            'Mês',
                            'Enviadas',
                            'Aceitas',
                            'Conversão',
                            'Ticket',
                            'Recebido',
                            'Em aberto',
                        ] as $heading)

                            <th
                                class="
                                    {{ $heading === 'Ticket'
                                        ? 'hidden xl:table-cell'
                                        : ''
                                    }}

                                    whitespace-nowrap
                                    px-3 py-3
                                    text-left
                                    text-xs
                                    font-semibold
                                    uppercase
                                    tracking-wide

                                    text-zinc-500
                                    dark:text-zinc-400
                                "
                            >
                                {{ $heading }}
                            </th>

                        @endforeach
                    </tr>
                </thead>

                <tbody
                    class="
                        divide-y divide-zinc-100
                        dark:divide-zinc-800
                    "
                >

                    @foreach ($this->report['months'] as $month)

                        <tr
                            wire:key="commercial-row-{{ $month['key'] }}"
                        >
                            <td
                                class="
                                    whitespace-nowrap
                                    px-3 py-3
                                    font-semibold
                                    text-zinc-900
                                    dark:text-zinc-100
                                "
                            >
                                {{ $month['label'] }}
                            </td>

                            <td
                                class="
                                    whitespace-nowrap
                                    px-3 py-3
                                    text-zinc-600
                                    dark:text-zinc-300
                                "
                            >
                                {{ $month['sent'] }}
                            </td>

                            <td
                                class="
                                    whitespace-nowrap
                                    px-3 py-3
                                    text-zinc-600
                                    dark:text-zinc-300
                                "
                            >
                                {{ $month['accepted'] }}
                            </td>

                            <td
                                class="
                                    whitespace-nowrap
                                    px-3 py-3
                                    font-medium
                                    text-zinc-900
                                    dark:text-zinc-100
                                "
                            >
                                {{
                                    $this->formatRate(
                                        $month['conversion']
                                    )
                                }}
                            </td>

                            <td
                                class="
                                    hidden
                                    whitespace-nowrap
                                    px-3 py-3
                                    text-zinc-600

                                    xl:table-cell

                                    dark:text-zinc-300
                                "
                            >
                                {{
                                    $this->money(
                                        $month['ticket']
                                    )
                                }}
                            </td>

                            <td
                                class="
                                    whitespace-nowrap
                                    px-3 py-3
                                    font-medium
                                    text-emerald-700
                                    dark:text-emerald-400
                                "
                            >
                                {{
                                    $this->money(
                                        $month['received']
                                    )
                                }}
                            </td>

                            <td
                                class="
                                    whitespace-nowrap
                                    px-3 py-3
                                    font-medium
                                    text-amber-700
                                    dark:text-amber-400
                                "
                            >
                                {{
                                    $this->money(
                                        $month['open']
                                    )
                                }}
                            </td>
                        </tr>

                    @endforeach

                </tbody>
            </table>

        </div>

    </section>

</div>
