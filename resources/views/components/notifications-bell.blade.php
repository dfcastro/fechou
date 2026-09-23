<?php

use App\Enums\PlanFeature;
use App\Models\Quote;
use App\Services\SubscriptionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    /*
    |--------------------------------------------------------------------------
    | Empresa
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function business()
    {
        return Auth::user()?->business;
    }


    #[Computed]
    public function hasAccess(): bool
    {
        if (!$this->business) {
            return false;
        }

        return app(SubscriptionService::class)
            ->hasFeature(
                $this->business,
                PlanFeature::NOTIFICATIONS
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Última versão de cada proposta
    |--------------------------------------------------------------------------
    */

    private function latestFamilyQuery(): Builder
    {
        if (!$this->business) {
            return Quote::query()
                ->whereRaw('1 = 0');
        }

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
    | Base das notificações
    |--------------------------------------------------------------------------
    */

    private function attentionBaseQuery(): Builder
    {
        if (
            !$this->business
            || !$this->hasAccess
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
                [
                    'sent',
                    'viewed',
                ]
            )

            ->where(
                function ($query) use ($sentThreshold, $viewedThreshold, $today, $expiryEnd) {

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
                     * Visualizada sem resposta.
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
                     * Enviada e ainda não visualizada.
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
                }
            )

            /*
             * Se houve follow-up recentemente,
             * não mostra novamente.
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
    | Quantidade
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function count(): int
    {
        return (clone $this->attentionBaseQuery())
            ->count();
    }


    /*
    |--------------------------------------------------------------------------
    | Notificações
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function notifications()
    {
        if (
            !$this->business
            || !$this->hasAccess
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

            ->limit(8)
            ->get();
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
        if (
            !$this->business
            || !$this->hasAccess
        ) {
            return;
        }

        $quote = Quote::query()
            ->where(
                'business_id',
                $this->business->id
            )
            ->whereKey($quoteId)
            ->whereIn(
                'status',
                [
                    'sent',
                    'viewed',
                ]
            )
            ->firstOrFail();


        /*
         * Evita duplicidade caso o usuário
         * clique várias vezes rapidamente.
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

            $quote->events()->create([
                'type' => 'follow_up',

                'metadata' => [
                    'channel' => 'whatsapp',
                    'origin' => 'notifications',
                ],
            ]);

        }


        /*
         * Força atualização do sino imediatamente.
         *
         * Como existe cooldown, a proposta some
         * da lista após o follow-up.
         */
        unset(
            $this->count,
            $this->notifications
        );
    }


    /*
    |--------------------------------------------------------------------------
    | WhatsApp
    |--------------------------------------------------------------------------
    */

    public function followUpUrl(
        Quote $quote
    ): ?string {
        $phone =
            $quote->client?->whatsapp
            ?: $quote->client?->phone;

        if (!$phone) {
            return null;
        }


        /*
         * Mantém somente números.
         */
        $phone = preg_replace(
            '/\D+/',
            '',
            $phone
        );


        if (!$phone) {
            return null;
        }


        /*
         * Caso seja número brasileiro sem DDI,
         * adiciona 55.
         *
         * Ex.:
         * 38999999999
         * ->
         * 5538999999999
         */
        if (
            strlen($phone) <= 11
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


        $clientName =
            trim(
                $quote->client?->name
                ?? ''
            );


        /*
         * Primeiro nome para a mensagem
         * ficar mais natural.
         */
        $firstName = $clientName
            ? explode(
                ' ',
                $clientName
            )[0]
            : 'Olá';


        $publicUrl = route(
            'quotes.public',
            $quote->public_token
        );


        /*
         * Mensagem contextual.
         */
        if (
            $quote->valid_until
            && $quote->valid_until->isToday()
        ) {

            $message =
                "Olá, {$firstName}! Tudo bem? 😊\n\n"
                . "Passando para lembrar que a proposta "
                . "#{$number} vence hoje.\n\n"
                . "Caso tenha alguma dúvida ou queira conversar "
                . "sobre algum detalhe, estou à disposição.\n\n"
                . "Você pode visualizar a proposta aqui:\n"
                . $publicUrl;

        } elseif (
            $quote->valid_until
            && $quote->valid_until->isTomorrow()
        ) {

            $message =
                "Olá, {$firstName}! Tudo bem? 😊\n\n"
                . "Passando para saber se conseguiu avaliar "
                . "a proposta #{$number}.\n\n"
                . "Ela vence amanhã. Se precisar ajustar "
                . "algum detalhe ou tiver alguma dúvida, "
                . "estou à disposição.\n\n"
                . "Proposta:\n"
                . $publicUrl;

        } elseif (
            $quote->status === 'viewed'
        ) {

            $message =
                "Olá, {$firstName}! Tudo bem? 😊\n\n"
                . "Vi que você conseguiu visualizar "
                . "a proposta #{$number} e queria saber "
                . "se ficou alguma dúvida ou se posso ajudar "
                . "em algum ponto.\n\n"
                . "Se precisar ajustar alguma coisa, "
                . "é só me chamar.\n\n"
                . "Proposta:\n"
                . $publicUrl;

        } else {

            $message =
                "Olá, {$firstName}! Tudo bem? 😊\n\n"
                . "Passando para saber se conseguiu receber "
                . "e visualizar a proposta #{$number} "
                . "que enviei.\n\n"
                . "Se precisar de qualquer informação, "
                . "estou à disposição.\n\n"
                . "Proposta:\n"
                . $publicUrl;

        }


        return
            'https://wa.me/'
            . $phone
            . '?text='
            . rawurlencode($message);
    }


    /*
    |--------------------------------------------------------------------------
    | Título da notificação
    |--------------------------------------------------------------------------
    */

    public function notificationTitle(
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
                    return 'Proposta vence hoje';
                }


                if ($expiry->isTomorrow()) {
                    return 'Proposta vence amanhã';
                }


                $days = (int) $today
                    ->diffInDays($expiry);


                return
                    "Proposta vence em {$days} dias";
            }
        }


        if ($quote->status === 'viewed') {
            return 'Cliente visualizou e não respondeu';
        }


        return 'Cliente ainda não visualizou';
    }


    /*
    |--------------------------------------------------------------------------
    | Descrição
    |--------------------------------------------------------------------------
    */

    public function notificationDescription(
        Quote $quote
    ): string {
        $number = str_pad(
            $quote->number,
            4,
            '0',
            STR_PAD_LEFT
        );


        $clientName =
            $quote->client?->name
            ?? 'Cliente';


        return
            "#{$number} • {$clientName}";
    }


    /*
    |--------------------------------------------------------------------------
    | Tempo
    |--------------------------------------------------------------------------
    */

    public function notificationTime(
        Quote $quote
    ): ?string {
        if (
            $quote->valid_until
            && $quote->valid_until->isToday()
        ) {
            return 'Vence hoje';
        }


        if (
            $quote->valid_until
            && $quote->valid_until->isTomorrow()
        ) {
            return 'Vence amanhã';
        }


        if (
            $quote->status === 'viewed'
            && $quote->first_viewed_at
        ) {
            return $this->elapsedTime(
                $quote->first_viewed_at
            );
        }


        if (
            $quote->status === 'sent'
            && $quote->sent_at
        ) {
            return $this->elapsedTime(
                $quote->sent_at
            );
        }


        return null;
    }


    private function elapsedTime(
        $date
    ): string {
        $hours = (int) $date
            ->diffInHours(
                now()
            );


        if ($hours < 1) {
            return 'Agora há pouco';
        }


        if ($hours < 24) {

            return $hours === 1
                ? 'Há 1 hora'
                : "Há {$hours} horas";

        }


        $days = (int) $date
            ->diffInDays(
                now()
            );


        return $days === 1
            ? 'Há 1 dia'
            : "Há {$days} dias";
    }


    /*
    |--------------------------------------------------------------------------
    | Cores
    |--------------------------------------------------------------------------
    */

    public function iconClasses(
        Quote $quote
    ): string {
        if (
            $quote->valid_until
            && $quote->valid_until->isToday()
        ) {
            return '
                bg-red-100
                text-red-600

                dark:bg-red-950
                dark:text-red-400
            ';
        }


        if (
            $quote->valid_until
            && $quote->valid_until->isTomorrow()
        ) {
            return '
                bg-orange-100
                text-orange-600

                dark:bg-orange-950
                dark:text-orange-400
            ';
        }


        if ($quote->status === 'viewed') {
            return '
                bg-amber-100
                text-amber-700

                dark:bg-amber-950
                dark:text-amber-300
            ';
        }


        return '
            bg-blue-100
            text-blue-700

            dark:bg-blue-950
            dark:text-blue-300
        ';
    }
};

?>


<div>

    @if ($this->hasAccess)

        <div x-data="{ open: false }" wire:poll.60s class="
            fixed
            right-3
            top-2.5
            z-[100]

            sm:right-4

            lg:right-6
            lg:top-4
        " @keydown.escape.window="open = false">

            {{-- ========================================================= --}}
            {{-- BOTÃO DO SINO --}}
            {{-- ========================================================= --}}

            <button type="button" @click="open = !open" :aria-expanded="open" aria-label="Notificações" class="
                relative

                flex size-10
                items-center
                justify-center

                rounded-xl

                border border-zinc-200

                bg-white

                text-zinc-600

                shadow-sm

                transition

                hover:bg-zinc-50
                hover:text-zinc-950

                focus:outline-none
                focus:ring-2
                focus:ring-emerald-500/30

                dark:border-zinc-800
                dark:bg-zinc-900
                dark:text-zinc-300

                dark:hover:bg-zinc-800
                dark:hover:text-white
            ">

                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="
                        M18 8
                        a6 6 0 1 0-12 0
                        c0 7-3 7-3 9
                        h18
                        c0-2-3-2-3-9
                    " />

                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 21h4" />
                </svg>


                @if ($this->count > 0)

                    <span class="
                                absolute
                                -right-1
                                -top-1

                                flex h-5
                                min-w-5

                                items-center
                                justify-center

                                rounded-full

                                bg-red-600

                                px-1

                                text-[10px]
                                font-bold
                                leading-none
                                text-white

                                ring-2
                                ring-zinc-50

                                dark:ring-zinc-950
                            ">
                        {{ $this->count > 99
                    ? '99+'
                    : $this->count
                            }}
                    </span>

                @endif

            </button>


            {{-- ========================================================= --}}
            {{-- PAINEL --}}
            {{-- ========================================================= --}}

            <div x-cloak x-show="open" @click.outside="open = false" x-transition:enter="
                transition
                ease-out
                duration-150
            " x-transition:enter-start="
                opacity-0
                translate-y-1
                scale-[0.98]
            " x-transition:enter-end="
                opacity-100
                translate-y-0
                scale-100
            " x-transition:leave="
                transition
                ease-in
                duration-100
            " x-transition:leave-start="
                opacity-100
                translate-y-0
                scale-100
            " x-transition:leave-end="
                opacity-0
                translate-y-1
                scale-[0.98]
            " class="
                absolute
                right-0
                top-full

                mt-2

                w-[400px]
                max-w-[calc(100vw-1.5rem)]

                overflow-hidden

                rounded-2xl

                border border-zinc-200

                bg-white

                shadow-2xl

                dark:border-zinc-700
                dark:bg-zinc-900
            ">

                {{-- ===================================================== --}}
                {{-- CABEÇALHO --}}
                {{-- ===================================================== --}}

                <div class="
                    flex
                    items-start
                    justify-between
                    gap-4

                    border-b
                    border-zinc-200

                    px-4 py-3.5

                    dark:border-zinc-800
                ">

                    <div>

                        <p class="
                            text-sm
                            font-semibold

                            text-zinc-950
                            dark:text-white
                        ">
                            Notificações
                        </p>


                        <p class="
                            mt-0.5

                            text-xs

                            text-zinc-500
                            dark:text-zinc-400
                        ">
                            Propostas que precisam de atenção
                        </p>

                    </div>


                    @if ($this->count > 0)

                        <span class="
                                    inline-flex
                                    min-w-6

                                    items-center
                                    justify-center

                                    rounded-full

                                    bg-red-100

                                    px-2 py-1

                                    text-[11px]
                                    font-bold
                                    text-red-700

                                    dark:bg-red-950
                                    dark:text-red-300
                                ">
                            {{ $this->count }}
                        </span>

                    @endif

                </div>


                {{-- ===================================================== --}}
                {{-- LISTA --}}
                {{-- ===================================================== --}}

                @if ($this->notifications->isNotEmpty())

                    <div class="
                                max-h-[500px]

                                divide-y
                                divide-zinc-100

                                overflow-y-auto

                                overscroll-contain

                                dark:divide-zinc-800
                            ">

                        @foreach ($this->notifications as $quote)

                                @php
                                    $followUpUrl =
                                        $this->followUpUrl($quote);
                                @endphp


                                <div wire:key="notification-{{ $quote->id }}" class="
                                                        px-4
                                                        py-3.5

                                                        transition

                                                        hover:bg-zinc-50

                                                        dark:hover:bg-zinc-800/40
                                                    ">

                                    <div class="
                                                            flex
                                                            items-start
                                                            gap-3
                                                        ">

                                        {{-- ================================= --}}
                                        {{-- ÍCONE --}}
                                        {{-- ================================= --}}

                                        <div class="
                                                                mt-0.5

                                                                flex size-9
                                                                shrink-0

                                                                items-center
                                                                justify-center

                                                                rounded-full

                                                                {{ $this->iconClasses($quote) }}
                                                            ">

                                            @if (
                                                    $quote->valid_until
                                                    && (
                                                        $quote->valid_until->isToday()
                                                        || $quote->valid_until->isTomorrow()
                                                    )
                                                )

                                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="
                                                                                    M12 9v4
                                                                                    m0 4h.01
                                                                                    M10.3 4.3
                                                                                    2.5 18
                                                                                    a2 2 0 0 0 1.7 3
                                                                                    h15.6
                                                                                    a2 2 0 0 0 1.7-3
                                                                                    L13.7 4.3
                                                                                    a2 2 0 0 0-3.4 0Z
                                                                                " />
                                                </svg>


                                            @elseif ($quote->status === 'viewed')

                                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="
                                                                                    M2.5 12
                                                                                    s3.5-6 9.5-6
                                                                                    9.5 6
                                                                                    9.5 6
                                                                                    -3.5 6
                                                                                    -9.5 6
                                                                                    -9.5-6
                                                                                    -9.5-6Z
                                                                                " />

                                                    <circle cx="12" cy="12" r="2.5" />
                                                </svg>


                                            @else

                                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16v12H4z" />

                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4 7 8 6 8-6" />
                                                </svg>

                                            @endif

                                        </div>


                                        {{-- ================================= --}}
                                        {{-- CONTEÚDO --}}
                                        {{-- ================================= --}}

                                        <div class="min-w-0 flex-1">

                                            <a href="{{ route(
                                'quotes.show',
                                $quote->id
                            ) }}" wire:navigate @click="open = false" class="
                                                                    block

                                                                    rounded-md

                                                                    focus:outline-none
                                                                    focus:ring-2
                                                                    focus:ring-emerald-500/30
                                                                ">

                                                <p class="
                                                                        text-sm
                                                                        font-semibold

                                                                        text-zinc-800

                                                                        hover:text-zinc-950

                                                                        dark:text-zinc-200
                                                                        dark:hover:text-white
                                                                    ">
                                                    {{ $this->notificationTitle(
                                $quote
                            ) }}
                                                </p>


                                                <p class="
                                                                        mt-0.5

                                                                        truncate

                                                                        text-xs

                                                                        text-zinc-500
                                                                        dark:text-zinc-400
                                                                    ">
                                                    {{ $this->notificationDescription(
                                $quote
                            ) }}
                                                </p>


                                                <div class="
                                                                        mt-1.5

                                                                        flex
                                                                        flex-wrap
                                                                        items-center

                                                                        gap-x-2
                                                                        gap-y-1
                                                                    ">

                                                    <span class="
                                                                            text-xs
                                                                            font-semibold

                                                                            text-zinc-700
                                                                            dark:text-zinc-300
                                                                        ">
                                                        R$ {{ number_format(
                                (float) $quote->total,
                                2,
                                ',',
                                '.'
                            ) }}
                                                    </span>


                                                    @if ($quote->version > 1)

                                                        <span class="
                                                                                        rounded-full

                                                                                        bg-violet-100

                                                                                        px-1.5
                                                                                        py-0.5

                                                                                        text-[10px]
                                                                                        font-semibold
                                                                                        text-violet-700

                                                                                        dark:bg-violet-950
                                                                                        dark:text-violet-300
                                                                                    ">
                                                            V{{ $quote->version }}
                                                        </span>

                                                    @endif


                                                    @if (
                                                                                $this->notificationTime(
                                                                                    $quote
                                                                                )
                                                                            )

                                                                            <span class="
                                                                                                                                text-[11px]

                                                                                                                                text-zinc-400
                                                                                                                                dark:text-zinc-500
                                                                                                                            ">
                                                                                •
                                                                                {{ $this->notificationTime(
                                                            $quote
                                                        ) }}
                                                                            </span>

                                                    @endif

                                                </div>

                                            </a>


                                            {{-- ================================= --}}
                                            {{-- AÇÕES --}}
                                            {{-- ================================= --}}

                                            <div class="
                                                                    mt-3

                                                                    flex
                                                                    items-center
                                                                    gap-2
                                                                ">

                                                {{-- VER ORÇAMENTO --}}

                                                <a href="{{ route(
                                'quotes.show',
                                $quote->id
                            ) }}" wire:navigate @click="open = false" class="
                                                                        inline-flex
                                                                        items-center
                                                                        justify-center
                                                                        gap-1.5

                                                                        rounded-lg

                                                                        border
                                                                        border-zinc-200

                                                                        bg-white

                                                                        px-2.5
                                                                        py-1.5

                                                                        text-[11px]
                                                                        font-semibold
                                                                        text-zinc-600

                                                                        transition

                                                                        hover:bg-zinc-100
                                                                        hover:text-zinc-950

                                                                        dark:border-zinc-700
                                                                        dark:bg-zinc-900
                                                                        dark:text-zinc-300

                                                                        dark:hover:bg-zinc-800
                                                                        dark:hover:text-white
                                                                    ">

                                                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="
                                                                                M2.5 12
                                                                                s3.5-6 9.5-6
                                                                                9.5 6
                                                                                9.5 6
                                                                                -3.5 6
                                                                                -9.5 6
                                                                                -9.5-6
                                                                                -9.5-6Z
                                                                            " />

                                                        <circle cx="12" cy="12" r="2.5" />
                                                    </svg>

                                                    Ver orçamento

                                                </a>


                                                {{-- WHATSAPP --}}

                                                @if (! auth()->user()?->hasVerifiedEmail())

                                                    {-- VERIFICACAO DE E-MAIL - FOLLOW-UP NOTIFICACOES --}

                                                    <a
                                                        href="{{ route('verification.notice') }}"
                                                        wire:navigate
                                                        @click.stop
                                                        title="Confirme seu e-mail para fazer follow-up"
                                                        class="
                                                            inline-flex items-center justify-center gap-1.5
                                                            rounded-lg
                                                            border border-amber-300 bg-amber-50
                                                            px-2.5 py-1.5
                                                            text-[11px] font-semibold text-amber-800
                                                            transition hover:bg-amber-100
                                                            dark:border-amber-800
                                                            dark:bg-amber-950/30
                                                            dark:text-amber-200
                                                            dark:hover:bg-amber-950/50
                                                        "
                                                    >
                                                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6.5h16v11H4z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="m5 8 7 5 7-5" />
                                                        </svg>

                                                        Verificar e-mail
                                                    </a>

                                                @elseif ($followUpUrl)

                                                    <a href="{{ $followUpUrl }}" target="_blank" rel="noopener noreferrer" wire:click="
                                                                                    registerFollowUp(
                                                                                        {{ $quote->id }}
                                                                                    )
                                                                                " @click.stop class="
                                                                                    inline-flex
                                                                                    items-center
                                                                                    justify-center
                                                                                    gap-1.5

                                                                                    rounded-lg

                                                                                    bg-emerald-600

                                                                                    px-2.5
                                                                                    py-1.5

                                                                                    text-[11px]
                                                                                    font-semibold
                                                                                    text-white

                                                                                    transition

                                                                                    hover:bg-emerald-700

                                                                                    dark:bg-emerald-500
                                                                                    dark:text-zinc-950

                                                                                    dark:hover:bg-emerald-400
                                                                                ">

                                                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                            stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="
                                                                                            M21 11.5
                                                                                            a8.4 8.4 0 0 1-9 8.4
                                                                                            9.5 9.5 0 0 1-4-.9
                                                                                            L3 20.5
                                                                                            4.5 16
                                                                                            a8.4 8.4 0 1 1
                                                                                            16.5-4.5Z
                                                                                        " />

                                                            <path stroke-linecap="round" stroke-linejoin="round" d="
                                                                                            M8.5 8.5
                                                                                            c.5 3
                                                                                            2 4.5
                                                                                            5 5
                                                                                        " />
                                                        </svg>

                                                        Follow-up

                                                    </a>


                                                @else

                                                    <span title="Cliente sem WhatsApp ou telefone cadastrado" class="
                                                                                    inline-flex
                                                                                    cursor-not-allowed
                                                                                    items-center
                                                                                    justify-center
                                                                                    gap-1.5

                                                                                    rounded-lg

                                                                                    bg-zinc-100

                                                                                    px-2.5
                                                                                    py-1.5

                                                                                    text-[11px]
                                                                                    font-semibold
                                                                                    text-zinc-400

                                                                                    dark:bg-zinc-800
                                                                                    dark:text-zinc-500
                                                                                ">

                                                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                            stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="
                                                                                            M21 11.5
                                                                                            a8.4 8.4 0 0 1-9 8.4
                                                                                            9.5 9.5 0 0 1-4-.9
                                                                                            L3 20.5
                                                                                            4.5 16
                                                                                            a8.4 8.4 0 1 1
                                                                                            16.5-4.5Z
                                                                                        " />
                                                        </svg>

                                                        Sem WhatsApp

                                                    </span>

                                                @endif

                                            </div>

                                        </div>

                                    </div>

                                </div>

                        @endforeach

                    </div>


                @else

                    {{-- ================================================= --}}
                    {{-- SEM NOTIFICAÇÕES --}}
                    {{-- ================================================= --}}

                    <div class="px-6 py-10 text-center">

                        <div class="
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

                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12l4 4L19 6" />
                            </svg>

                        </div>


                        <p class="
                                    mt-3

                                    text-sm
                                    font-semibold

                                    text-zinc-800
                                    dark:text-zinc-200
                                ">
                            Tudo em dia
                        </p>


                        <p class="
                                    mt-1

                                    text-xs

                                    text-zinc-500
                                    dark:text-zinc-400
                                ">
                            Nenhuma proposta precisa de atenção agora.
                        </p>

                    </div>

                @endif


                {{-- ===================================================== --}}
                {{-- RODAPÉ --}}
                {{-- ===================================================== --}}

                <div class="
                    border-t
                    border-zinc-200

                    bg-zinc-50

                    px-4 py-3

                    text-center

                    dark:border-zinc-800
                    dark:bg-zinc-950/50
                ">

                    <a href="{{ route('dashboard') }}" wire:navigate @click="open = false" class="
                        text-xs
                        font-semibold

                        text-emerald-600

                        hover:text-emerald-700

                        dark:text-emerald-400
                        dark:hover:text-emerald-300
                    ">
                        Ver todas no Dashboard
                    </a>

                </div>

            </div>

    @endif

    </div>