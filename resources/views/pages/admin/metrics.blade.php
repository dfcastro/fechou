<?php

use App\Services\ProductMetricsService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Métricas | Fechou')]
    class extends Component {
    public string $period = '30';

    /*
    |--------------------------------------------------------------------------
    | MÉTRICAS
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function metrics(): array
    {
        [$start, $end] = $this->currentPeriod();

        return app(ProductMetricsService::class)
            ->summary($start, $end);
    }

    #[Computed]
    public function previousMetrics(): array
    {
        [$start, $end] = $this->previousPeriod();

        return app(ProductMetricsService::class)
            ->summary($start, $end);
    }

    #[Computed]
    public function dailySeries(): array
    {
        [$start, $end] = $this->currentPeriod();

        return app(ProductMetricsService::class)
            ->dailySeries($start, $end);
    }

    #[Computed]
public function engagement(): array
{
    [$start, $end] = $this->currentPeriod();

    return app(ProductMetricsService::class)
        ->engagement($start, $end);
}

public function updatedPeriod(): void
{
    unset(
        $this->metrics,
        $this->previousMetrics,
        $this->dailySeries,
        $this->engagement
    );
}

    /*
    |--------------------------------------------------------------------------
    | PERÍODO
    |--------------------------------------------------------------------------
    */

    private function days(): int
    {
        return match ($this->period) {
            '7' => 7,
            '90' => 90,
            default => 30,
        };
    }

    private function currentPeriod(): array
    {
        $days = $this->days();

        return [
            now()
                ->subDays($days - 1)
                ->startOfDay(),

            now()->endOfDay(),
        ];
    }

    private function previousPeriod(): array
    {
        [$currentStart] = $this->currentPeriod();

        $previousEnd = $currentStart
            ->copy()
            ->subSecond();

        $previousStart = $previousEnd
            ->copy()
            ->subDays($this->days() - 1)
            ->startOfDay();

        return [
            $previousStart,
            $previousEnd,
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | VARIAÇÕES
    |--------------------------------------------------------------------------
    */

    public function variation(
        int|float $current,
        int|float $previous
    ): ?float {
        if ((float) $previous === 0.0) {
            return (float) $current === 0.0
                ? 0.0
                : null;
        }

        return round(
            (($current - $previous) / abs($previous)) * 100,
            1
        );
    }

    public function variationLabel(
        int|float $current,
        int|float $previous
    ): string {
        $variation = $this->variation(
            $current,
            $previous
        );

        if ($variation === null) {
            return 'Novo no período';
        }

        if ($variation === 0.0) {
            return 'Sem alteração';
        }

        $signal = $variation > 0
            ? '+'
            : '';

        return $signal
            . number_format(
                $variation,
                1,
                ',',
                '.'
            )
            . '%';
    }

    public function variationClasses(
        int|float $current,
        int|float $previous
    ): string {
        $variation = $this->variation(
            $current,
            $previous
        );

        if ($variation === null || $variation > 0) {
            return '
                bg-emerald-50
                text-emerald-700
                dark:bg-emerald-950/40
                dark:text-emerald-300
            ';
        }

        if ($variation < 0) {
            return '
                bg-red-50
                text-red-700
                dark:bg-red-950/40
                dark:text-red-300
            ';
        }

        return '
            bg-zinc-100
            text-zinc-600
            dark:bg-zinc-800
            dark:text-zinc-300
        ';
    }

    public function conversionComparisonLabel(
        float $current,
        float $previous
    ): string {
        if ($previous === 0.0) {
            return $current === 0.0
                ? 'Sem alteração'
                : 'Sem base anterior';
        }

        $difference = round(
            $current - $previous,
            2
        );

        if ($difference === 0.0) {
            return 'Sem alteração';
        }

        $signal = $difference > 0
            ? '+'
            : '';

        return $signal
            . number_format(
                $difference,
                2,
                ',',
                '.'
            )
            . ' p.p.';
    }


    /*
    |--------------------------------------------------------------------------
    | GRÁFICO
    |--------------------------------------------------------------------------
    */

    public function chartPoints(
        array $values,
        int $maxValue
    ): string {
        $count = count($values);

        if ($count === 0) {
            return '';
        }

        $left = 48;
        $right = 20;

        $top = 18;
        $bottom = 34;

        $width = 1000;
        $height = 250;

        $usableWidth = $width - $left - $right;
        $usableHeight = $height - $top - $bottom;

        return collect($values)
            ->map(function ($value, $index) use ($count, $maxValue, $left, $top, $usableWidth, $usableHeight) {
                $x = $count === 1
                    ? $left + ($usableWidth / 2)
                    : $left
                    + (($index / ($count - 1)) * $usableWidth);

                $y = $top
                    + $usableHeight
                    - (
                        ($value / max(1, $maxValue))
                        * $usableHeight
                    );

                return round($x, 2)
                    . ','
                    . round($y, 2);
            })
            ->implode(' ');
    }

    public function chartX(
        int $index,
        int $count
    ): float {
        $left = 48;
        $right = 20;
        $width = 1000;

        $usableWidth = $width - $left - $right;

        if ($count <= 1) {
            return $left + ($usableWidth / 2);
        }

        return round(
            $left
            + (($index / ($count - 1)) * $usableWidth),
            2
        );
    }

    public function chartY(
        float $value,
        int $maxValue
    ): float {
        $top = 18;
        $bottom = 34;
        $height = 250;

        $usableHeight = $height - $top - $bottom;

        return round(
            $top
            + $usableHeight
            - (
                ($value / max(1, $maxValue))
                * $usableHeight
            ),
            2
        );
    }

public function engagementRate(
    array $metric
): string {
    if (($metric['eligible'] ?? 0) === 0) {
        return '—';
    }

    return number_format(
        $metric['rate'],
        1,
        ',',
        '.'
    ) . '%';
}

public function engagementDetail(
    array $metric,
    string $resultKey
): string {
    $eligible = $metric['eligible'] ?? 0;

    if ($eligible === 0) {
        return 'Coorte ainda não madura';
    }

    $result = $metric[$resultKey] ?? 0;

    return "{$result} de {$eligible} elegíveis";
}
    /*
    |--------------------------------------------------------------------------
    | FORMATAÇÃO DE TEMPO
    |--------------------------------------------------------------------------
    */

    public function formatHours(
        ?float $hours
    ): string {
        if ($hours === null) {
            return '—';
        }

        if ($hours < 1) {
            return max(
                1,
                (int) round($hours * 60)
            ) . ' min';
        }

        if ($hours < 24) {
            $wholeHours = (int) floor($hours);

            $minutes = (int) round(
                ($hours - $wholeHours) * 60
            );

            if ($minutes === 60) {
                $wholeHours++;
                $minutes = 0;
            }

            return $minutes > 0
                ? "{$wholeHours}h {$minutes}min"
                : "{$wholeHours}h";
        }

        $days = (int) floor($hours / 24);

        $remainingHours = (int) round(
            $hours - ($days * 24)
        );

        return $remainingHours > 0
            ? "{$days}d {$remainingHours}h"
            : "{$days}d";
    }
};

?>

<div class="mx-auto max-w-7xl space-y-6">


    {{-- ================================================= --}}
    {{-- CABEÇALHO --}}
    {{-- ================================================= --}}

    <div class="
            flex flex-col gap-4
            sm:flex-row
            sm:items-end
            sm:justify-between
        ">

        <div>

            <p class="
                    text-xs font-semibold
                    uppercase tracking-wider
                    text-emerald-600
                    dark:text-emerald-400
                ">
                Administração
            </p>

            <h1 class="
                    mt-1
                    text-2xl font-semibold
                    tracking-tight
                    text-zinc-950
                    dark:text-white
                ">
                Métricas do Fechou
            </h1>

            <p class="
                    mt-1
                    text-sm
                    text-zinc-500
                    dark:text-zinc-400
                ">
                Acompanhe adoção, uso e conversão da plataforma.
            </p>

        </div>


        {{-- PERÍODO --}}

        <div class="w-full sm:w-auto">

            <label for="period" class="
                    mb-1.5 block
                    text-xs font-medium
                    text-zinc-500
                    dark:text-zinc-400
                ">
                Período
            </label>

            <select id="period" wire:model.live="period" class="
                    w-full
                    rounded-lg
                    border border-zinc-300
                    bg-white
                    px-3 py-2
                    text-sm font-medium
                    text-zinc-800
                    shadow-sm
                    outline-none
                    transition
                    focus:border-emerald-500
                    focus:ring-2
                    focus:ring-emerald-500/10
                    sm:w-auto
                    dark:border-zinc-700
                    dark:bg-zinc-900
                    dark:text-zinc-200
                ">

                <option value="7">
                    Últimos 7 dias
                </option>

                <option value="30">
                    Últimos 30 dias
                </option>

                <option value="90">
                    Últimos 90 dias
                </option>

            </select>

        </div>

    </div>



    {{-- ================================================= --}}
    {{-- VISÃO GERAL --}}
    {{-- ================================================= --}}

    @php
        $cards = [
            [
                'label' => 'Novos usuários',
                'current' => $this->metrics['users']['new'],
                'previous' => $this->previousMetrics['users']['new'],
            ],
            [
                'label' => 'Usuários ativos',
                'current' => $this->metrics['users']['active'],
                'previous' => $this->previousMetrics['users']['active'],
            ],
            [
                'label' => 'Empresas criadas',
                'current' => $this->metrics['businesses']['created'],
                'previous' => $this->previousMetrics['businesses']['created'],
            ],
            [
                'label' => 'Propostas criadas',
                'current' => $this->metrics['quotes']['created'],
                'previous' => $this->previousMetrics['quotes']['created'],
            ],
        ];
    @endphp


    <div class="
            grid grid-cols-1
            gap-3
            sm:grid-cols-2
            xl:grid-cols-4
        ">

        @foreach ($cards as $card)

            <div class="
                        rounded-xl
                        border border-zinc-200
                        bg-white
                        p-5
                        shadow-sm
                        dark:border-zinc-800
                        dark:bg-zinc-900
                    ">

                <p class="
                            text-xs font-semibold
                            text-zinc-500
                            dark:text-zinc-400
                        ">
                    {{ $card['label'] }}
                </p>


                <div class="
                            mt-3
                            flex items-end
                            justify-between
                            gap-3
                        ">

                    <p class="
                                text-3xl font-bold
                                tracking-tight
                                text-zinc-950
                                dark:text-white
                            ">
                        {{ $card['current'] }}
                    </p>


                    <span class="
                                rounded-full
                                px-2 py-1
                                text-[11px] font-semibold

                                {{
            $this->variationClasses(
                $card['current'],
                $card['previous']
            )
                                }}
                            ">
                        {{
            $this->variationLabel(
                $card['current'],
                $card['previous']
            )
                            }}
                    </span>

                </div>


                <p class="
                            mt-2
                            text-[11px]
                            text-zinc-400
                            dark:text-zinc-500
                        ">
                    Período anterior:
                    {{ $card['previous'] }}
                </p>

            </div>

        @endforeach

    </div>



    {{-- ================================================= --}}
    {{-- EVOLUÇÃO DIÁRIA --}}
    {{-- ================================================= --}}
{{-- ================================================= --}}
{{-- ADOÇÃO E RETENÇÃO --}}
{{-- ================================================= --}}

@php
    $engagement = $this->engagement;

    $engagementCards = [
        [
            'label' => 'Ativação em 24h',
            'description' => 'Novos usuários que criaram a primeira proposta nas primeiras 24 horas.',
            'metric' => $engagement['activation'],
            'result_key' => 'activated',
        ],
        [
            'label' => 'Retenção D7',
            'description' => 'Usuários ativados que voltaram a criar propostas entre os dias 7 e 13.',
            'metric' => $engagement['retention']['d7'],
            'result_key' => 'retained',
        ],
        [
            'label' => 'Retenção D30',
            'description' => 'Usuários ativados que voltaram a criar propostas entre os dias 30 e 36.',
            'metric' => $engagement['retention']['d30'],
            'result_key' => 'retained',
        ],
    ];
@endphp

<section>

    <div class="mb-3">

        <h2
            class="
                font-semibold
                text-zinc-950
                dark:text-white
            "
        >
            Adoção e retenção
        </h2>

        <p
            class="
                mt-0.5
                text-xs
                text-zinc-500
                dark:text-zinc-400
            "
        >
            Entenda se novos usuários começam a usar o produto
            e continuam voltando.
        </p>

    </div>

    <div
        class="
            grid grid-cols-1
            gap-3
            lg:grid-cols-3
        "
    >

        @foreach ($engagementCards as $card)

            <div
                class="
                    rounded-xl
                    border border-zinc-200
                    bg-white
                    p-5
                    shadow-sm
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
                    {{ $card['label'] }}
                </p>

                <p
                    class="
                        mt-1
                        min-h-8
                        text-[11px]
                        leading-4
                        text-zinc-400
                        dark:text-zinc-500
                    "
                >
                    {{ $card['description'] }}
                </p>

                <p
                    class="
                        mt-4
                        text-3xl font-bold
                        tracking-tight
                        text-zinc-950
                        dark:text-white
                    "
                >
                    {{
                        $this->engagementRate(
                            $card['metric']
                        )
                    }}
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
                    {{
                        $this->engagementDetail(
                            $card['metric'],
                            $card['result_key']
                        )
                    }}
                </p>

            </div>

        @endforeach

    </div>

</section>
    @php
        $series = $this->dailySeries;

        $allChartValues = array_merge(
            [1],
            $series['created'],
            $series['sent'],
            $series['viewed'],
            $series['accepted']
        );

        $chartMax = max(
            4,
            max($allChartValues)
        );

        $labelStep = match ($this->period) {
            '7' => 1,
            '90' => 15,
            default => 5,
        };

        $labelCount = count(
            $series['labels']
        );
    @endphp


    <section class="
            overflow-hidden
            rounded-2xl
            border border-zinc-200
            bg-white
            shadow-sm
            dark:border-zinc-800
            dark:bg-zinc-900
        ">

        {{-- CABEÇALHO DO GRÁFICO --}}

        <div class="
                flex flex-col gap-3
                border-b border-zinc-200
                px-5 py-4
                sm:flex-row
                sm:items-center
                sm:justify-between
                sm:px-6
                dark:border-zinc-800
            ">

            <div>

                <h2 class="
                        font-semibold
                        text-zinc-950
                        dark:text-white
                    ">
                    Evolução diária
                </h2>

                <p class="
                        mt-0.5
                        text-xs
                        text-zinc-500
                        dark:text-zinc-400
                    ">
                    Movimento das propostas ao longo do período selecionado.
                </p>

            </div>


            {{-- LEGENDA --}}

            <div class="
                    flex flex-wrap
                    items-center
                    gap-x-4 gap-y-2
                    text-[11px]
                    text-zinc-500
                    dark:text-zinc-400
                ">

                <div class="flex items-center gap-1.5">

                    <span class="
                            h-2 w-2
                            rounded-full
                            bg-emerald-500
                        "></span>

                    Criadas

                </div>


                <div class="flex items-center gap-1.5">

                    <span class="
                            h-2 w-2
                            rounded-full
                            bg-sky-500
                        "></span>

                    Enviadas

                </div>


                <div class="flex items-center gap-1.5">

                    <span class="
                            h-2 w-2
                            rounded-full
                            bg-amber-500
                        "></span>

                    Visualizadas

                </div>


                <div class="flex items-center gap-1.5">

                    <span class="
                            h-2 w-2
                            rounded-full
                            bg-violet-500
                        "></span>

                    Aceitas

                </div>

            </div>

        </div>


        {{-- GRÁFICO --}}

        <div class="px-3 py-5 sm:px-6">

            <div class="overflow-x-auto">

                <svg viewBox="0 0 1000 250" role="img" aria-label="Evolução diária das propostas"class="
    h-[260px]
    w-full
    min-w-[640px]
    lg:min-w-0
">

                    {{-- GRID HORIZONTAL --}}

                    @for ($i = 0; $i <= 4; $i++)

                        @php
                            $gridValue =
                                ($chartMax / 4)
                                * (4 - $i);

                            $gridY =
                                18
                                + (($i / 4) * 198);
                        @endphp


                        <line x1="48" x2="980" y1="{{ $gridY }}" y2="{{ $gridY }}" class="
                                    stroke-zinc-200
                                    dark:stroke-zinc-800
                                " stroke-width="1" />


                        <text x="38" y="{{ $gridY + 4 }}" text-anchor="end" class="
                                    fill-zinc-400
                                    text-[10px]
                                    dark:fill-zinc-500
                                ">
                            {{ (int) round($gridValue) }}
                        </text>

                    @endfor



                    {{-- CRIADAS --}}

                    <polyline points="{{
    $this->chartPoints(
        $series['created'],
        $chartMax
    )
                        }}" fill="none" class="stroke-emerald-500" stroke-width="3" stroke-linejoin="round"
                        stroke-linecap="round" vector-effect="non-scaling-stroke" />



                    {{-- ENVIADAS --}}

                    <polyline points="{{
    $this->chartPoints(
        $series['sent'],
        $chartMax
    )
                        }}" fill="none" class="stroke-sky-500" stroke-width="3" stroke-linejoin="round"
                        stroke-linecap="round" vector-effect="non-scaling-stroke" />



                    {{-- VISUALIZADAS --}}

                    <polyline points="{{
    $this->chartPoints(
        $series['viewed'],
        $chartMax
    )
                        }}" fill="none" class="stroke-amber-500" stroke-width="3" stroke-linejoin="round"
                        stroke-linecap="round" vector-effect="non-scaling-stroke" />



                    {{-- ACEITAS --}}

                    <polyline points="{{
    $this->chartPoints(
        $series['accepted'],
        $chartMax
    )
                        }}" fill="none" class="stroke-violet-500" stroke-width="3" stroke-linejoin="round"
                        stroke-linecap="round" vector-effect="non-scaling-stroke" />



                    {{-- DATAS --}}

                    @foreach (
                            $series['labels']
                            as $index => $label
                        )

                        @if (
                                        $index % $labelStep === 0
                                        || $index === $labelCount - 1
                                    )

                                    <text x="{{
                            $this->chartX(
                                $index,
                                $labelCount
                            )
                                                }}" y="242" text-anchor="middle" class="
                                                    fill-zinc-400
                                                    text-[10px]
                                                    dark:fill-zinc-500
                                                ">
                                        {{ $label }}
                                    </text>

                        @endif

                    @endforeach

                </svg>

            </div>


            {{-- RESUMO DO GRÁFICO --}}

            <div class="
                    mt-2
                    grid grid-cols-2
                    gap-2
                    border-t
                    border-zinc-100
                    pt-4
                    sm:grid-cols-4
                    dark:border-zinc-800
                ">

                <div>

                    <p class="
                            text-[10px] font-medium
                            uppercase tracking-wide
                            text-zinc-400
                            dark:text-zinc-500
                        ">
                        Criadas
                    </p>

                    <p class="
                            mt-1
                            text-lg font-bold
                            text-zinc-950
                            dark:text-white
                        ">
                        {{ $this->metrics['quotes']['created'] }}
                    </p>

                </div>


                <div>

                    <p class="
                            text-[10px] font-medium
                            uppercase tracking-wide
                            text-zinc-400
                            dark:text-zinc-500
                        ">
                        Enviadas
                    </p>

                    <p class="
                            mt-1
                            text-lg font-bold
                            text-zinc-950
                            dark:text-white
                        ">
                        {{ $this->metrics['quotes']['sent'] }}
                    </p>

                </div>


                <div>

                    <p class="
                            text-[10px] font-medium
                            uppercase tracking-wide
                            text-zinc-400
                            dark:text-zinc-500
                        ">
                        Visualizadas
                    </p>

                    <p class="
                            mt-1
                            text-lg font-bold
                            text-zinc-950
                            dark:text-white
                        ">
                        {{ $this->metrics['quotes']['viewed'] }}
                    </p>

                </div>


                <div>

                    <p class="
                            text-[10px] font-medium
                            uppercase tracking-wide
                            text-zinc-400
                            dark:text-zinc-500
                        ">
                        Aceitas
                    </p>

                    <p class="
                            mt-1
                            text-lg font-bold
                            text-zinc-950
                            dark:text-white
                        ">
                        {{ $this->metrics['quotes']['accepted'] }}
                    </p>

                </div>

            </div>

        </div>

    </section>



    {{-- ================================================= --}}
    {{-- FUNIL --}}
    {{-- ================================================= --}}

    @php
        $funnel = [
            [
                'label' => 'Criadas',
                'value' => $this->metrics['quotes']['created'],
                'rate' => null,
            ],
            [
                'label' => 'Enviadas',
                'value' => $this->metrics['quotes']['sent'],
                'rate' => $this->metrics['conversion']['created_to_sent'],
            ],
            [
                'label' => 'Visualizadas',
                'value' => $this->metrics['quotes']['viewed'],
                'rate' => $this->metrics['conversion']['sent_to_viewed'],
            ],
            [
                'label' => 'Aceitas',
                'value' => $this->metrics['quotes']['accepted'],
                'rate' => $this->metrics['conversion']['viewed_to_accepted'],
            ],
        ];
    @endphp


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
                border-b
                border-zinc-200
                px-5 py-4
                sm:px-6
                dark:border-zinc-800
            ">

            <h2 class="
                    font-semibold
                    text-zinc-950
                    dark:text-white
                ">
                Funil de propostas
            </h2>

            <p class="
                    mt-0.5
                    text-xs
                    text-zinc-500
                    dark:text-zinc-400
                ">
                Da criação até a decisão do cliente.
            </p>

        </div>


        <div class="overflow-x-auto lg:overflow-x-hidden">

            <div class="
                    grid min-w-[680px]
                    grid-cols-4
                    divide-x
                    divide-zinc-200
                    dark:divide-zinc-800
                ">

                @foreach ($funnel as $index => $item)

                    <div class="
                                relative
                                px-5 py-7
                                text-center
                            ">

                        @if ($index > 0)

                                    <span class="
                                                    absolute
                                                    left-1/2
                                                    top-3
                                                    -translate-x-1/2
                                                    whitespace-nowrap
                                                    rounded-full
                                                    bg-zinc-100
                                                    px-2 py-0.5
                                                    text-[10px] font-semibold
                                                    text-zinc-500
                                                    dark:bg-zinc-800
                                                    dark:text-zinc-400
                                                ">
                                        {{
                            number_format(
                                $item['rate'],
                                1,
                                ',',
                                '.'
                            )
                                                }}%
                                    </span>

                        @endif


                        <p class="
                                    mt-4
                                    text-xs font-medium
                                    text-zinc-500
                                    dark:text-zinc-400
                                ">
                            {{ $item['label'] }}
                        </p>

                        <p class="
                                    mt-2
                                    text-3xl font-bold
                                    tracking-tight
                                    text-zinc-950
                                    dark:text-white
                                ">
                            {{ $item['value'] }}
                        </p>

                    </div>

                @endforeach

            </div>

        </div>


        {{-- RECUSADAS --}}

        <div class="
                flex items-center
                justify-between
                gap-4
                border-t
                border-zinc-200
                bg-zinc-50/70
                px-5 py-3
                text-xs
                sm:px-6
                dark:border-zinc-800
                dark:bg-zinc-950/30
            ">

            <div>

                <span class="
                        font-medium
                        text-zinc-600
                        dark:text-zinc-400
                    ">
                    Recusadas
                </span>

                <span class="
                        ml-2
                        text-zinc-400
                        dark:text-zinc-500
                    ">
                    decisão negativa do cliente
                </span>

            </div>


            <span class="
                    text-base font-bold
                    text-zinc-900
                    dark:text-white
                ">
                {{ $this->metrics['quotes']['rejected'] }}
            </span>

        </div>

    </section>



    {{-- ================================================= --}}
    {{-- CONVERSÕES --}}
    {{-- ================================================= --}}

    @php
        $conversions = [
            [
                'label' => 'Envio → visualização',
                'description' => 'Propostas enviadas que foram abertas pelo cliente.',
                'current' => $this->metrics['conversion']['sent_to_viewed'],
                'previous' => $this->previousMetrics['conversion']['sent_to_viewed'],
            ],
            [
                'label' => 'Envio → aceite',
                'description' => 'Propostas enviadas que terminaram em aceite.',
                'current' => $this->metrics['conversion']['sent_to_accepted'],
                'previous' => $this->previousMetrics['conversion']['sent_to_accepted'],
            ],
            [
                'label' => 'Visualização → aceite',
                'description' => 'Propostas visualizadas que terminaram em aceite.',
                'current' => $this->metrics['conversion']['viewed_to_accepted'],
                'previous' => $this->previousMetrics['conversion']['viewed_to_accepted'],
            ],
        ];
    @endphp


    <section>

        <div class="mb-3">

            <h2 class="
                    font-semibold
                    text-zinc-950
                    dark:text-white
                ">
                Conversões
            </h2>

            <p class="
                    mt-0.5
                    text-xs
                    text-zinc-500
                    dark:text-zinc-400
                ">
                Taxas de avanço nas principais etapas do funil.
            </p>

        </div>


        <div class="
                grid grid-cols-1
                gap-3
                lg:grid-cols-3
            ">

            @foreach ($conversions as $conversion)

                    <div class="
                                rounded-xl
                                border border-zinc-200
                                bg-white
                                p-5
                                shadow-sm
                                dark:border-zinc-800
                                dark:bg-zinc-900
                            ">

                        <p class="
                                    text-xs font-semibold
                                    text-zinc-500
                                    dark:text-zinc-400
                                ">
                            {{ $conversion['label'] }}
                        </p>


                        <p class="
                                    mt-1
                                    min-h-8
                                    text-[11px]
                                    leading-4
                                    text-zinc-400
                                    dark:text-zinc-500
                                ">
                            {{ $conversion['description'] }}
                        </p>


                        <div class="
                                    mt-4
                                    flex items-end
                                    justify-between
                                    gap-3
                                ">

                            <p class="
                                        text-3xl font-bold
                                        tracking-tight
                                        text-zinc-950
                                        dark:text-white
                                    ">
                                {{
                number_format(
                    $conversion['current'],
                    2,
                    ',',
                    '.'
                )
                                    }}%
                            </p>


                            <span class="
                                        whitespace-nowrap
                                        text-[11px] font-semibold
                                        text-zinc-500
                                        dark:text-zinc-400
                                    ">
                                {{
                $this->conversionComparisonLabel(
                    $conversion['current'],
                    $conversion['previous']
                )
                                    }}
                            </span>

                        </div>

                    </div>

            @endforeach

        </div>

    </section>



    {{-- ================================================= --}}
    {{-- VELOCIDADE --}}
    {{-- ================================================= --}}

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
                border-b
                border-zinc-200
                px-5 py-4
                sm:px-6
                dark:border-zinc-800
            ">

            <h2 class="
                    font-semibold
                    text-zinc-950
                    dark:text-white
                ">
                Velocidade
            </h2>

            <p class="
                    mt-0.5
                    text-xs
                    text-zinc-500
                    dark:text-zinc-400
                ">
                Quanto tempo leva para os principais eventos acontecerem.
            </p>

        </div>


        <div class="
                grid grid-cols-1
                divide-y
                divide-zinc-100
                md:grid-cols-3
                md:divide-x
                md:divide-y-0
                dark:divide-zinc-800
            ">

            {{-- PRIMEIRA PROPOSTA --}}

            <div class="p-5">

                <p class="
                        text-xs font-medium
                        text-zinc-500
                        dark:text-zinc-400
                    ">
                    Cadastro → 1ª proposta
                </p>

                <p class="
                        mt-2
                        text-2xl font-bold
                        tracking-tight
                        text-zinc-950
                        dark:text-white
                    ">
                    {{
    $this->formatHours(
        $this->metrics['timing']['average_hours_to_first_quote']
    )
                    }}
                </p>

                <p class="
                        mt-1
                        text-[11px]
                        text-zinc-400
                        dark:text-zinc-500
                    ">
                    Tempo médio para começar a usar o produto.
                </p>

            </div>



            {{-- VISUALIZAÇÃO --}}

            <div class="p-5">

                <p class="
                        text-xs font-medium
                        text-zinc-500
                        dark:text-zinc-400
                    ">
                    Envio → visualização
                </p>

                <p class="
                        mt-2
                        text-2xl font-bold
                        tracking-tight
                        text-zinc-950
                        dark:text-white
                    ">
                    {{
    $this->formatHours(
        $this->metrics['timing']['average_hours_sent_to_viewed']
    )
                    }}
                </p>

                <p class="
                        mt-1
                        text-[11px]
                        text-zinc-400
                        dark:text-zinc-500
                    ">
                    Tempo médio até o cliente abrir a proposta.
                </p>

            </div>



            {{-- ACEITE --}}

            <div class="p-5">

                <p class="
                        text-xs font-medium
                        text-zinc-500
                        dark:text-zinc-400
                    ">
                    Envio → aceite
                </p>

                <p class="
                        mt-2
                        text-2xl font-bold
                        tracking-tight
                        text-zinc-950
                        dark:text-white
                    ">
                    {{
    $this->formatHours(
        $this->metrics['timing']['average_hours_sent_to_accepted']
    )
                    }}
                </p>

                <p class="
                        mt-1
                        text-[11px]
                        text-zinc-400
                        dark:text-zinc-500
                    ">
                    Tempo médio até o fechamento da proposta.
                </p>

            </div>

        </div>

    </section>

</div>