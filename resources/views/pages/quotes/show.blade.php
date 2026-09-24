<?php

use App\Models\Quote;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Proposta | Fechou')]
    class extends Component {
    public int $quoteId;

    /*
    |--------------------------------------------------------------------------
    | Inicialização
    |--------------------------------------------------------------------------
    */

    public function mount(int $quote): void
    {
        $business = Auth::user()->business;

        abort_unless($business, 403);

        /*
         * Segurança multiempresa.
         *
         * O orçamento só pode ser acessado se pertencer
         * à empresa do usuário autenticado.
         */
        $quoteModel = $business
            ->quotes()
            ->whereKey($quote)
            ->firstOrFail();

        $this->quoteId = $quoteModel->id;
    }

    /*
    |--------------------------------------------------------------------------
    | Orçamento
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function quote(): Quote
    {
        return Auth::user()
            ->business
            ->quotes()
            ->with([
                'business',
                'client',

                'items' => fn($query) =>
                    $query->orderBy('sort_order'),

                'events' => fn($query) =>
                    $query->orderByDesc('created_at'),
            ])
            ->whereKey($this->quoteId)
            ->firstOrFail();
    }

    /*
    |--------------------------------------------------------------------------
    | Link público
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function publicUrl(): string
    {
        return route(
            'quotes.public',
            [
                'token' => $this->quote->public_token,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Permissão para compartilhar
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function canShareQuote(): bool
    {
        return Auth::user()?->hasVerifiedEmail() === true;
    }


    /*
    |--------------------------------------------------------------------------
    | WhatsApp
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function whatsappUrl(): string
    {
        $phone = preg_replace(
            '/\D+/',
            '',
            $this->quote->client->whatsapp
            ?: $this->quote->client->phone
            ?: ''
        );

        /*
         * Adiciona DDI do Brasil caso ainda não exista.
         */
        if (
            $phone
            && !str_starts_with($phone, '55')
        ) {
            $phone = '55' . $phone;
        }

        $number = str_pad(
            $this->quote->number,
            4,
            '0',
            STR_PAD_LEFT
        );

        $validity =
            $this->quote->valid_until
            ? $this->quote->valid_until->format('d/m/Y')
            : null;

        $total = number_format(
            (float) $this->quote->total,
            2,
            ',',
            '.'
        );

        $message =
            "Olá, {$this->quote->client->name}! 👋\n\n"
            . "Preparei a proposta #{$number} "
            . "da {$this->quote->business->name}.\n"
            . "{$this->quote->title}\n\n"
            . "Valor: R$ {$total}"
            . ($validity
                ? "\nVálida até: {$validity}"
                : '')
            . "\n\n"
            . "Você pode revisar e responder por aqui:\n"
            . $this->publicUrl;

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
    | Marcar como enviado
    |--------------------------------------------------------------------------
    */

    public function markAsSent(): void
    {
        /*
         * Compartilhamento externo exige e-mail confirmado.
         */
        abort_unless(Auth::user()?->hasVerifiedEmail(), 403);

        $business = Auth::user()->business;

        abort_unless($business, 403);

        $quote = $business
            ->quotes()
            ->whereKey($this->quoteId)
            ->firstOrFail();

        /*
         * Só fazemos:
         *
         * draft -> sent
         *
         * Copiar o link várias vezes não gera vários
         * eventos de envio.
         */
        if ($quote->status !== 'draft') {
            return;
        }

        DB::transaction(function () use ($quote) {

            $quote->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $quote->events()->create([
                'type' => 'sent',
            ]);
        });

        unset($this->quote);
    }

    /*
    |--------------------------------------------------------------------------
    | Pós-aceite
    |--------------------------------------------------------------------------
    */

    public function markAsPaid(): void
    {
        $business = Auth::user()->business;

        abort_unless($business, 403);

        DB::transaction(function () use ($business) {
            $quote = $business
                ->quotes()
                ->whereKey($this->quoteId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($quote->status !== 'accepted') {
                return;
            }

            if ($quote->payment_status === 'paid') {
                return;
            }

            $quote->update([
                'payment_status' => 'paid',
                'paid_at' => now(),
            ]);

            $quote->events()->create([
                'type' => 'payment_received',
            ]);
        });

        unset($this->quote);

        session()->flash(
            'success',
            'Pagamento marcado como recebido.'
        );
    }

    public function startExecution(): void
    {
        $business = Auth::user()->business;

        abort_unless($business, 403);

        DB::transaction(function () use ($business) {
            $quote = $business
                ->quotes()
                ->whereKey($this->quoteId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($quote->status !== 'accepted') {
                return;
            }

            if (
                !in_array(
                    $quote->execution_status,
                    [null, 'pending'],
                    true
                )
            ) {
                return;
            }

            $quote->update([
                'execution_status' => 'in_progress',
                'execution_started_at' => now(),
                'completed_at' => null,
            ]);

            $quote->events()->create([
                'type' => 'execution_started',
            ]);
        });

        unset($this->quote);

        session()->flash(
            'success',
            'Execução iniciada.'
        );
    }

    public function completeExecution(): void
    {
        $business = Auth::user()->business;

        abort_unless($business, 403);

        DB::transaction(function () use ($business) {
            $quote = $business
                ->quotes()
                ->whereKey($this->quoteId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($quote->status !== 'accepted') {
                return;
            }

            if ($quote->execution_status !== 'in_progress') {
                return;
            }

            $quote->update([
                'execution_status' => 'completed',
                'completed_at' => now(),
            ]);

            $quote->events()->create([
                'type' => 'execution_completed',
            ]);
        });

        unset($this->quote);

        session()->flash(
            'success',
            'Execução concluída.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Cobrança opcional
    |--------------------------------------------------------------------------
    */

    public function setPaymentCollection(
        bool $enabled
    ): void {

        $business =
            Auth::user()->business;

        abort_unless(
            $business,
            403
        );


        DB::transaction(
            function () use (
                $business,
                $enabled
            ) {

                $quote = $business
                    ->quotes()
                    ->whereKey(
                        $this->quoteId
                    )
                    ->lockForUpdate()
                    ->firstOrFail();


                abort_unless(
                    $quote->status
                    === 'accepted',
                    422
                );


                if (
                    $enabled
                    && ! $business
                        ->payment_collection_enabled
                ) {
                    abort(
                        422,
                        'A cobrança está desativada para a empresa.'
                    );
                }


                if (
                    (bool) $quote
                        ->payment_collection_enabled
                    === $enabled
                ) {
                    return;
                }


                $quote->update([
                    'payment_collection_enabled'
                        => $enabled,
                ]);
            }
        );


        unset(
            $this->quote
        );


        session()->flash(
            'success',
            $enabled
                ? 'Cobrança ativada para esta proposta.'
                : 'Cobrança desativada para esta proposta.'
        );
    }


    public function paymentReminderMessage(): string
    {
        $business =
            Auth::user()->business;

        $quote =
            $this->quote;


        if (
            ! $business
            || $quote->status !== 'accepted'
        ) {
            return '';
        }


        $clientName =
            trim(
                (string) (
                    $quote
                        ->client
                        ?->name
                    ?? ''
                )
            );

        if ($clientName === '') {
            $clientName = 'cliente';
        }


        $number =
            str_pad(
                (string) $quote->number,
                4,
                '0',
                STR_PAD_LEFT
            );


        $amount =
            'R$ '
            . number_format(
                (float) $quote->total,
                2,
                ',',
                '.'
            );


        $lines = [
            "Olá, {$clientName}!",
            '',
            "A proposta #{$number} foi aprovada no valor de {$amount}.",
        ];


        if (
            filled(
                $business->pix_key
            )
        ) {
            $lines[] = '';
            $lines[] =
                'Chave Pix: '
                . $business->pix_key;
        }


        if (
            filled(
                $business
                    ->payment_instructions
            )
        ) {
            $lines[] = '';
            $lines[] =
                trim(
                    $business
                        ->payment_instructions
                );
        }


        $lines[] = '';
        $lines[] =
            'Qualquer dúvida, estou à disposição.';


        return implode(
            "\n",
            $lines
        );
    }


    public function paymentReminderUrl(): ?string
    {
        $business =
            Auth::user()->business;

        $quote =
            $this->quote;


        if (
            ! $business
            || ! $business
                ->payment_collection_enabled
            || $quote->status
                !== 'accepted'
            || ! $quote
                ->payment_collection_enabled
            || $quote->payment_status
                === 'paid'
        ) {
            return null;
        }


        $phone =
            preg_replace(
                '/\D+/',
                '',
                (string) (
                    $quote
                        ->client
                        ?->whatsapp
                    ?? ''
                )
            );


        if (
            strlen($phone) === 10
            || strlen($phone) === 11
        ) {
            $phone =
                '55'
                . $phone;
        }


        if (
            strlen($phone) < 12
        ) {
            return null;
        }


        return
            'https://wa.me/'
            . $phone
            . '?text='
            . rawurlencode(
                $this
                    ->paymentReminderMessage()
            );
    }


    public function recordPaymentReminder(): void
    {
        $business =
            Auth::user()->business;

        abort_unless(
            $business,
            403
        );


        DB::transaction(
            function () use (
                $business
            ) {

                $quote = $business
                    ->quotes()
                    ->with('client')
                    ->whereKey(
                        $this->quoteId
                    )
                    ->lockForUpdate()
                    ->firstOrFail();


                abort_unless(
                    $quote->status
                    === 'accepted',
                    422
                );


                abort_unless(
                    $business
                        ->payment_collection_enabled
                    && $quote
                        ->payment_collection_enabled,
                    422
                );


                if (
                    $quote->payment_status
                    === 'paid'
                ) {
                    return;
                }


                $phone =
                    preg_replace(
                        '/\D+/',
                        '',
                        (string) (
                            $quote
                                ->client
                                ?->whatsapp
                            ?? ''
                        )
                    );


                abort_if(
                    $phone === '',
                    422
                );


                $quote
                    ->events()
                    ->create([
                        'type' =>
                            'payment_reminder_sent',

                        'metadata' => [
                            'channel' =>
                                'whatsapp',

                            'origin' =>
                                'quote_show',
                        ],
                    ]);
            }
        );


        unset(
            $this->quote
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    */

    public function statusLabel(string $status): string
    {
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

    public function statusClasses(string $status): string
    {
        return match ($status) {
            'draft' =>
                'bg-zinc-100 text-zinc-700
                 dark:bg-zinc-800 dark:text-zinc-200',

            'sent' =>
                'bg-blue-100 text-blue-700
                 dark:bg-blue-950/70 dark:text-blue-300',

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

    /*
    |--------------------------------------------------------------------------
    | Tipos dos itens
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
                'bg-blue-100 text-blue-700
                 dark:bg-blue-950/70 dark:text-blue-300',

            'material' =>
                'bg-violet-100 text-violet-700
                 dark:bg-violet-950/70 dark:text-violet-300',

            default =>
                'bg-zinc-100 text-zinc-700
                 dark:bg-zinc-800 dark:text-zinc-200',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Histórico
    |--------------------------------------------------------------------------
    */

    public function eventLabel(string $type): string
    {
        return match ($type) {
            'created' =>
                'Proposta criada',

            'updated' =>
                'Proposta editada',

            'sent' =>
                'Proposta enviada',

            'viewed' =>
                'Cliente visualizou',

            'accepted' =>
                'Cliente aceitou',

            'rejected' =>
                'Cliente recusou',

            'follow_up' => 'Follow-up realizado',

            'payment_received' =>
                'Pagamento recebido',

            'payment_reminder_sent' =>
                'Lembrete de pagamento enviado',

            'execution_started' =>
                'Execução iniciada',

            'execution_completed' =>
                'Execução concluída',

            default =>
                ucfirst($type),
        };
    }


    public function timelineEvents()
    {
        /*
         * Para acompanhamento operacional,
         * os acontecimentos mais recentes
         * ficam no topo.
         */
        return $this
            ->quote
            ->events()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }


    public function eventPhaseLabel(
        string $type
    ): string {
        return match ($type) {

            'sent',
            'follow_up' =>
                'Comercial',

            'viewed',
            'accepted',
            'rejected' =>
                'Cliente',

            'payment_received',
            'payment_reminder_sent' =>
                'Pagamento',

            'execution_started',
            'execution_completed' =>
                'Execução',

            default =>
                'Proposta',
        };
    }


    public function eventPhaseClasses(
        string $type
    ): string {
        return match ($type) {

            'sent',
            'follow_up' =>
                'bg-blue-50 text-blue-700 '
                . 'dark:bg-blue-950/50 '
                . 'dark:text-blue-300',

            'viewed' =>
                'bg-amber-50 text-amber-700 '
                . 'dark:bg-amber-950 '
                . 'dark:text-amber-300',

            'accepted' =>
                'bg-emerald-50 text-emerald-700 '
                . 'dark:bg-emerald-950/50 '
                . 'dark:text-emerald-300',

            'rejected' =>
                'bg-red-50 text-red-700 '
                . 'dark:bg-red-950/50 '
                . 'dark:text-red-300',

            'payment_received' =>
                'bg-emerald-50 text-emerald-700 '
                . 'dark:bg-emerald-950/50 '
                . 'dark:text-emerald-300',

            'payment_reminder_sent' =>
                'bg-amber-50 text-amber-700 '
                . 'dark:bg-amber-950/50 '
                . 'dark:text-amber-300',

            'execution_started' =>
                'bg-blue-50 text-blue-700 '
                . 'dark:bg-blue-950/50 '
                . 'dark:text-blue-300',

            'execution_completed' =>
                'bg-emerald-50 text-emerald-700 '
                . 'dark:bg-emerald-950/50 '
                . 'dark:text-emerald-300',

            default =>
                'bg-zinc-100 text-zinc-600 '
                . 'dark:bg-zinc-800 '
                . 'dark:text-zinc-300',
        };
    }


    public function formatCycleDuration(
        $from,
        $to
    ): string {
        if (! $from || ! $to) {
            return '—';
        }

        $seconds = max(
            0,
            (int) $from->diffInSeconds($to)
        );

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


    public function cycleSummary(): array
    {
        $events = $this
            ->quote
            ->events()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();


        $createdAt =
            $events
                ->firstWhere(
                    'type',
                    'created'
                )
                ?->created_at
            ?? $this->quote->created_at;


        $viewedAt =
            $events
                ->firstWhere(
                    'type',
                    'viewed'
                )
                ?->created_at;


        $acceptedAt =
            $events
                ->firstWhere(
                    'type',
                    'accepted'
                )
                ?->created_at
            ?? $this->quote->accepted_at;


        $paidAt =
            $events
                ->firstWhere(
                    'type',
                    'payment_received'
                )
                ?->created_at
            ?? $this->quote->paid_at;


        $completedAt =
            $events
                ->firstWhere(
                    'type',
                    'execution_completed'
                )
                ?->created_at
            ?? $this->quote->completed_at;


        return [
            [
                'key' =>
                    'viewed',

                'label' =>
                    'Até visualizar',

                'context' =>
                    'Criação → visualização',

                'value' =>
                    $viewedAt
                        ? $this->formatCycleDuration(
                            $createdAt,
                            $viewedAt
                        )
                        : '—',

                'completed' =>
                    (bool) $viewedAt,
            ],

            [
                'key' =>
                    'accepted',

                'label' =>
                    'Até aceitar',

                'context' =>
                    'Criação → aceite',

                'value' =>
                    $acceptedAt
                        ? $this->formatCycleDuration(
                            $createdAt,
                            $acceptedAt
                        )
                        : '—',

                'completed' =>
                    (bool) $acceptedAt,
            ],

            [
                'key' =>
                    'payment',

                'label' =>
                    'Até pagamento',

                'context' =>
                    'Aceite → pagamento',

                'value' =>
                    (
                        $acceptedAt
                        && $paidAt
                    )
                        ? $this->formatCycleDuration(
                            $acceptedAt,
                            $paidAt
                        )
                        : (
                            $acceptedAt
                                ? 'Pendente'
                                : '—'
                        ),

                'completed' =>
                    (bool) (
                        $acceptedAt
                        && $paidAt
                    ),
            ],

            [
                'key' =>
                    'completed',

                'label' =>
                    'Ciclo completo',

                'context' =>
                    'Criação → conclusão',

                'value' =>
                    $completedAt
                        ? $this->formatCycleDuration(
                            $createdAt,
                            $completedAt
                        )
                        : (
                            $acceptedAt
                                ? 'Em andamento'
                                : '—'
                        ),

                'completed' =>
                    (bool) $completedAt,
            ],
        ];
    }





    public function eventClasses(string $type): string
    {
        return match ($type) {
            'accepted' =>
                'bg-emerald-500',

            'rejected' =>
                'bg-red-500',

            'viewed' =>
                'bg-amber-500',

            'sent' =>
                'bg-blue-500',

            'updated' =>
                'bg-violet-500',

            'follow_up' =>
                'bg-cyan-500',
            'payment_received' =>
                'bg-emerald-500',

            'execution_started' =>
                'bg-blue-500',

            'execution_completed' =>
                'bg-emerald-600',

            default =>
                'bg-zinc-400 dark:bg-zinc-600',
        };
    }
};
?>

<div wire:poll.10s class="mx-auto w-full max-w-7xl space-y-6">

    {{-- ========================================================= --}}
    {{-- MENSAGEM --}}
    {{-- ========================================================= --}}

    {{-- ========================================================= --}}
    {{-- CABEÇALHO + AÇÕES --}}
    {{-- ========================================================= --}}

    <section class="
            overflow-visible
            rounded-2xl
            border border-zinc-200
            bg-white
            shadow-sm
            dark:border-zinc-800
            dark:bg-zinc-900
        ">
        <div class="px-5 py-5 sm:px-6">

            <a href="{{ route('quotes.index') }}" wire:navigate class="
                    inline-flex items-center gap-2
                    text-sm font-medium
                    text-zinc-500
                    transition
                    hover:text-zinc-950
                    dark:text-zinc-400
                    dark:hover:text-white
                ">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6" />
                </svg>

                Voltar para propostas
            </a>

            <div class="
                    mt-4
                    flex flex-col gap-5
                    lg:flex-row
                    lg:items-end
                    lg:justify-between
                ">
                <div class="min-w-0">

                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1 class="
                                text-2xl font-bold
                                tracking-tight
                                text-zinc-950
                                dark:text-white
                            ">
                            #{{ str_pad(
    $this->quote->number,
    4,
    '0',
    STR_PAD_LEFT
) }}
                        </h1>

                        <span class="
                                inline-flex
                                rounded-full
                                px-2.5 py-1
                                text-xs font-semibold
                                {{ $this->statusClasses(
    $this->quote->status
) }}
                            ">
                            {{ $this->statusLabel(
    $this->quote->status
) }}
                        </span>

                        @if ($this->quote->version > 1)
                            <span class="
                                                        inline-flex items-center gap-1
                                                        rounded-full
                                                        bg-violet-100
                                                        px-2.5 py-1
                                                        text-xs font-semibold
                                                        text-violet-700
                                                        dark:bg-violet-950
                                                        dark:text-violet-300
                                                    ">
                                Versão {{ $this->quote->version }}
                            </span>
                        @endif
                    </div>

                    <h2 class="
                            mt-2
                            text-lg font-semibold
                            text-zinc-900
                            dark:text-zinc-100
                        ">
                        {{ $this->quote->title }}
                    </h2>

                    <p class="
                            mt-1
                            text-sm
                            text-zinc-500
                            dark:text-zinc-400
                        ">
                        Criada em
                        {{ $this->quote->created_at->format(
    'd/m/Y \à\s H:i'
) }}
                    </p>
                </div>

                <div class="
                        min-w-44
                        rounded-xl
                        border border-emerald-200
                        bg-emerald-50
                        px-5 py-3
                        dark:border-emerald-900/60
                        dark:bg-emerald-950/20
                    ">
                    <p class="
                            text-xs font-semibold
                            uppercase tracking-wide
                            text-emerald-700
                            dark:text-emerald-400
                        ">
                        Total
                    </p>

                    <p class="
                            mt-1 whitespace-nowrap
                            text-2xl font-bold
                            tracking-tight
                            text-zinc-950
                            dark:text-zinc-100
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

        <div x-data="{ copied: false }" class="
                flex flex-col gap-3
                border-t border-zinc-200
                bg-zinc-50/60
                px-5 py-4
                sm:px-6
                lg:flex-row
                lg:items-center
                lg:justify-between
                dark:border-zinc-800
                dark:bg-zinc-950/30
            ">
            <div
                data-header-document-actions
                class="
                    flex
                    flex-wrap
                    items-center
                    gap-2
                "
            >

<details
                data-more-actions
                class="
                    group
                    relative
                    w-fit
                "
            >

                <summary
                    class="
                        inline-flex
                        cursor-pointer
                        list-none
                        items-center
                        justify-center
                        gap-2

                        rounded-lg

                        border border-zinc-300

                        bg-transparent

                        px-3.5 py-2.5

                        text-sm
                        font-semibold
                        text-zinc-600

                        transition

                        hover:bg-zinc-100
                        hover:text-zinc-950

                        dark:border-zinc-700
                        dark:text-zinc-300
                        dark:hover:bg-zinc-800
                        dark:hover:text-white
                    "
                >
                    Mais ações

                    <svg
                        class="
                            size-4
                            transition-transform
                            group-open:rotate-180
                        "
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="m6 9 6 6 6-6"
                        />
                    </svg>
                </summary>


                <div
                    class="
                        absolute
                        left-0
                        top-full
                        z-40

                        mt-2

                        flex
                        min-w-56
                        flex-col
                        gap-1

                        rounded-xl

                        border border-zinc-200

                        bg-white

                        p-1.5

                        shadow-lg
                        shadow-black/10

                        [&>a]:w-full
                        [&>a]:justify-start
                        [&>a]:border-transparent
                        [&>a]:bg-transparent
                        [&>a]:shadow-none

                        [&>button]:w-full
                        [&>button]:justify-start
                        [&>button]:border-transparent
                        [&>button]:bg-transparent
                        [&>button]:shadow-none

                        dark:border-zinc-700
                        dark:bg-zinc-900
                    "
                >


                @if ($this->quote->status === 'draft')
                                <a href="{{ route(
                        'quotes.edit',
                        $this->quote->id
                    ) }}" wire:navigate class="
                                                                                                            inline-flex items-center justify-center gap-2
                                                                                                            rounded-lg
                                                                                                            border border-zinc-300
                                                                                                            bg-white
                                                                                                            px-4 py-2.5
                                                                                                            text-sm font-semibold
                                                                                                            text-zinc-700
                                                                                                            shadow-sm
                                                                                                            transition
                                                                                                            hover:bg-zinc-100
                                                                                                            hover:text-zinc-950
                                                                                                            dark:border-zinc-700
                                                                                                            dark:bg-zinc-900
                                                                                                            dark:text-zinc-200
                                                                                                            dark:hover:bg-zinc-800
                                                                                                            dark:hover:text-white
                                                                                                        ">
                                    Editar proposta
                                </a>
                @endif

                <a
                    data-duplicate-quote
                    href="{{
                        route(
                            'quotes.create',
                            [
                                'duplicar' =>
                                    $this->quote->id,
                            ]
                        )
                    }}"
                    wire:navigate
                    class="
                        inline-flex
                        items-center
                        justify-center
                        gap-1.5

                        rounded-lg

                        border border-zinc-300
                        bg-transparent

                        px-3 py-2

                        text-xs font-semibold
                        text-zinc-600

                        transition

                        hover:bg-zinc-100
                        hover:text-zinc-950

                        dark:border-zinc-700
                        dark:text-zinc-300
                        dark:hover:bg-zinc-800
                        dark:hover:text-white
                    "
                >
                    Duplicar proposta
                </a>


                <a
                    data-save-as-template
                    href="{{
                        route(
                            'quote-templates.index',
                            [
                                'proposta' =>
                                    $this->quote->id,
                            ]
                        )
                    }}"
                    wire:navigate
                    class="
                        inline-flex
                        items-center
                        justify-center
                        gap-1.5

                        rounded-lg

                        border border-zinc-300
                        bg-transparent

                        px-3 py-2

                        text-xs font-semibold
                        text-zinc-600

                        transition

                        hover:bg-zinc-100
                        hover:text-zinc-950

                        dark:border-zinc-700
                        dark:text-zinc-300
                        dark:hover:bg-zinc-800
                        dark:hover:text-white
                    "
                >
                    Salvar como modelo
                </a>






                </div>

            </details>

<a href="{{ route(
    'quotes.pdf.preview',
    $this->quote->id
) }}" target="_blank" rel="noopener noreferrer" class="
                            inline-flex
                            items-center
                            justify-center
                            gap-1.5

                            rounded-lg

                            border border-zinc-300
                            bg-transparent

                            px-3.5 py-2.5

                            text-sm
                            font-semibold
                            text-zinc-600

                            transition

                            hover:bg-zinc-100
                            hover:text-zinc-950

                            dark:border-zinc-700
                            dark:text-zinc-300
                            dark:hover:bg-zinc-800
                            dark:hover:text-white
                        ">
                    Revisar PDF
                </a>

<a href="{{ route(
    'quotes.pdf',
    $this->quote->id
) }}" class="
                            inline-flex
                            items-center
                            justify-center
                            gap-1.5

                            rounded-lg

                            border border-zinc-300
                            bg-transparent

                            px-3.5 py-2.5

                            text-sm
                            font-semibold
                            text-zinc-600

                            transition

                            hover:bg-zinc-100
                            hover:text-zinc-950

                            dark:border-zinc-700
                            dark:text-zinc-300
                            dark:hover:bg-zinc-800
                            dark:hover:text-white
                        ">
                    Baixar PDF
                </a>

            </div>

            <div class="flex flex-wrap gap-2 lg:justify-end">

                @if ($this->canShareQuote)
                    <button type="button" wire:click="markAsSent" x-on:click="
                                                navigator.clipboard.writeText(
                                                    @js($this->publicUrl)
                                                );
                                                copied = true;
                                                setTimeout(
                                                    () => copied = false,
                                                    2000
                                                );
                                            " class="
                                                inline-flex items-center justify-center gap-2
                                                rounded-lg
                                                border border-emerald-300
                                                bg-white
                                                px-4 py-2.5
                                                text-sm font-semibold
                                                text-emerald-700
                                                shadow-sm
                                                transition
                                                hover:bg-emerald-50
                                                dark:border-emerald-800
                                                dark:bg-zinc-900
                                                dark:text-emerald-300
                                            ">
                        <span x-show="!copied">Copiar link</span>
                        <span x-show="copied" x-cloak>Copiado!</span>
                    </button>

                    <a href="{{ $this->whatsappUrl }}" target="_blank" rel="noopener noreferrer" wire:click="markAsSent"
                        class="
                                                inline-flex items-center justify-center gap-2
                                                rounded-lg
                                                bg-emerald-600
                                                px-4 py-2.5
                                                text-sm font-semibold
                                                text-white
                                                shadow-sm
                                                transition
                                                hover:bg-emerald-700
                                                dark:bg-emerald-500
                                                dark:text-zinc-950
                                            ">
                        Enviar por WhatsApp
                    </a>
                @else
                    <a href="{{ route('verification.notice') }}" wire:navigate class="
                                                inline-flex items-center justify-center gap-2
                                                rounded-lg
                                                border border-amber-400/70
                                                bg-amber-50
                                                px-4 py-2.5
                                                text-sm font-semibold
                                                text-amber-800
                                                shadow-sm
                                                transition
                                                hover:bg-amber-100
                                                dark:border-amber-800
                                                dark:bg-amber-950/30
                                                dark:text-amber-200
                                            ">
                        Confirmar e-mail para compartilhar
                    </a>
                @endif
            </div>
        </div>
    </section>


    {{-- ========================================================= --}}
    {{-- FEEDBACK PÓS-CRIAÇÃO --}}
    {{-- ========================================================= --}}

    @if (session('quote_created'))
        <section class="
                                    rounded-2xl
                                    border border-emerald-200
                                    bg-emerald-50/70
                                    px-5 py-4
                                    shadow-sm
                                    sm:px-6
                                    dark:border-emerald-900/60
                                    dark:bg-emerald-950/20
                                ">
            <div class="flex items-start gap-3">
                <div class="
                                            flex size-9 shrink-0
                                            items-center justify-center
                                            rounded-xl
                                            bg-emerald-100
                                            text-emerald-700
                                            dark:bg-emerald-500/10
                                            dark:text-emerald-400
                                        ">
                    ✓
                </div>

                <div>
                    <p class="
                                                text-sm font-semibold
                                                text-zinc-950
                                                dark:text-white
                                            ">
                        Proposta criada com sucesso
                    </p>

                    <p class="
                                                mt-1
                                                text-sm leading-6
                                                text-zinc-600
                                                dark:text-zinc-300
                                            ">
                        Ela foi salva como rascunho.
                        Revise o PDF e, quando estiver tudo certo,
                        compartilhe com o cliente.

                        @if (!$this->canShareQuote)
                            Para compartilhar, confirme seu e-mail
                            no aviso acima.
                        @endif
                    </p>
                </div>
            </div>
        </section>
    @elseif (session('success'))
        <div class="
                                    rounded-xl
                                    border border-emerald-200
                                    bg-emerald-50
                                    px-4 py-3
                                    text-sm font-medium
                                    text-emerald-800
                                    dark:border-emerald-900
                                    dark:bg-emerald-950/40
                                    dark:text-emerald-300
                                ">
            {{ session('success') }}
        </div>
    @endif


    {{-- ========================================================= --}}
    {{-- FEEDBACK DA RECUSA --}}
    {{-- ========================================================= --}}

    @if ($this->quote->status === 'rejected')
        @php
            $rejectionEvent = $this->quote
                ->events
                ->firstWhere(
                    'type',
                    'rejected'
                );

            $rejectionMetadata =
                $rejectionEvent?->metadata ?? [];

            $rejectionReason =
                $rejectionMetadata[
                    'rejection_reason_label'
                ] ?? null;

            $rejectionComment =
                $rejectionMetadata[
                    'rejection_comment'
                ] ?? null;
        @endphp

        <section class="
                                    rounded-2xl
                                    border border-red-200
                                    bg-red-50
                                    px-5 py-4
                                    shadow-sm

                                    dark:border-red-900/70
                                    dark:bg-red-950/20
                                ">
            <div class="
                                        flex flex-col gap-4
                                        sm:flex-row
                                        sm:items-start
                                        sm:justify-between
                                    ">
                <div class="min-w-0">

                    <div class="flex items-center gap-2.5">
                        <div class="
                                                    flex size-9 shrink-0
                                                    items-center justify-center
                                                    rounded-full
                                                    bg-red-100
                                                    text-red-700

                                                    dark:bg-red-950
                                                    dark:text-red-300
                                                ">
                            <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </div>

                        <div>
                            <h3 class="
                                                        font-semibold
                                                        text-red-900
                                                        dark:text-red-200
                                                    ">
                                Proposta recusada
                            </h3>

                            @if ($this->quote->rejected_at)
                                            <p class="
                                                                                                                                            mt-0.5
                                                                                                                                            text-xs
                                                                                                                                            text-red-700/80
                                                                                                                                            dark:text-red-400
                                                                                                                                        ">
                                                Em
                                                {{ $this->quote
                                ->rejected_at
                                ->format(
                                    'd/m/Y \à\s H:i'
                                ) }}
                                            </p>
                            @endif
                        </div>
                    </div>

                    @if (
                            $rejectionReason
                            || $rejectionComment
                        )
                        <div class="
                                                                        mt-4
                                                                        space-y-3
                                                                        pl-0
                                                                        sm:pl-11
                                                                    ">
                            @if ($rejectionReason)
                                <div>
                                    <p class="
                                                                                                        text-xs font-semibold
                                                                                                        uppercase
                                                                                                        tracking-wide
                                                                                                        text-red-700/70
                                                                                                        dark:text-red-400/80
                                                                                                    ">
                                        Motivo
                                    </p>

                                    <p class="
                                                                                                        mt-1
                                                                                                        text-sm font-medium
                                                                                                        text-red-950
                                                                                                        dark:text-red-100
                                                                                                    ">
                                        {{ $rejectionReason }}
                                    </p>
                                </div>
                            @endif

                            @if ($rejectionComment)
                                <div>
                                    <p class="
                                                                                                        text-xs font-semibold
                                                                                                        uppercase
                                                                                                        tracking-wide
                                                                                                        text-red-700/70
                                                                                                        dark:text-red-400/80
                                                                                                    ">
                                        Comentário do cliente
                                    </p>

                                    <p class="
                                                                                                        mt-1
                                                                                                        whitespace-pre-line
                                                                                                        [overflow-wrap:anywhere]
                                                                                                        text-sm leading-6
                                                                                                        text-red-900
                                                                                                        dark:text-red-200
                                                                                                    ">
                                        {{ $rejectionComment }}
                                    </p>
                                </div>
                            @endif
                        </div>
                    @else
                        <p class="
                                                                        mt-3
                                                                        text-sm
                                                                        text-red-800/80
                                                                        dark:text-red-300/80
                                                                        sm:pl-11
                                                                    ">
                            O cliente recusou a proposta
                            sem informar um motivo.
                        </p>
                    @endif
                </div>
            </div>
        </section>
    @endif
    {{-- ========================================================= --}}
    {{-- NEGÓCIO FECHADO / PÓS-ACEITE --}}
    {{-- ========================================================= --}}

    @if ($this->quote->status === 'accepted')

        <section
        x-data="{ confirmAction: null }"
        @keydown.escape.window="confirmAction = null" class="
                overflow-hidden
                rounded-2xl
                border border-emerald-200
                bg-white
                shadow-sm

                dark:border-emerald-900/70
                dark:bg-zinc-900
            ">

            {{-- CABEÇALHO --}}
            <div class="
                    flex flex-col gap-3
                    border-b border-emerald-100
                    bg-emerald-50/70
                    px-5 py-4

                    sm:flex-row
                    sm:items-center
                    sm:justify-between

                    dark:border-emerald-900/60
                    dark:bg-emerald-950/20
                ">

                <div class="flex items-center gap-3">

                    <div class="
                            flex size-10 shrink-0
                            items-center justify-center
                            rounded-full
                            bg-emerald-100
                            text-emerald-700

                            dark:bg-emerald-950
                            dark:text-emerald-300
                        ">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12l4 4L19 6" />
                        </svg>
                    </div>

                    <div>
                        <h2 class="
                                font-semibold
                                text-emerald-950
                                dark:text-emerald-100
                            ">
                            Negócio fechado
                        </h2>

                        <p class="
                                mt-0.5
                                text-sm
                                text-emerald-700
                                dark:text-emerald-400
                            ">
                            Proposta aprovada pelo cliente

                            @if ($this->quote->accepted_at)
                                em
                                {{ $this->quote->accepted_at->format('d/m/Y \à\s H:i') }}
                            @endif
                        </p>
                    </div>

                </div>

                <span class="
                        inline-flex w-fit
                        items-center gap-1.5
                        rounded-full
                        bg-emerald-100
                        px-3 py-1
                        text-xs font-semibold
                        text-emerald-700

                        dark:bg-emerald-950
                        dark:text-emerald-300
                    ">
                    Fechado
                </span>

            </div>


            {{-- ETAPAS --}}
            <div class="
                    grid
                    divide-y divide-zinc-200

                    md:grid-cols-2
                    md:divide-x
                    md:divide-y-0

                    dark:divide-zinc-800
                ">

                {{-- ================================================= --}}
                {{-- PAGAMENTO --}}
                {{-- ================================================= --}}

                <div class="p-5">

                    <div class="flex items-start justify-between gap-4">

                        <div>
                            <p class="
                                    text-xs font-semibold
                                    uppercase tracking-wide
                                    text-zinc-500
                                    dark:text-zinc-400
                                ">
                                Pagamento
                            </p>

                            @if ($this->quote->payment_status === 'paid')

                                <div class="mt-2 flex items-center gap-2">

                                    <div class="
                                                flex size-7 items-center justify-center
                                                rounded-full
                                                bg-emerald-100
                                                text-emerald-700

                                                dark:bg-emerald-950
                                                dark:text-emerald-300
                                            ">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2.3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12l4 4L19 6" />
                                        </svg>
                                    </div>

                                    <div>
                                        <p class="
                                                    text-sm font-semibold
                                                    text-zinc-950
                                                    dark:text-white
                                                ">
                                            Pago
                                        </p>

                                        @if ($this->quote->paid_at)
                                            <p class="
                                                            text-xs
                                                            text-zinc-500
                                                            dark:text-zinc-400
                                                        ">
                                                {{ $this->quote->paid_at->format('d/m/Y \à\s H:i') }}
                                            </p>
                                        @endif
                                    </div>

                                </div>

                            @else

                                <p class="
                                            mt-2
                                            text-sm font-semibold
                                            text-amber-700
                                            dark:text-amber-300
                                        ">
                                    Pagamento pendente
                                </p>

                                <p class="
                                            mt-1
                                            text-xs leading-5
                                            text-zinc-500
                                            dark:text-zinc-400
                                        ">
                                    Marque como recebido quando o pagamento
                                    for confirmado.
                                </p>

                            @endif
                        </div>

                    </div>


                    @if ($this->quote->payment_status !== 'paid')

                        <button
                        @click="confirmAction = 'payment'" type="button" wire:loading.attr="disabled"
                            wire:target="markAsPaid" class="
                                    mt-4
                                    inline-flex items-center justify-center gap-2
                                    rounded-lg
                                    bg-emerald-600
                                    px-4 py-2.5
                                    text-sm font-semibold
                                    text-white
                                    transition

                                    hover:bg-emerald-700

                                    disabled:cursor-not-allowed
                                    disabled:opacity-60

                                    dark:bg-emerald-500
                                    dark:text-zinc-950
                                    dark:hover:bg-emerald-400
                                ">
                            <span wire:loading.remove wire:target="markAsPaid">
                                Marcar como pago
                            </span>

                            <span wire:loading wire:target="markAsPaid">
                                Confirmando...
                            </span>
                        </button>

                    @endif

                </div>


                {{-- ================================================= --}}
                {{-- EXECUÇÃO --}}
                {{-- ================================================= --}}

                <div class="p-5">

                    <p class="
                            text-xs font-semibold
                            uppercase tracking-wide
                            text-zinc-500
                            dark:text-zinc-400
                        ">
                        Execução
                    </p>


                    @if (
                            $this->quote->execution_status === 'completed'
                        )

                        <div class="mt-2 flex items-center gap-2">

                            <div class="
                                        flex size-7 items-center justify-center
                                        rounded-full
                                        bg-emerald-100
                                        text-emerald-700

                                        dark:bg-emerald-950
                                        dark:text-emerald-300
                                    ">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 12l4 4L19 6" />
                                </svg>
                            </div>

                            <div>
                                <p class="
                                            text-sm font-semibold
                                            text-zinc-950
                                            dark:text-white
                                        ">
                                    Concluída
                                </p>

                                @if ($this->quote->completed_at)
                                    <p class="
                                                    text-xs
                                                    text-zinc-500
                                                    dark:text-zinc-400
                                                ">
                                        {{ $this->quote->completed_at->format('d/m/Y \à\s H:i') }}
                                    </p>
                                @endif
                            </div>

                        </div>

                    @elseif (
                            $this->quote->execution_status === 'in_progress'
                        )

                        <div class="mt-2">

                            <div class="flex items-center gap-2">
                                <span class="
                                            size-2.5
                                            rounded-full
                                            bg-blue-500
                                        "></span>

                                <p class="
                                            text-sm font-semibold
                                            text-blue-700
                                            dark:text-blue-300
                                        ">
                                    Em andamento
                                </p>
                            </div>

                            @if ($this->quote->execution_started_at)
                                <p class="
                                                mt-1
                                                text-xs
                                                text-zinc-500
                                                dark:text-zinc-400
                                            ">
                                    Iniciada em
                                    {{ $this->quote->execution_started_at->format('d/m/Y \à\s H:i') }}
                                </p>
                            @endif

                        </div>

                    @else

                        <p class="
                                    mt-2
                                    text-sm font-semibold
                                    text-zinc-700
                                    dark:text-zinc-200
                                ">
                            Aguardando início
                        </p>

                        <p class="
                                    mt-1
                                    text-xs leading-5
                                    text-zinc-500
                                    dark:text-zinc-400
                                ">
                            Inicie quando o serviço ou pedido começar
                            a ser executado.
                        </p>

                    @endif


                    {{-- AÇÃO DA EXECUÇÃO --}}

                    @if (
                            !in_array(
                                $this->quote->execution_status,
                                ['in_progress', 'completed'],
                                true
                            )
                        )

                        <button
                        @click="confirmAction = 'start'" type="button"
                            wire:loading.attr="disabled" wire:target="startExecution" class="
                                    mt-4
                                    inline-flex items-center justify-center
                                    rounded-lg
                                    border border-blue-200
                                    bg-blue-50
                                    px-4 py-2.5
                                    text-sm font-semibold
                                    text-blue-700
                                    transition

                                    hover:bg-blue-100

                                    disabled:cursor-not-allowed
                                    disabled:opacity-60

                                    dark:border-blue-900
                                    dark:bg-blue-950/40
                                    dark:text-blue-300
                                    dark:hover:bg-blue-950/70
                                ">
                            <span wire:loading.remove wire:target="startExecution">
                                Iniciar execução
                            </span>

                            <span wire:loading wire:target="startExecution">
                                Iniciando...
                            </span>
                        </button>


                    @elseif (
                            $this->quote->execution_status === 'in_progress'
                        )

                        <button
                        @click="confirmAction = 'complete'" type="button" wire:loading.attr="disabled"
                            wire:target="completeExecution" class="
                                    mt-4
                                    inline-flex items-center justify-center
                                    rounded-lg
                                    bg-zinc-900
                                    px-4 py-2.5
                                    text-sm font-semibold
                                    text-white
                                    transition

                                    hover:bg-zinc-800

                                    disabled:cursor-not-allowed
                                    disabled:opacity-60

                                    dark:bg-white
                                    dark:text-zinc-950
                                    dark:hover:bg-zinc-200
                                ">
                            <span wire:loading.remove wire:target="completeExecution">
                                Concluir execução
                            </span>

                            <span wire:loading wire:target="completeExecution">
                                Concluindo...
                            </span>
                        </button>

                    @endif

                </div>

            </div>


        {{-- ===================================================== --}}

        {{-- ===================================================== --}}
        {{-- COBRANÇA OPCIONAL --}}
        {{-- ===================================================== --}}

        @if (
            $this->quote->status
            === 'accepted'
        )

            <section
                id="cobranca"
                data-payment-collection

                class="
                    mb-6

                    rounded-2xl

                    border
                    border-zinc-200

                    bg-white

                    p-5

                    shadow-sm

                    dark:border-zinc-800
                    dark:bg-zinc-900
                "
            >

                <div
                    class="
                        flex
                        flex-col
                        gap-4

                        sm:flex-row
                        sm:items-start
                        sm:justify-between
                    "
                >

                    <div>

                        <div
                            class="
                                flex
                                flex-wrap
                                items-center
                                gap-2
                            "
                        >

                            <h3
                                class="
                                    font-semibold

                                    text-zinc-950
                                    dark:text-white
                                "
                            >
                                Cobrança
                            </h3>


                            <span
                                class="
                                    rounded-full

                                    bg-zinc-100

                                    px-2
                                    py-0.5

                                    text-[10px]
                                    font-bold

                                    text-zinc-500

                                    dark:bg-zinc-800
                                    dark:text-zinc-400
                                "
                            >
                                OPCIONAL
                            </span>

                        </div>


                        <p
                            class="
                                mt-1

                                text-sm
                                leading-6

                                text-zinc-500
                                dark:text-zinc-400
                            "
                        >
                            Use o Fechou para compartilhar
                            dados de pagamento e lembrar o
                            cliente pelo WhatsApp.
                        </p>

                    </div>


                    <a
                        href="{{
                            route(
                                'settings.payment'
                            )
                        }}"

                        wire:navigate

                        class="
                            shrink-0

                            text-sm
                            font-semibold

                            text-emerald-600

                            hover:text-emerald-700

                            dark:text-emerald-400
                            dark:hover:text-emerald-300
                        "
                    >
                        Configurar cobrança
                    </a>

                </div>


                @if (
                    ! Auth::user()
                        ->business
                        ?->payment_collection_enabled
                )

                    <div
                        class="
                            mt-5

                            rounded-xl

                            border
                            border-dashed
                            border-zinc-300

                            bg-zinc-50

                            p-4

                            dark:border-zinc-700
                            dark:bg-zinc-950/40
                        "
                    >

                        <p
                            class="
                                text-sm
                                font-semibold

                                text-zinc-700
                                dark:text-zinc-200
                            "
                        >
                            Cobrança desativada para sua empresa
                        </p>


                        <p
                            class="
                                mt-1

                                text-xs
                                leading-5

                                text-zinc-500
                                dark:text-zinc-400
                            "
                        >
                            Nada muda no seu fluxo atual.
                            Ative o recurso somente se quiser
                            enviar Pix, instruções e lembretes
                            pelo Fechou.
                        </p>

                    </div>


                @elseif (
                    ! $this->quote
                        ->payment_collection_enabled
                )

                    <div
                        class="
                            mt-5

                            flex
                            flex-col
                            gap-3

                            rounded-xl

                            border
                            border-zinc-200

                            bg-zinc-50

                            p-4

                            sm:flex-row
                            sm:items-center
                            sm:justify-between

                            dark:border-zinc-800
                            dark:bg-zinc-950/40
                        "
                    >

                        <div>

                            <p
                                class="
                                    text-sm
                                    font-semibold

                                    text-zinc-700
                                    dark:text-zinc-200
                                "
                            >
                                Não usar cobrança nesta proposta
                            </p>


                            <p
                                class="
                                    mt-1

                                    text-xs

                                    text-zinc-500
                                    dark:text-zinc-400
                                "
                            >
                                O recurso está disponível,
                                mas continua opcional por negócio.
                            </p>

                        </div>


                        <button
                            type="button"

                            wire:click="
                                setPaymentCollection(true)
                            "

                            class="
                                inline-flex
                                shrink-0
                                items-center
                                justify-center

                                rounded-lg

                                bg-emerald-600

                                px-3.5
                                py-2

                                text-sm
                                font-semibold
                                text-white

                                transition

                                hover:bg-emerald-700

                                dark:bg-emerald-500
                                dark:text-zinc-950
                                dark:hover:bg-emerald-400
                            "
                        >
                            Ativar nesta proposta
                        </button>

                    </div>


                @else

                    <div
                        class="
                            mt-5

                            grid
                            gap-4

                            lg:grid-cols-2
                        "
                    >

                        <div
                            class="
                                rounded-xl

                                border
                                border-zinc-200

                                p-4

                                dark:border-zinc-800
                            "
                        >

                            <p
                                class="
                                    text-xs
                                    font-semibold
                                    uppercase
                                    tracking-wide

                                    text-zinc-400
                                "
                            >
                                Valor da proposta
                            </p>


                            <p
                                class="
                                    mt-1

                                    text-2xl
                                    font-bold

                                    text-zinc-950
                                    dark:text-white
                                "
                            >
                                R$
                                {{
                                    number_format(
                                        $this->quote->total,
                                        2,
                                        ',',
                                        '.'
                                    )
                                }}
                            </p>


                            <div
                                class="
                                    mt-4

                                    flex
                                    flex-wrap
                                    gap-2
                                "
                            >

                                @if (
                                    $this->quote
                                        ->payment_status
                                    === 'paid'
                                )

                                    <span
                                        class="
                                            rounded-full

                                            bg-emerald-100

                                            px-2.5
                                            py-1

                                            text-xs
                                            font-semibold

                                            text-emerald-700

                                            dark:bg-emerald-950/50
                                            dark:text-emerald-300
                                        "
                                    >
                                        Pagamento recebido
                                    </span>

                                @else

                                    <span
                                        class="
                                            rounded-full

                                            bg-amber-100

                                            px-2.5
                                            py-1

                                            text-xs
                                            font-semibold

                                            text-amber-700

                                            dark:bg-amber-950/50
                                            dark:text-amber-300
                                        "
                                    >
                                        Pagamento pendente
                                    </span>

                                @endif

                            </div>

                        </div>


                        <div
                            class="
                                rounded-xl

                                border
                                border-zinc-200

                                p-4

                                dark:border-zinc-800
                            "
                        >

                            <p
                                class="
                                    text-xs
                                    font-semibold
                                    uppercase
                                    tracking-wide

                                    text-zinc-400
                                "
                            >
                                Dados para pagamento
                            </p>


                            @if (
                                filled(
                                    Auth::user()
                                        ->business
                                        ?->pix_key
                                )
                            )

                                <div
                                    class="
                                        mt-3

                                        rounded-lg

                                        bg-zinc-50

                                        p-3

                                        dark:bg-zinc-950/60
                                    "
                                >

                                    <p
                                        class="
                                            text-[10px]
                                            font-semibold
                                            uppercase
                                            tracking-wide

                                            text-zinc-400
                                        "
                                    >
                                        Chave Pix
                                    </p>


                                    <div
                                        class="
                                            mt-1

                                            flex
                                            items-center
                                            justify-between
                                            gap-3
                                        "
                                    >

                                        <p
                                            class="
                                                min-w-0
                                                break-all

                                                text-sm
                                                font-medium

                                                text-zinc-800
                                                dark:text-zinc-200
                                            "
                                        >
                                            {{
                                                Auth::user()
                                                    ->business
                                                    ->pix_key
                                            }}
                                        </p>


                                        <button
                                            type="button"

                                            x-data="{
                                                copied: false
                                            }"

                                            data-pix="{{
                                                Auth::user()
                                                    ->business
                                                    ->pix_key
                                            }}"

                                            x-on:click="
                                                navigator.clipboard
                                                    .writeText(
                                                        $el.dataset.pix
                                                    );

                                                copied = true;

                                                setTimeout(
                                                    () => {
                                                        copied = false
                                                    },
                                                    2000
                                                );
                                            "

                                            class="
                                                shrink-0

                                                text-xs
                                                font-semibold

                                                text-emerald-600

                                                hover:text-emerald-700

                                                dark:text-emerald-400
                                            "
                                        >
                                            <span
                                                x-show="!copied"
                                            >
                                                Copiar
                                            </span>

                                            <span
                                                x-cloak
                                                x-show="copied"
                                            >
                                                Copiado
                                            </span>
                                        </button>

                                    </div>

                                </div>

                            @endif


                            @if (
                                filled(
                                    Auth::user()
                                        ->business
                                        ?->payment_instructions
                                )
                            )

                                <p
                                    class="
                                        mt-3

                                        whitespace-pre-line

                                        text-sm
                                        leading-6

                                        text-zinc-600
                                        dark:text-zinc-300
                                    "
                                >
                                    {{
                                        Auth::user()
                                            ->business
                                            ->payment_instructions
                                    }}
                                </p>

                            @endif

                        </div>

                    </div>


                    <div
                        class="
                            mt-4

                            flex
                            flex-wrap
                            items-center
                            gap-2
                        "
                    >

                        @if (
                            $this->quote
                                ->payment_status
                            !== 'paid'
                            && $this
                                ->paymentReminderUrl()
                        )

                            <a
                                href="{{
                                    $this
                                        ->paymentReminderUrl()
                                }}"

                                target="_blank"
                                rel="noopener noreferrer"

                                wire:click="
                                    recordPaymentReminder
                                "

                                class="
                                    inline-flex
                                    items-center
                                    justify-center

                                    rounded-lg

                                    bg-emerald-600

                                    px-3.5
                                    py-2

                                    text-sm
                                    font-semibold
                                    text-white

                                    transition

                                    hover:bg-emerald-700

                                    dark:bg-emerald-500
                                    dark:text-zinc-950
                                    dark:hover:bg-emerald-400
                                "
                            >
                                Enviar lembrete no WhatsApp
                            </a>


                        @elseif (
                            $this->quote
                                ->payment_status
                            !== 'paid'
                        )

                            <span
                                class="
                                    text-xs

                                    text-zinc-500
                                    dark:text-zinc-400
                                "
                            >
                                Cadastre o WhatsApp do cliente
                                para enviar lembretes.
                            </span>

                        @endif


                        <button
                            type="button"

                            wire:click="
                                setPaymentCollection(false)
                            "

                            class="
                                inline-flex
                                items-center
                                justify-center

                                rounded-lg

                                border
                                border-zinc-300

                                bg-white

                                px-3.5
                                py-2

                                text-sm
                                font-semibold

                                text-zinc-600

                                transition

                                hover:bg-zinc-100

                                dark:border-zinc-700
                                dark:bg-zinc-900
                                dark:text-zinc-300
                                dark:hover:bg-zinc-800
                            "
                        >
                            Desativar nesta proposta
                        </button>

                    </div>

                @endif

            </section>

        @endif


        {{-- MODAL DE CONFIRMAÇÃO DO PÓS-ACEITE --}}
        {{-- ===================================================== --}}

        <div
            x-cloak
            x-show="confirmAction !== null"
            x-transition.opacity
            class="
                fixed inset-0 z-[100]
                flex items-center justify-center
                p-4
            "
            role="dialog"
            aria-modal="true"
        >

            {{-- BACKDROP --}}
            <div
                class="
                    absolute inset-0
                    bg-black/65
                    backdrop-blur-sm
                "
                @click="confirmAction = null"
            ></div>


            {{-- JANELA --}}
            <div
                x-show="confirmAction !== null"

                x-transition:enter="
                    transition ease-out duration-200
                "
                x-transition:enter-start="
                    opacity-0 scale-95 translate-y-2
                "
                x-transition:enter-end="
                    opacity-100 scale-100 translate-y-0
                "

                x-transition:leave="
                    transition ease-in duration-150
                "
                x-transition:leave-start="
                    opacity-100 scale-100
                "
                x-transition:leave-end="
                    opacity-0 scale-95
                "

                @click.stop

                class="
                    relative z-10
                    w-full max-w-md
                    overflow-hidden
                    rounded-2xl
                    border border-zinc-200
                    bg-white
                    shadow-2xl

                    dark:border-zinc-800
                    dark:bg-zinc-900
                "
            >

                <div class="p-6">

                    {{-- ÍCONE --}}
                    <div
                        class="
                            flex size-11
                            items-center justify-center
                            rounded-full
                        "

                        :class="
                            confirmAction === 'payment'
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                                : confirmAction === 'start'
                                    ? 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300'
                                    : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200'
                        "
                    >

                        <svg
                            x-show="
                                confirmAction === 'payment'
                                || confirmAction === 'complete'
                            "
                            class="size-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.3"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M5 12l4 4L19 6"
                            />
                        </svg>


                        <svg
                            x-show="confirmAction === 'start'"
                            class="size-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M8 5v14l11-7z"
                            />
                        </svg>

                    </div>


                    {{-- TÍTULO --}}
                    <h3
                        class="
                            mt-4
                            text-lg font-semibold
                            text-zinc-950
                            dark:text-white
                        "
                        x-text="
                            confirmAction === 'payment'
                                ? 'Confirmar pagamento?'
                                : confirmAction === 'start'
                                    ? 'Iniciar execução?'
                                    : 'Concluir execução?'
                        "
                    ></h3>


                    {{-- TEXTO --}}
                    <div
                        class="
                            mt-2
                            text-sm leading-6
                            text-zinc-500
                            dark:text-zinc-400
                        "
                    >

                        <p x-show="confirmAction === 'payment'">
                            Confirme somente se o pagamento desta
                            proposta já foi recebido.
                        </p>

                        <p x-show="confirmAction === 'start'">
                            A proposta será marcada como em execução
                            e a data de início será registrada.
                        </p>

                        <p x-show="confirmAction === 'complete'">
                            A execução será marcada como concluída
                            e essa informação ficará registrada no
                            histórico da proposta.
                        </p>

                    </div>

                </div>


                {{-- RODAPÉ --}}
                <div
                    class="
                        flex flex-col-reverse gap-2

                        border-t border-zinc-200
                        bg-zinc-50
                        px-6 py-4

                        sm:flex-row
                        sm:justify-end

                        dark:border-zinc-800
                        dark:bg-zinc-950/40
                    "
                >

                    <button
                        type="button"
                        @click="confirmAction = null"

                        class="
                            inline-flex
                            items-center justify-center

                            rounded-lg
                            border border-zinc-300

                            bg-white
                            px-4 py-2.5

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
                        Cancelar
                    </button>


                    <button
                        type="button"

                        @click="
                            if (confirmAction === 'payment') {
                                $wire.markAsPaid();
                            } else if (confirmAction === 'start') {
                                $wire.startExecution();
                            } else if (confirmAction === 'complete') {
                                $wire.completeExecution();
                            }

                            confirmAction = null;
                        "

                        class="
                            inline-flex
                            items-center justify-center

                            rounded-lg
                            bg-emerald-600

                            px-4 py-2.5

                            text-sm font-semibold
                            text-white

                            transition
                            hover:bg-emerald-700

                            dark:bg-emerald-500
                            dark:text-zinc-950
                            dark:hover:bg-emerald-400
                        "
                    >

                        <span
                            x-text="
                                confirmAction === 'payment'
                                    ? 'Confirmar pagamento'
                                    : confirmAction === 'start'
                                        ? 'Iniciar execução'
                                        : 'Concluir execução'
                            "
                        ></span>

                    </button>

                </div>

            </div>

        </div>

</section>

    @endif

    {{-- ========================================================= --}}
    {{-- GRID PRINCIPAL --}}
    {{-- ========================================================= --}}

    <div class="
            grid
            items-start
            gap-6

            xl:grid-cols-[minmax(0,1fr)_330px]
        ">

        {{-- ====================================================== --}}
        {{-- CONTEÚDO --}}
        {{-- ====================================================== --}}

        <div class="space-y-6">


            {{-- ================================================== --}}
            {{-- CLIENTE --}}
            {{-- ================================================== --}}

            <section class="
                    rounded-2xl

                    border border-zinc-200

                    bg-white

                    shadow-sm

                    dark:border-zinc-800
                    dark:bg-zinc-900
                ">

                <div class="
                        border-b border-zinc-200

                        px-6 py-4

                        dark:border-zinc-800
                    ">
                    <h3 class="font-semibold text-zinc-950 dark:text-white">
                        Cliente
                    </h3>
                </div>


                <div class="p-6">

                    <div class="flex items-start gap-4">

                        {{-- AVATAR --}}

                        <div class="
                                flex size-11
                                shrink-0
                                items-center
                                justify-center

                                rounded-full

                                bg-emerald-100

                                font-bold
                                text-emerald-700

                                dark:bg-emerald-950
                                dark:text-emerald-300
                            ">
                            {{ mb_strtoupper(
    mb_substr(
        $this->quote->client->name,
        0,
        1
    )
) }}
                        </div>


                        <div class="min-w-0">

                            <p class="
                                    font-semibold

                                    text-zinc-950
                                    dark:text-white
                                ">
                                {{ $this->quote->client->name }}
                            </p>


                            <div class="
                                    mt-2
                                    space-y-1

                                    text-sm

                                    text-zinc-500
                                    dark:text-zinc-400
                                ">

                                @if ($this->quote->client->document)

                                    <p>
                                        CPF/CNPJ:

                                        <span class="
                                                                    text-zinc-700
                                                                    dark:text-zinc-300
                                                                ">
                                            {{ \App\Support\BrazilianInput::formatDocument(
                                                $this->quote->client->document
                                            ) }}
                                        </span>
                                    </p>

                                @endif


                                @if ($this->quote->client->whatsapp)

                                    <p>
                                        WhatsApp:

                                        <span class="
                                                                    text-zinc-700
                                                                    dark:text-zinc-300
                                                                ">
                                            {{ \App\Support\BrazilianInput::formatPhone(
                                                $this->quote->client->whatsapp
                                            ) }}
                                        </span>
                                    </p>

                                @elseif ($this->quote->client->phone)

                                    <p>
                                        Telefone:

                                        <span class="
                                                                    text-zinc-700
                                                                    dark:text-zinc-300
                                                                ">
                                            {{ \App\Support\BrazilianInput::formatPhone(
                                                $this->quote->client->phone
                                            ) }}
                                        </span>
                                    </p>

                                @endif


                                @if ($this->quote->client->email)

                                    <p class="break-all">
                                        {{ $this->quote->client->email }}
                                    </p>

                                @endif

                            </div>

                        </div>

                    </div>

                </div>

            </section>


            {{-- ================================================== --}}
            {{-- DESCRIÇÃO --}}
            {{-- ================================================== --}}

            @if ($this->quote->description)

                <section class="
                                            rounded-2xl

                                            border border-zinc-200

                                            bg-white

                                            p-6

                                            shadow-sm

                                            dark:border-zinc-800
                                            dark:bg-zinc-900
                                        ">

                    <h3 class="font-semibold text-zinc-950 dark:text-white">
                        Descrição
                    </h3>


                    <p class="
                                                mt-3

                                                whitespace-pre-line

                                                text-sm
                                                leading-6

                                                text-zinc-600
                                                dark:text-zinc-300

                                                [overflow-wrap:anywhere] break-words max-w-full">
                        {{ $this->quote->description }}
                    </p>

                </section>

            @endif


            {{-- ================================================== --}}
            {{-- ITENS --}}
            {{-- ================================================== --}}

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
                        flex
                        items-center
                        justify-between

                        border-b
                        border-zinc-200

                        px-6 py-4

                        dark:border-zinc-800
                    ">

                    <div>

                        <h3 class="font-semibold text-zinc-950 dark:text-white">
                            Itens da proposta
                        </h3>

                        <p class="
                                mt-0.5

                                text-sm

                                text-zinc-500
                                dark:text-zinc-400
                            ">
                            {{ $this->quote->items->count() }}

                            {{ $this->quote->items->count() === 1
    ? 'item'
    : 'itens'
                            }}
                        </p>

                    </div>

                </div>


                <div class="
                        divide-y
                        divide-zinc-100

                        dark:divide-zinc-800
                    ">

                    @foreach ($this->quote->items as $item)

                                        <div wire:key="quote-item-{{ $item->id }}" class="
                                                                                                                                    px-6 py-4

                                                                                                                                    transition

                                                                                                                                    hover:bg-zinc-50

                                                                                                                                    dark:hover:bg-zinc-800/30
                                                                                                                                ">

                                            <div class="
                                                                                                                                        flex flex-col
                                                                                                                                        gap-4

                                                                                                                                        sm:flex-row
                                                                                                                                        sm:items-center
                                                                                                                                        sm:justify-between
                                                                                                                                    ">

                                                <div class="min-w-0">

                                                    <div class="min-w-0 flex flex-wrap items-center gap-2">

                                                        <p
                                                            class="
                                                                                                                                                    font-semibold

                                                                                                                                                    text-zinc-900
                                                                                                                                                    dark:text-white

                                                                                                                                [overflow-wrap:anywhere] break-words max-w-full">
                                                            {{ $item->description }}
                                                        </p>


                                                        <span
                                                            class="
                                                                                                                                                    inline-flex

                                                                                                                                                    rounded-full

                                                                                                                                                    px-2 py-0.5

                                                                                                                                                    text-[11px]
                                                                                                                                                    font-semibold

                                                                                                                                                    {{ $this->typeClasses(
                            $item->type
                        ) }}
                                                                                                                                                ">
                                                            {{ $this->typeLabel(
                            $item->type
                        ) }}
                                                        </span>

                                                    </div>


                                                    <p
                                                        class="
                                                                                                                                                mt-1

                                                                                                                                                text-sm

                                                                                                                                                text-zinc-500
                                                                                                                                                dark:text-zinc-400
                                                                                                                                            ">
                                                        {{ rtrim(
                            rtrim(
                                number_format(
                                    (float) $item->quantity,
                                    3,
                                    ',',
                                    '.'
                                ),
                                '0'
                            ),
                            ','
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


                                                <div class="shrink-0 sm:text-right">

                                                    <p
                                                        class="
                                                                                                                                                text-xs

                                                                                                                                                text-zinc-400
                                                                                                                                                dark:text-zinc-500
                                                                                                                                            ">
                                                        Total
                                                    </p>


                                                    <p
                                                        class="
                                                                                                                                                mt-0.5

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


            {{-- ================================================== --}}
            {{-- CONDIÇÕES --}}
            {{-- ================================================== --}}

            @if ($this->quote->notes)

                <section class="
                                            rounded-2xl

                                            border border-zinc-200

                                            bg-white

                                            p-6

                                            shadow-sm

                                            dark:border-zinc-800
                                            dark:bg-zinc-900
                                        ">

                    <h3 class="font-semibold text-zinc-950 dark:text-white">
                        Condições e observações
                    </h3>


                    <p class="
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

        </div>


        {{-- ====================================================== --}}
        {{-- LATERAL --}}
        {{-- ====================================================== --}}

        <aside class="space-y-6 xl:sticky xl:top-6">


            {{-- ================================================== --}}
            {{-- RESUMO --}}
            {{-- ================================================== --}}

            <div class="
                    rounded-2xl

                    border border-zinc-200

                    bg-white

                    p-5

                    shadow-sm

                    dark:border-zinc-800
                    dark:bg-zinc-900
                ">

                <h3 class="font-semibold text-zinc-950 dark:text-white">
                    Resumo
                </h3>


                <div class="mt-5 space-y-3">

                    {{-- SUBTOTAL --}}

                    <div class="flex justify-between gap-4 text-sm">

                        <span class="text-zinc-500 dark:text-zinc-400">
                            Subtotal
                        </span>

                        <span class="
                                font-medium

                                text-zinc-900
                                dark:text-zinc-200
                            ">
                            R$ {{ number_format(
    (float) $this->quote->subtotal,
    2,
    ',',
    '.'
) }}
                        </span>

                    </div>


                    {{-- DESCONTO --}}

                    @if ((float) $this->quote->discount > 0)

                                        <div class="flex justify-between gap-4 text-sm">

                                            <span class="text-zinc-500 dark:text-zinc-400">
                                                Desconto
                                            </span>


                                            <span class="
                                                                                                                                        font-medium

                                                                                                                                        text-red-600
                                                                                                                                        dark:text-red-400
                                                                                                                                    ">
                                                - R$ {{ number_format(
                            (float) $this->quote->discount,
                            2,
                            ',',
                            '.'
                        ) }}
                                            </span>

                                        </div>

                    @endif


                    {{-- TOTAL --}}

                    <div class="
                            border-t
                            border-zinc-200

                            pt-4

                            dark:border-zinc-800
                        ">

                        <p class="text-sm text-zinc-500 dark:text-zinc-400">
                            Total
                        </p>

                        <p class="
                                mt-1

                                text-2xl
                                font-bold
                                tracking-tight

                                text-zinc-950
                                dark:text-zinc-100
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


                {{-- VALIDADE --}}

                <div class="
                        mt-5

                        border-t
                        border-zinc-200

                        pt-4

                        dark:border-zinc-800
                    ">

                    <div class="flex justify-between gap-4 text-sm">

                        <span class="text-zinc-500 dark:text-zinc-400">
                            Validade
                        </span>

                        <span class="
                                font-medium

                                text-zinc-700
                                dark:text-zinc-300
                            ">
                            {{ $this->quote
    ->valid_until
        ?->format('d/m/Y')
    ?? 'Sem validade'
                            }}
                        </span>

                    </div>

                </div>


                {{-- VERSÃO --}}

                <div class="
                        mt-4

                        flex
                        items-center
                        justify-between

                        border-t
                        border-zinc-200

                        pt-4

                        dark:border-zinc-800
                    ">

                    <span class="text-sm text-zinc-500 dark:text-zinc-400">
                        Versão
                    </span>

                    <span class="
                            font-semibold

                            text-zinc-700
                            dark:text-zinc-300
                        ">
                        {{ $this->quote->version }}
                    </span>

                </div>


                {{-- STATUS --}}

                <div class="
                        mt-4

                        flex
                        items-center
                        justify-between

                        border-t
                        border-zinc-200

                        pt-4

                        dark:border-zinc-800
                    ">

                    <span class="text-sm text-zinc-500 dark:text-zinc-400">
                        Status
                    </span>


                    <span class="
                            inline-flex
                            rounded-full

                            px-2.5 py-1

                            text-xs
                            font-semibold

                            {{ $this->statusClasses(
    $this->quote->status
) }}
                        ">
                        {{ $this->statusLabel(
    $this->quote->status
) }}
                    </span>

                </div>

            </div>


            {{-- ================================================== --}}
            {{-- LINHA DO TEMPO DO NEGÓCIO --}}
            {{-- ================================================== --}}

            <div
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
                        items-start
                        gap-3

                        border-b
                        border-zinc-100

                        px-5 py-4

                        dark:border-zinc-800
                    "
                >

                    <div
                        class="
                            flex
                            size-9
                            shrink-0
                            items-center
                            justify-center

                            rounded-xl

                            bg-zinc-100

                            text-zinc-500

                            dark:bg-zinc-800
                            dark:text-zinc-300
                        "
                    >
                        <svg
                            class="size-4.5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            aria-hidden="true"
                        >
                            <circle
                                cx="12"
                                cy="12"
                                r="9"
                            />

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 7v5l3 2"
                            />
                        </svg>
                    </div>


                    <div class="min-w-0">

                        <h3
                            class="
                                font-semibold

                                text-zinc-950
                                dark:text-white
                            "
                        >
                            Linha do tempo
                        </h3>

                        <p
                            class="
                                mt-0.5

                                text-xs

                                text-zinc-500
                                dark:text-zinc-400
                            "
                        >
                            Histórico comercial e operacional
                            desta proposta.
                        </p>

                    </div>

                </div>


                {{-- ===================================================== --}}
                {{-- RESUMO DO CICLO --}}
                {{-- ===================================================== --}}

                <div
                    class="
                        border-b
                        border-zinc-100

                        px-5 py-4

                        dark:border-zinc-800
                    "
                >

                    <div
                        class="
                            mb-3

                            flex
                            items-center
                            justify-between
                            gap-3
                        "
                    >

                        <div>
                            <p
                                class="
                                    text-xs
                                    font-semibold

                                    text-zinc-700
                                    dark:text-zinc-200
                                "
                            >
                                Resumo do ciclo
                            </p>

                            <p
                                class="
                                    mt-0.5

                                    text-[11px]

                                    text-zinc-400
                                    dark:text-zinc-500
                                "
                            >
                                Tempo entre as principais etapas.
                            </p>
                        </div>

                    </div>


                    <div
                        class="
                            grid
                            grid-cols-2
                            gap-2
                        "
                    >

                        @foreach (
                            $this->cycleSummary()
                            as $metric
                        )

                            <div
                                data-cycle-metric="{{
                                    $metric['key']
                                }}"

                                class="
                                    min-w-0

                                    rounded-xl

                                    border
                                    border-zinc-100

                                    bg-zinc-50

                                    px-3 py-2.5

                                    dark:border-zinc-800
                                    dark:bg-zinc-950/50
                                "
                            >

                                <p
                                    class="
                                        truncate

                                        text-[10px]
                                        font-medium

                                        text-zinc-500
                                        dark:text-zinc-400
                                    "
                                >
                                    {{ $metric['label'] }}
                                </p>


                                <p
                                    class="
                                        mt-1

                                        truncate

                                        text-sm
                                        font-bold

                                        {{
                                            $metric[
                                                'completed'
                                            ]
                                                ? 'text-zinc-950 dark:text-white'
                                                : 'text-zinc-500 dark:text-zinc-400'
                                        }}
                                    "
                                >
                                    {{ $metric['value'] }}
                                </p>


                                <p
                                    class="
                                        mt-0.5
                                        min-h-6

                                        text-[9px]
                                        leading-3

                                        text-zinc-400
                                        dark:text-zinc-600
                                    "
                                    title="{{
                                        $metric['context']
                                    }}"
                                >
                                    {{ $metric['context'] }}
                                </p>

                            </div>

                        @endforeach

                    </div>

                </div>


                {{-- EVENTOS --}}

                <div class="px-5 py-5">

                    @forelse (
                        $this->timelineEvents()
                        as $event
                    )

                        <div
                            wire:key="quote-event-{{ $event->id }}"

                            class="
                                relative

                                flex
                                gap-4

                                pb-6

                                last:pb-0
                            "
                        >

                            {{-- LINHA VERTICAL --}}

                            @if (! $loop->last)

                                <div
                                    class="
                                        absolute

                                        left-[15px]
                                        top-8
                                        bottom-0

                                        w-px

                                        bg-zinc-200

                                        dark:bg-zinc-800
                                    "
                                ></div>

                            @endif


                            {{-- MARCADOR --}}

                            <div
                                class="
                                    relative
                                    z-10

                                    flex
                                    size-8
                                    shrink-0
                                    items-center
                                    justify-center

                                    rounded-full

                                    bg-white

                                    ring-1
                                    ring-zinc-200

                                    dark:bg-zinc-900
                                    dark:ring-zinc-700
                                "
                            >
                                <span
                                    class="
                                        size-2.5

                                        rounded-full

                                        {{ $this->eventClasses(
                                            $event->type
                                        ) }}
                                    "
                                ></span>
                            </div>


                            {{-- CONTEÚDO --}}

                            <div
                                class="
                                    min-w-0
                                    flex-1
                                "
                            >

                                <div
                                    class="
                                        flex
                                        flex-wrap
                                        items-center
                                        gap-2
                                    "
                                >

                                    <p
                                        class="
                                            text-sm
                                            font-semibold

                                            text-zinc-800
                                            dark:text-zinc-100
                                        "
                                    >
                                        {{ $this->eventLabel(
                                            $event->type
                                        ) }}
                                    </p>


                                    <span
                                        class="
                                            inline-flex

                                            rounded-full

                                            px-2 py-0.5

                                            text-[10px]
                                            font-semibold

                                            {{
                                                $this
                                                    ->eventPhaseClasses(
                                                        $event->type
                                                    )
                                            }}
                                        "
                                    >
                                        {{
                                            $this->eventPhaseLabel(
                                                $event->type
                                            )
                                        }}
                                    </span>

                                </div>


                                <p
                                    class="
                                        mt-1

                                        text-xs

                                        text-zinc-500
                                        dark:text-zinc-400
                                    "
                                >
                                    {{ $event->created_at->format(
                                        'd/m/Y \à\s H:i'
                                    ) }}
                                </p>


                                {{-- FEEDBACK DA RECUSA --}}

                                @if (
                                    $event->type === 'rejected'
                                    && (
                                        ! empty(
                                            $event->metadata[
                                                'label'
                                            ]
                                            ?? null
                                        )
                                        || ! empty(
                                            $event->metadata[
                                                'comment'
                                            ]
                                            ?? null
                                        )
                                    )
                                )

                                    <div
                                        class="
                                            mt-2

                                            rounded-lg

                                            border
                                            border-red-100

                                            bg-red-50/70

                                            px-3 py-2.5

                                            text-xs

                                            dark:border-red-950
                                            dark:bg-red-950/20
                                        "
                                    >

                                        @if (
                                            ! empty(
                                                $event->metadata[
                                                    'label'
                                                ]
                                                ?? null
                                            )
                                        )

                                            <p
                                                class="
                                                    font-semibold

                                                    text-red-700
                                                    dark:text-red-300
                                                "
                                            >
                                                {{
                                                    $event->metadata[
                                                        'label'
                                                    ]
                                                }}
                                            </p>

                                        @endif


                                        @if (
                                            ! empty(
                                                $event->metadata[
                                                    'comment'
                                                ]
                                                ?? null
                                            )
                                        )

                                            <p
                                                class="
                                                    mt-1

                                                    leading-5

                                                    text-red-600
                                                    dark:text-red-400
                                                "
                                            >
                                                {{
                                                    $event->metadata[
                                                        'comment'
                                                    ]
                                                }}
                                            </p>

                                        @endif

                                    </div>

                                @endif

                            </div>

                        </div>

                    @empty

                        <div
                            class="
                                rounded-xl

                                border
                                border-dashed
                                border-zinc-200

                                px-4 py-8

                                text-center

                                dark:border-zinc-800
                            "
                        >

                            <div
                                class="
                                    mx-auto

                                    flex
                                    size-9
                                    items-center
                                    justify-center

                                    rounded-full

                                    bg-zinc-100

                                    text-zinc-400

                                    dark:bg-zinc-800
                                    dark:text-zinc-500
                                "
                            >
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
                                        d="M12 8v4l2 2"
                                    />

                                    <circle
                                        cx="12"
                                        cy="12"
                                        r="9"
                                    />
                                </svg>
                            </div>

                            <p
                                class="
                                    mt-3

                                    text-sm
                                    font-medium

                                    text-zinc-600
                                    dark:text-zinc-300
                                "
                            >
                                Nenhum evento registrado
                            </p>

                            <p
                                class="
                                    mt-1

                                    text-xs

                                    text-zinc-400
                                    dark:text-zinc-500
                                "
                            >
                                Os acontecimentos desta proposta
                                aparecerão aqui.
                            </p>

                        </div>

                    @endforelse

                </div>

            </div>

        </aside>

    </div>