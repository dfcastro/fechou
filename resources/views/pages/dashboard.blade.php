<?php

use App\Models\Quote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard | Fechou')] class extends Component
{
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
    | Indicadores
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function waitingCount(): int
    {
        if (! $this->business) {
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
        if (! $this->business) {
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
        if (! $this->business) {
            return 0;
        }

        return (clone $this->latestFamilyQuery())
            ->where('status', 'viewed')
            ->count();
    }

    #[Computed]
    public function acceptedCount(): int
    {
        if (! $this->business) {
            return 0;
        }

        return (clone $this->latestFamilyQuery())
            ->where('status', 'accepted')
            ->count();
    }

    #[Computed]
    public function acceptedAmount(): float
    {
        if (! $this->business) {
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

    #[Computed]
    public function attentionQuotes()
    {
        if (! $this->business) {
            return collect();
        }

        return (clone $this->latestFamilyQuery())
            ->with([
                'client',

                'events' => fn($query) =>
                $query
                    ->where('type', 'follow_up')
                    ->orderByDesc('created_at'),
            ])
            ->whereIn(
                'status',
                ['sent', 'viewed']
            )
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()

            /*
             * Identifica somente propostas que realmente
             * precisam de alguma ação.
             */
            ->filter(
                fn(Quote $quote) =>
                $this->needsAttention($quote)
            )

            /*
             * Ordena por prioridade.
             */
            ->sortBy(
                fn(Quote $quote) =>
                $this->attentionPriority($quote)
            )

            ->take(6)
            ->values();
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

            if ($quote->valid_until->isToday()) {
                return 'Vence hoje';
            }

            if ($quote->valid_until->isTomorrow()) {
                return 'Vence amanhã';
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
            && ! str_starts_with(
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
        $business = Auth::user()->business;

        abort_unless(
            $business,
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

        if (! $recentFollowUp) {

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
    | Orçamentos recentes
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function recentQuotes()
    {
        if (! $this->business) {
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

<div class="mx-auto max-w-7xl space-y-6">

    {{-- ========================================================= --}}
    {{-- CABEÇALHO --}}
    {{-- ========================================================= --}}

    <div
        class="
            flex flex-col gap-4

            sm:flex-row
            sm:items-center
            sm:justify-between
        ">

        <div>

            <h1
                class="
                    text-2xl
                    font-semibold
                    tracking-tight

                    text-zinc-950
                    dark:text-white
                ">
                Dashboard
            </h1>

            <p
                class="
                    mt-1
                    text-sm

                    text-zinc-500
                    dark:text-zinc-400
                ">
                Acompanhe suas propostas e veja onde agir para fechar mais.
            </p>

        </div>


        <a
            href="{{ route('quotes.create') }}"
            wire:navigate

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
            <svg
                class="size-4"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2">
                <path
                    stroke-linecap="round"
                    d="M12 5v14M5 12h14" />
            </svg>

            Novo orçamento
        </a>

    </div>


    {{-- ========================================================= --}}
    {{-- INDICADORES --}}
    {{-- ========================================================= --}}

    <div
        class="
            grid grid-cols-1
            gap-3

            sm:grid-cols-2
            lg:grid-cols-4
        ">

        {{-- AGUARDANDO --}}

        <div
            class="
                rounded-xl

                border border-amber-200
                bg-amber-50

                p-4

                dark:border-amber-900/60
                dark:bg-amber-950/20
            ">

            <p
                class="
                    text-xs font-semibold

                    text-amber-700
                    dark:text-amber-400
                ">
                Aguardando cliente
            </p>

            <div
                class="
                    mt-2

                    flex items-end
                    justify-between
                    gap-3
                ">

                <p
                    class="
                        text-2xl font-bold

                        text-zinc-950
                        dark:text-zinc-100
                    ">
                    {{ $this->waitingCount }}
                </p>

                <p
                    class="
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

        <div
            class="
                rounded-xl

                border border-blue-200
                bg-blue-50

                p-4

                dark:border-blue-900/60
                dark:bg-blue-950/20
            ">

            <p
                class="
                    text-xs font-semibold

                    text-blue-700
                    dark:text-blue-400
                ">
                Visualizados
            </p>

            <p
                class="
                    mt-2

                    text-2xl font-bold

                    text-zinc-950
                    dark:text-zinc-100
                ">
                {{ $this->viewedCount }}
            </p>

        </div>


        {{-- ACEITOS --}}

        <div
            class="
                rounded-xl

                border border-emerald-200
                bg-emerald-50

                p-4

                dark:border-emerald-900/60
                dark:bg-emerald-950/20
            ">

            <p
                class="
                    text-xs font-semibold

                    text-emerald-700
                    dark:text-emerald-400
                ">
                Aceitos
            </p>

            <div
                class="
                    mt-2

                    flex items-end
                    justify-between
                    gap-3
                ">

                <p
                    class="
                        text-2xl font-bold

                        text-zinc-950
                        dark:text-zinc-100
                    ">
                    {{ $this->acceptedCount }}
                </p>

                <p
                    class="
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


        {{-- PRECISAM DE ATENÇÃO --}}

        <div
            class="
                rounded-xl

                border border-red-200
                bg-red-50

                p-4

                dark:border-red-900/60
                dark:bg-red-950/20
            ">

            <p
                class="
                    text-xs font-semibold

                    text-red-700
                    dark:text-red-400
                ">
                Precisam de atenção
            </p>

            <p
                class="
                    mt-2

                    text-2xl font-bold

                    text-zinc-950
                    dark:text-zinc-100
                ">
                {{ $this->attentionQuotes->count() }}
            </p>

        </div>

    </div>


    {{-- ========================================================= --}}
    {{-- PRECISAM DE ATENÇÃO --}}
    {{-- ========================================================= --}}

    <section
        class="
            overflow-hidden

            rounded-2xl

            border border-zinc-200

            bg-white

            shadow-sm

            dark:border-zinc-800
            dark:bg-zinc-900
        ">

        <div
            class="
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

                    <div
                        class="
                            flex size-8
                            items-center justify-center

                            rounded-lg

                            bg-amber-100
                            text-amber-700

                            dark:bg-amber-950
                            dark:text-amber-300
                        ">
                        <svg
                            class="size-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 9v4m0 4h.01M10.3 4.3 2.5 18a2 2 0 0 0 1.7 3h15.6a2 2 0 0 0 1.7-3L13.7 4.3a2 2 0 0 0-3.4 0Z" />
                        </svg>
                    </div>


                    <div>

                        <h2
                            class="
                                font-semibold

                                text-zinc-950
                                dark:text-white
                            ">
                            Precisam de atenção
                        </h2>

                        <p
                            class="
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

        <div
            class="
                    divide-y divide-zinc-100

                    dark:divide-zinc-800
                ">

            @foreach ($this->attentionQuotes as $quote)

            <div
                wire:key="attention-{{ $quote->id }}"

                class="
                            px-5 py-4

                            sm:px-6
                        ">

                <div
                    class="
                                flex flex-col
                                gap-4

                                lg:flex-row
                                lg:items-center
                                lg:justify-between
                            ">

                    {{-- INFORMAÇÕES --}}

                    <div class="min-w-0">

                        <div
                            class="
                                        flex flex-wrap
                                        items-center
                                        gap-2
                                    ">

                            <a
                                href="{{ route(
                                            'quotes.show',
                                            $quote->id
                                        ) }}"

                                wire:navigate

                                class="
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

                            <span
                                class="
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


                            <span
                                class="
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


                        <p
                            class="
                                        mt-1

                                        truncate

                                        font-semibold

                                        text-zinc-800
                                        dark:text-zinc-200
                                    ">
                            {{ $quote->client->name }}
                        </p>


                        <p
                            class="
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

                    <div
                        class="
                                    flex flex-col
                                    gap-3

                                    sm:flex-row
                                    sm:items-center

                                    lg:shrink-0
                                ">

                        <div class="sm:text-right">

                            <p
                                class="
                                            text-xs

                                            text-zinc-400
                                            dark:text-zinc-500
                                        ">
                                Valor
                            </p>

                            <p
                                class="
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


                        <a
                            href="{{ $this->followUpUrl(
                                        $quote
                                    ) }}"

                            target="_blank"
                            rel="noopener noreferrer"

                            wire:click="registerFollowUp({{ $quote->id }})"

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

                            <svg
                                class="size-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M21 11.5a8.5 8.5 0 0 1-12.6 7.4L3 20l1.2-5A8.5 8.5 0 1 1 21 11.5Z" />

                                <path
                                    stroke-linecap="round"
                                    d="M8.5 8.5c.7 3 2.2 4.5 5 5" />
                            </svg>

                            Fazer follow-up

                        </a>

                    </div>

                </div>

            </div>

            @endforeach

        </div>


        @else

        <div class="px-6 py-10 text-center">

            <div
                class="
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


            <p
                class="
                        mt-3

                        font-medium

                        text-zinc-800
                        dark:text-zinc-200
                    ">
                Tudo em dia
            </p>


            <p
                class="
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


    {{-- ========================================================= --}}
    {{-- ORÇAMENTOS RECENTES --}}
    {{-- ========================================================= --}}

    <section
        class="
            overflow-hidden

            rounded-2xl

            border border-zinc-200

            bg-white

            shadow-sm

            dark:border-zinc-800
            dark:bg-zinc-900
        ">

        <div
            class="
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

                <h2
                    class="
                        font-semibold

                        text-zinc-950
                        dark:text-white
                    ">
                    Orçamentos recentes
                </h2>

                <p
                    class="
                        mt-0.5

                        text-xs

                        text-zinc-500
                        dark:text-zinc-400
                    ">
                    Últimas propostas movimentadas.
                </p>

            </div>


            <a
                href="{{ route('quotes.index') }}"
                wire:navigate

                class="
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

        <a
            href="{{ route(
                    'quotes.show',
                    $quote->id
                ) }}"

            wire:navigate

            wire:key="recent-{{ $quote->id }}"

            class="
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

                <div
                    class="
                            flex flex-wrap
                            items-center
                            gap-2
                        ">

                    <span
                        class="
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


                    <span
                        class="
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

                    <span
                        class="
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


                <p
                    class="
                            mt-1
                            truncate

                            text-sm
                            font-medium

                            text-zinc-700
                            dark:text-zinc-300
                        ">
                    {{ $quote->client->name }}
                </p>


                <p
                    class="
                            mt-0.5
                            truncate

                            text-xs

                            text-zinc-500
                            dark:text-zinc-400
                        ">
                    {{ $quote->title }}
                </p>

            </div>


            <div
                class="
                        flex shrink-0
                        items-center
                        gap-4
                    ">

                <p
                    class="
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


                <svg
                    class="
                            size-4

                            text-zinc-300

                            transition

                            group-hover:translate-x-1
                            group-hover:text-zinc-500

                            dark:text-zinc-600
                        "
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="m9 18 6-6-6-6" />
                </svg>

            </div>

        </a>

        @empty

        <div class="px-6 py-10 text-center">

            <p
                class="
                        text-sm

                        text-zinc-500
                        dark:text-zinc-400
                    ">
                Nenhum orçamento criado ainda.
            </p>

        </div>

        @endforelse

    </section>

</div>