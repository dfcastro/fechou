<?php

use App\Models\Quote;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
    #[Layout('layouts.public')]
    #[Title('Orçamento | Fechou')]
    class extends Component
    {
        public int $quoteId;

        /*
    |--------------------------------------------------------------------------
    | Inicialização
    |--------------------------------------------------------------------------
    */

        public function mount(string $token): void
        {
            $quote = Quote::query()
                ->where('public_token', $token)
                ->firstOrFail();

            /*
         * Um rascunho ainda não deve estar disponível publicamente.
         */
            abort_if($quote->status === 'draft', 404);

            DB::transaction(function () use ($quote) {

                $lockedQuote = Quote::query()
                    ->whereKey($quote->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                /*
             * O orçamento continua válido até o final do dia.
             *
             * Ex.:
             * válido até 23/08/2026
             * → expira somente após 23/08/2026 23:59:59.
             */
                if (
                    $lockedQuote->valid_until
                    && $lockedQuote->valid_until
                    ->copy()
                    ->endOfDay()
                    ->isPast()
                    && ! in_array(
                        $lockedQuote->status,
                        ['accepted', 'rejected'],
                        true
                    )
                ) {
                    $lockedQuote->update([
                        'status' => 'expired',
                    ]);

                    return;
                }

                /*
             * Registra somente a primeira visualização.
             */
                if (
                    ! $lockedQuote->first_viewed_at
                    && in_array(
                        $lockedQuote->status,
                        ['sent', 'viewed'],
                        true
                    )
                ) {
                    $lockedQuote->update([
                        'status' => 'viewed',
                        'first_viewed_at' => now(),
                    ]);

                    $lockedQuote->events()->create([
                        'type' => 'viewed',
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                    ]);
                }
            });

            $this->quoteId = $quote->id;
        }

        /*
    |--------------------------------------------------------------------------
    | Orçamento
    |--------------------------------------------------------------------------
    */

        #[Computed]
        public function quote(): Quote
        {
            return Quote::query()
                ->with([
                    'business',
                    'client',

                    'items' => fn($query) =>
                    $query->orderBy('sort_order'),
                ])
                ->findOrFail($this->quoteId);
        }

        /*
    |--------------------------------------------------------------------------
    | Contato da empresa
    |--------------------------------------------------------------------------
    */

        #[Computed]
        public function businessWhatsappUrl(): ?string
        {
            $phone = preg_replace(
                '/\D+/',
                '',
                $this->quote->business->whatsapp
                    ?: $this->quote->business->phone
                    ?: ''
            );

            if (! $phone) {
                return null;
            }

            if (! str_starts_with($phone, '55')) {
                $phone = '55' . $phone;
            }

            $message =
                "Olá! Estou entrando em contato sobre o orçamento "
                . "#{$this->quote->number}.";

            return 'https://wa.me/'
                . $phone
                . '?text='
                . rawurlencode($message);
        }

        /*
    |--------------------------------------------------------------------------
    | Aceitar
    |--------------------------------------------------------------------------
    */

        public function accept(): void
        {
            DB::transaction(function () {

                $quote = Quote::query()
                    ->whereKey($this->quoteId)
                    ->lockForUpdate()
                    ->firstOrFail();

                /*
             * Somente um orçamento enviado/visualizado
             * pode receber uma decisão.
             */
                if (! in_array($quote->status, ['sent', 'viewed'], true)) {
                    return;
                }

                if (
                    $quote->valid_until
                    && $quote->valid_until
                    ->copy()
                    ->endOfDay()
                    ->isPast()
                ) {
                    $quote->update([
                        'status' => 'expired',
                    ]);

                    return;
                }

                $quote->update([
                    'status' => 'accepted',
                    'accepted_at' => now(),
                    'rejected_at' => null,
                ]);

                $quote->events()->create([
                    'type' => 'accepted',
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            });

            unset($this->quote);
        }

        /*
    |--------------------------------------------------------------------------
    | Recusar
    |--------------------------------------------------------------------------
    */

        public function reject(): void
        {
            DB::transaction(function () {

                $quote = Quote::query()
                    ->whereKey($this->quoteId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! in_array($quote->status, ['sent', 'viewed'], true)) {
                    return;
                }

                if (
                    $quote->valid_until
                    && $quote->valid_until
                    ->copy()
                    ->endOfDay()
                    ->isPast()
                ) {
                    $quote->update([
                        'status' => 'expired',
                    ]);

                    return;
                }

                $quote->update([
                    'status' => 'rejected',
                    'rejected_at' => now(),
                    'accepted_at' => null,
                ]);

                $quote->events()->create([
                    'type' => 'rejected',
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            });

            unset($this->quote);
        }

        /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

        public function typeLabel(string $type): string
        {
            return match ($type) {
                'service' => 'Serviço',
                'material' => 'Material',
                default => 'Outro',
            };
        }

        public function typeClasses(string $type): string
        {
            return match ($type) {
                'service' =>
                'bg-blue-50 text-blue-700
                 dark:bg-blue-950/50 dark:text-blue-300',

                'material' =>
                'bg-violet-50 text-violet-700
                 dark:bg-violet-950/50 dark:text-violet-300',

                default =>
                'bg-zinc-100 text-zinc-600
                 dark:bg-zinc-800 dark:text-zinc-300',
            };
        }

        public function formatQuantity(float $quantity): string
        {
            $formatted = number_format(
                $quantity,
                3,
                ',',
                '.'
            );

            return rtrim(
                rtrim($formatted, '0'),
                ','
            );
        }
    };
?>

<div
    x-data="{
        decision: null
    }"
    class="min-h-screen bg-zinc-50 dark:bg-zinc-950">

    {{-- ========================================================= --}}
    {{-- TOPO --}}
    {{-- ========================================================= --}}

    <header
        class="
            border-b border-zinc-200
            bg-white

            dark:border-zinc-800
            dark:bg-zinc-900
        ">

        <div
            class="
                mx-auto
                flex max-w-4xl
                items-center
                justify-between
                gap-4

                px-4 py-4

                sm:px-6
            ">

            {{-- EMPRESA --}}

            @if ($this->quote->business->logo_path)

            <div
                class="
            flex h-11 w-16
            shrink-0
            items-center
            justify-center
            overflow-hidden
            rounded-xl
            border border-zinc-200
            bg-white
            p-1.5

            dark:border-zinc-700
            dark:bg-white
        ">
                <img
                    src="{{ asset(
                'storage/'.
                $this->quote->business->logo_path
            ) }}"
                    alt="{{ $this->quote->business->name }}"
                    class="max-h-full max-w-full object-contain">
            </div>

            @else

            <div
                class="
            flex size-10
            shrink-0
            items-center
            justify-center
            rounded-xl
            bg-emerald-600
            font-bold
            text-white
            shadow-sm

            dark:bg-emerald-500
            dark:text-zinc-950
        ">
                {{ mb_strtoupper(
            mb_substr(
                $this->quote->business->name,
                0,
                1
            )
        ) }}
            </div>

            @endif


            <div class="min-w-0">

                <p
                    class="
                            truncate
                            font-semibold
                            text-zinc-950
                            dark:text-white
                        ">
                    {{ $this->quote->business->name }}
                </p>

                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    Proposta comercial
                </p>

            </div>

        </div>


        {{-- NÚMERO --}}

        <div class="shrink-0 text-right">

            <p
                class="
                        text-[11px]
                        font-medium
                        uppercase
                        tracking-wide
                        text-zinc-400
                        dark:text-zinc-500
                    ">
                Orçamento
            </p>

            <p class="font-bold text-zinc-900 dark:text-zinc-100">
                #{{ str_pad($this->quote->number, 4, '0', STR_PAD_LEFT) }}
            </p>

        </div>



    </header>


    {{-- ========================================================= --}}
    {{-- CONTEÚDO --}}
    {{-- ========================================================= --}}

    <main
        class="
            mx-auto
            max-w-4xl
            space-y-5

            px-4
            py-6

            sm:px-6
            sm:py-8
        ">

        {{-- ===================================================== --}}
        {{-- STATUS FINAL --}}
        {{-- ===================================================== --}}

        @if ($this->quote->status === 'accepted')

        <section
            class="
                    overflow-hidden
                    rounded-2xl

                    border
                    border-emerald-200

                    bg-emerald-50

                    shadow-sm

                    dark:border-emerald-900
                    dark:bg-emerald-950/30
                ">

            <div class="flex items-start gap-4 p-5 sm:p-6">

                <div
                    class="
                            flex size-11
                            shrink-0
                            items-center
                            justify-center

                            rounded-full

                            bg-emerald-600
                            text-white
                        ">
                    <svg
                        class="size-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.5">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M5 12l4 4L19 6" />
                    </svg>
                </div>


                <div>

                    <h2
                        class="
                                text-lg font-bold
                                text-emerald-900
                                dark:text-emerald-300
                            ">
                        Proposta aceita
                    </h2>

                    <p
                        class="
                                mt-1
                                text-sm leading-6
                                text-emerald-700
                                dark:text-emerald-400
                            ">
                        Sua confirmação foi registrada em
                        {{ $this->quote->accepted_at?->format('d/m/Y \à\s H:i') }}.
                    </p>


                    @if ($this->businessWhatsappUrl)

                    <a
                        href="{{ $this->businessWhatsappUrl }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="
                                    mt-4
                                    inline-flex
                                    items-center
                                    gap-2

                                    rounded-lg

                                    bg-emerald-600

                                    px-4 py-2.5

                                    text-sm
                                    font-semibold
                                    text-white

                                    transition

                                    hover:bg-emerald-700
                                ">
                        Falar com a empresa
                    </a>

                    @endif

                </div>

            </div>

        </section>


        @elseif ($this->quote->status === 'rejected')

        <section
            class="
                    rounded-2xl

                    border
                    border-zinc-200

                    bg-white

                    p-5
                    shadow-sm

                    dark:border-zinc-800
                    dark:bg-zinc-900
                ">

            <div class="flex items-start gap-4">

                <div
                    class="
                            flex size-11
                            shrink-0
                            items-center
                            justify-center

                            rounded-full

                            bg-zinc-100
                            text-zinc-500

                            dark:bg-zinc-800
                            dark:text-zinc-300
                        ">
                    <svg
                        class="size-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2">
                        <path
                            stroke-linecap="round"
                            d="M6 6l12 12M18 6 6 18" />
                    </svg>
                </div>


                <div>

                    <h2 class="font-bold text-zinc-900 dark:text-zinc-100">
                        Proposta recusada
                    </h2>

                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        Sua resposta foi registrada.
                    </p>


                    @if ($this->businessWhatsappUrl)

                    <a
                        href="{{ $this->businessWhatsappUrl }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="
                                    mt-4
                                    inline-flex
                                    rounded-lg

                                    border border-zinc-300
                                    bg-white

                                    px-4 py-2.5

                                    text-sm
                                    font-semibold
                                    text-zinc-700

                                    hover:bg-zinc-100

                                    dark:border-zinc-700
                                    dark:bg-zinc-900
                                    dark:text-zinc-200
                                    dark:hover:bg-zinc-800
                                ">
                        Falar com a empresa
                    </a>

                    @endif

                </div>

            </div>

        </section>


        @elseif ($this->quote->status === 'expired')

        <section
            class="
                    rounded-2xl

                    border border-amber-200

                    bg-amber-50

                    p-5
                    shadow-sm

                    dark:border-amber-900
                    dark:bg-amber-950/30
                ">

            <h2
                class="
                        font-bold
                        text-amber-900
                        dark:text-amber-300
                    ">
                Esta proposta expirou
            </h2>

            <p
                class="
                        mt-1
                        text-sm leading-6
                        text-amber-700
                        dark:text-amber-400
                    ">
                O prazo de validade terminou.
                Entre em contato com
                {{ $this->quote->business->name }}
                para solicitar uma atualização.
            </p>


            @if ($this->businessWhatsappUrl)

            <a
                href="{{ $this->businessWhatsappUrl }}"
                target="_blank"
                rel="noopener noreferrer"
                class="
                            mt-4
                            inline-flex

                            rounded-lg

                            bg-amber-600

                            px-4 py-2.5

                            text-sm
                            font-semibold
                            text-white

                            hover:bg-amber-700
                        ">
                Solicitar nova proposta
            </a>

            @endif

        </section>

        @endif


        {{-- ===================================================== --}}
        {{-- APRESENTAÇÃO --}}
        {{-- ===================================================== --}}

        <section
            class="
                overflow-hidden

                rounded-2xl

                border
                border-zinc-200

                bg-white

                shadow-sm

                dark:border-zinc-800
                dark:bg-zinc-900
            ">

            <div class="p-5 sm:p-7">

                <p
                    class="
                        text-xs
                        font-semibold
                        uppercase
                        tracking-wide

                        text-emerald-600
                        dark:text-emerald-400
                    ">
                    Proposta para
                </p>


                <h1
                    class="
                        mt-1

                        text-xl
                        font-bold
                        tracking-tight

                        text-zinc-950
                        dark:text-white

                        sm:text-2xl
                    ">
                    {{ $this->quote->client->name }}
                </h1>


                <div class="mt-6">

                    <h2
                        class="
                            text-lg
                            font-semibold

                            text-zinc-900
                            dark:text-zinc-100
                        ">
                        {{ $this->quote->title }}
                    </h2>


                    @if ($this->quote->description)

                    <p
                        class="
                                mt-2
                                whitespace-pre-line

                                text-sm
                                leading-6

                                text-zinc-600
                                dark:text-zinc-300
                            ">
                        {{ $this->quote->description }}
                    </p>

                    @endif

                </div>


                <div
                    class="
                        mt-6
                        grid
                        gap-4

                        border-t
                        border-zinc-200

                        pt-5

                        sm:grid-cols-2

                        dark:border-zinc-800
                    ">

                    <div>

                        <p class="text-xs text-zinc-400 dark:text-zinc-500">
                            Emitido em
                        </p>

                        <p
                            class="
                                mt-1
                                text-sm
                                font-medium

                                text-zinc-700
                                dark:text-zinc-300
                            ">
                            {{ $this->quote->created_at->format('d/m/Y') }}
                        </p>

                    </div>


                    <div>

                        <p class="text-xs text-zinc-400 dark:text-zinc-500">
                            Válido até
                        </p>

                        <p
                            class="
                                mt-1
                                text-sm
                                font-medium

                                text-zinc-700
                                dark:text-zinc-300
                            ">
                            {{ $this->quote->valid_until?->format('d/m/Y')
                                ?? 'Sem prazo definido'
                            }}
                        </p>

                    </div>

                </div>

            </div>

        </section>


        {{-- ===================================================== --}}
        {{-- ITENS --}}
        {{-- ===================================================== --}}

        <section
            class="
                overflow-hidden

                rounded-2xl

                border
                border-zinc-200

                bg-white

                shadow-sm

                dark:border-zinc-800
                dark:bg-zinc-900
            ">

            <div
                class="
                    flex
                    items-center
                    justify-between

                    border-b
                    border-zinc-200

                    px-5 py-4

                    sm:px-6

                    dark:border-zinc-800
                ">

                <div>

                    <h3 class="font-semibold text-zinc-950 dark:text-white">
                        Itens da proposta
                    </h3>

                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ $this->quote->items->count() }}

                        {{ $this->quote->items->count() === 1
                            ? 'item'
                            : 'itens'
                        }}
                    </p>

                </div>

            </div>


            <div class="divide-y divide-zinc-100 dark:divide-zinc-800">

                @foreach ($this->quote->items as $item)

                <div
                    wire:key="public-item-{{ $item->id }}"
                    class="px-5 py-4 sm:px-6">

                    <div class="flex items-start justify-between gap-4">

                        <div class="min-w-0">

                            <div class="flex flex-wrap items-center gap-2">

                                <p
                                    class="
                                            font-semibold
                                            text-zinc-900
                                            dark:text-zinc-100
                                        ">
                                    {{ $item->description }}
                                </p>


                                <span
                                    class="
                                            inline-flex

                                            rounded-full

                                            px-2 py-0.5

                                            text-[10px]
                                            font-semibold
                                            uppercase
                                            tracking-wide

                                            {{ $this->typeClasses($item->type) }}
                                        ">
                                    {{ $this->typeLabel($item->type) }}
                                </span>

                            </div>


                            <p
                                class="
                                        mt-1.5
                                        text-sm

                                        text-zinc-500
                                        dark:text-zinc-400
                                    ">
                                {{ $this->formatQuantity(
                                        (float) $item->quantity
                                    ) }}

                                {{ $item->unit }}

                                ×

                                R$ {{ number_format(
                                        (float) $item->unit_price,
                                        2,
                                        ',',
                                        '.'
                                    ) }}
                            </p>

                        </div>


                        <div class="shrink-0 text-right">

                            <p
                                class="
                                        whitespace-nowrap

                                        font-bold

                                        text-zinc-950
                                        dark:text-zinc-100
                                    ">
                                R$ {{ number_format(
                                        (float) $item->total,
                                        2,
                                        ',',
                                        '.'
                                    ) }}
                            </p>

                        </div>

                    </div>

                </div>

                @endforeach

            </div>

        </section>


        {{-- ===================================================== --}}
        {{-- VALORES --}}
        {{-- ===================================================== --}}

        <section
            class="
                rounded-2xl

                border
                border-zinc-200

                bg-white

                p-5

                shadow-sm

                sm:p-6

                dark:border-zinc-800
                dark:bg-zinc-900
            ">

            <div class="ml-auto max-w-md">

                <div class="space-y-3">

                    <div class="flex items-center justify-between gap-5 text-sm">

                        <span class="text-zinc-500 dark:text-zinc-400">
                            Subtotal
                        </span>

                        <span class="font-medium text-zinc-800 dark:text-zinc-200">
                            R$ {{ number_format(
                                (float) $this->quote->subtotal,
                                2,
                                ',',
                                '.'
                            ) }}
                        </span>

                    </div>


                    @if ((float) $this->quote->discount > 0)

                    <div class="flex items-center justify-between gap-5 text-sm">

                        <span class="text-zinc-500 dark:text-zinc-400">
                            Desconto
                        </span>

                        <span class="font-medium text-red-600 dark:text-red-400">
                            - R$ {{ number_format(
                                    (float) $this->quote->discount,
                                    2,
                                    ',',
                                    '.'
                                ) }}
                        </span>

                    </div>

                    @endif

                </div>


                <div
                    class="
                        mt-4

                        border-t
                        border-zinc-200

                        pt-4

                        dark:border-zinc-800
                    ">

                    <div class="flex items-end justify-between gap-5">

                        <div>

                            <p
                                class="
                                    text-sm
                                    font-medium

                                    text-zinc-500
                                    dark:text-zinc-400
                                ">
                                Total da proposta
                            </p>

                        </div>


                        <p
                            class="
                                whitespace-nowrap

                                text-2xl
                                font-bold
                                tracking-tight

                                text-zinc-950
                                dark:text-zinc-100

                                sm:text-3xl
                            ">
                            R$ {{ number_format(
                                (float) $this->quote->total,
                                2,
                                ',',
                                '.'
                            ) }}
                        </p>

                    </div>

                </div>

            </div>

        </section>


        {{-- ===================================================== --}}
        {{-- CONDIÇÕES --}}
        {{-- ===================================================== --}}

        @if ($this->quote->notes)

        <section
            class="
                    rounded-2xl

                    border
                    border-zinc-200

                    bg-white

                    p-5

                    shadow-sm

                    sm:p-6

                    dark:border-zinc-800
                    dark:bg-zinc-900
                ">

            <h3 class="font-semibold text-zinc-950 dark:text-white">
                Condições e observações
            </h3>


            <p
                class="
                        mt-3

                        whitespace-pre-line

                        text-sm
                        leading-6

                        text-zinc-600
                        dark:text-zinc-300
                    ">
                {{ $this->quote->notes }}
            </p>

        </section>

        @endif


        {{-- ===================================================== --}}
        {{-- DECISÃO --}}
        {{-- ===================================================== --}}

        @if (in_array($this->quote->status, ['sent', 'viewed'], true))

        <section
            class="
                    overflow-hidden

                    rounded-2xl

                    border
                    border-emerald-200

                    bg-white

                    shadow-sm

                    dark:border-emerald-900/70
                    dark:bg-zinc-900
                ">

            {{-- ESTADO NORMAL --}}

            <div
                x-show="decision === null"
                class="p-5 sm:p-7">

                <div class="text-center">

                    <div
                        class="
                                mx-auto
                                flex size-11
                                items-center
                                justify-center

                                rounded-full

                                bg-emerald-100
                                text-emerald-700

                                dark:bg-emerald-950
                                dark:text-emerald-300
                            ">
                        <svg
                            class="size-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M5 12l4 4L19 6" />
                        </svg>
                    </div>


                    <h3
                        class="
                                mt-4
                                text-lg
                                font-bold

                                text-zinc-950
                                dark:text-white
                            ">
                        Gostaria de aprovar esta proposta?
                    </h3>


                    <p
                        class="
                                mx-auto
                                mt-1
                                max-w-md

                                text-sm
                                leading-6

                                text-zinc-500
                                dark:text-zinc-400
                            ">
                        Sua resposta será registrada e enviada para
                        {{ $this->quote->business->name }}.
                    </p>

                </div>


                <div
                    class="
                            mt-6
                            grid
                            gap-3

                            sm:grid-cols-2
                        ">

                    <button
                        type="button"
                        x-on:click="decision = 'reject'"
                        class="
                                inline-flex
                                items-center
                                justify-center

                                rounded-xl

                                border
                                border-zinc-300

                                bg-white

                                px-5 py-3

                                text-sm
                                font-semibold
                                text-zinc-700

                                transition

                                hover:bg-zinc-100
                                hover:text-zinc-950

                                dark:border-zinc-700
                                dark:bg-zinc-900
                                dark:text-zinc-200
                                dark:hover:bg-zinc-800
                                dark:hover:text-white
                            ">
                        Não vou aprovar
                    </button>


                    <button
                        type="button"
                        x-on:click="decision = 'accept'"
                        class="
                                inline-flex
                                items-center
                                justify-center
                                gap-2

                                rounded-xl

                                bg-emerald-600

                                px-5 py-3

                                text-sm
                                font-bold
                                text-white

                                shadow-sm

                                transition

                                hover:bg-emerald-700

                                dark:bg-emerald-500
                                dark:text-zinc-950
                                dark:hover:bg-emerald-400
                            ">
                        <svg
                            class="size-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M5 12l4 4L19 6" />
                        </svg>

                        Aprovar proposta
                    </button>

                </div>

            </div>


            {{-- CONFIRMAR ACEITE --}}

            <div
                x-cloak
                x-show="decision === 'accept'"
                class="p-5 sm:p-7">

                <div class="mx-auto max-w-lg text-center">

                    <div
                        class="
                                mx-auto
                                flex size-12
                                items-center
                                justify-center

                                rounded-full

                                bg-emerald-100
                                text-emerald-700

                                dark:bg-emerald-950
                                dark:text-emerald-300
                            ">
                        <svg
                            class="size-6"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M5 12l4 4L19 6" />
                        </svg>
                    </div>


                    <h3 class="mt-4 text-lg font-bold text-zinc-950 dark:text-white">
                        Confirmar aprovação?
                    </h3>


                    <p
                        class="
                                mt-2
                                text-sm
                                leading-6

                                text-zinc-500
                                dark:text-zinc-400
                            ">
                        Você está aprovando a proposta no valor de

                        <strong class="text-zinc-900 dark:text-zinc-100">
                            R$ {{ number_format(
                                    (float) $this->quote->total,
                                    2,
                                    ',',
                                    '.'
                                ) }}
                        </strong>.
                    </p>


                    <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-center">

                        <button
                            type="button"
                            x-on:click="decision = null"
                            class="
                                    rounded-lg

                                    border
                                    border-zinc-300

                                    bg-white

                                    px-5 py-2.5

                                    text-sm
                                    font-semibold
                                    text-zinc-700

                                    hover:bg-zinc-100

                                    dark:border-zinc-700
                                    dark:bg-zinc-900
                                    dark:text-zinc-200
                                    dark:hover:bg-zinc-800
                                ">
                            Voltar
                        </button>


                        <button
                            type="button"
                            wire:click="accept"
                            wire:loading.attr="disabled"
                            wire:target="accept"
                            class="
                                    rounded-lg

                                    bg-emerald-600

                                    px-5 py-2.5

                                    text-sm
                                    font-bold
                                    text-white

                                    hover:bg-emerald-700

                                    disabled:opacity-60

                                    dark:bg-emerald-500
                                    dark:text-zinc-950
                                ">
                            <span wire:loading.remove wire:target="accept">
                                Sim, aprovar proposta
                            </span>

                            <span wire:loading wire:target="accept">
                                Confirmando...
                            </span>
                        </button>

                    </div>

                </div>

            </div>


            {{-- CONFIRMAR RECUSA --}}

            <div
                x-cloak
                x-show="decision === 'reject'"
                class="p-5 sm:p-7">

                <div class="mx-auto max-w-lg text-center">

                    <h3 class="text-lg font-bold text-zinc-950 dark:text-white">
                        Confirmar recusa?
                    </h3>

                    <p
                        class="
                                mt-2
                                text-sm
                                leading-6

                                text-zinc-500
                                dark:text-zinc-400
                            ">
                        Confirme apenas se você realmente não deseja aprovar
                        esta proposta neste momento.
                    </p>


                    <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-center">

                        <button
                            type="button"
                            x-on:click="decision = null"
                            class="
                                    rounded-lg

                                    border
                                    border-zinc-300

                                    bg-white

                                    px-5 py-2.5

                                    text-sm
                                    font-semibold
                                    text-zinc-700

                                    hover:bg-zinc-100

                                    dark:border-zinc-700
                                    dark:bg-zinc-900
                                    dark:text-zinc-200
                                    dark:hover:bg-zinc-800
                                ">
                            Voltar
                        </button>


                        <button
                            type="button"
                            wire:click="reject"
                            wire:loading.attr="disabled"
                            wire:target="reject"
                            class="
                                    rounded-lg

                                    bg-red-600

                                    px-5 py-2.5

                                    text-sm
                                    font-semibold
                                    text-white

                                    hover:bg-red-700

                                    disabled:opacity-60
                                ">
                            <span wire:loading.remove wire:target="reject">
                                Confirmar recusa
                            </span>

                            <span wire:loading wire:target="reject">
                                Confirmando...
                            </span>
                        </button>

                    </div>

                </div>

            </div>

        </section>

        @endif


        {{-- ===================================================== --}}
        {{-- CONTATO --}}
        {{-- ===================================================== --}}

        @if (
        $this->businessWhatsappUrl
        && in_array($this->quote->status, ['sent', 'viewed'], true)
        )

        <div class="text-center">

            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                Ficou com alguma dúvida?
            </p>

            <a
                href="{{ $this->businessWhatsappUrl }}"
                target="_blank"
                rel="noopener noreferrer"
                class="
                        mt-1
                        inline-flex

                        text-sm
                        font-semibold

                        text-emerald-600

                        hover:underline

                        dark:text-emerald-400
                    ">
                Falar com {{ $this->quote->business->name }}
            </a>

        </div>

        @endif


        {{-- ===================================================== --}}
        {{-- RODAPÉ --}}
        {{-- ===================================================== --}}

        <footer class="pb-8 pt-3 text-center">

            <div
                class="
                    inline-flex
                    items-center
                    gap-1.5

                    text-xs
                    text-zinc-400
                    dark:text-zinc-600
                ">

                <div
                    class="
                        flex size-5
                        items-center
                        justify-center

                        rounded-md

                        bg-zinc-200

                        text-[10px]
                        font-bold
                        text-zinc-600

                        dark:bg-zinc-800
                        dark:text-zinc-400
                    ">
                    ✓
                </div>

                Orçamento digital via Fechou

            </div>

        </footer>

    </main>

</div>