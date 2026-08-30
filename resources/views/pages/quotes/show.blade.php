<?php

use App\Models\Quote;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Orçamento | Fechou')] class extends Component
{
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
            && ! str_starts_with($phone, '55')
        ) {
            $phone = '55' . $phone;
        }

        $number = str_pad(
            $this->quote->number,
            4,
            '0',
            STR_PAD_LEFT
        );

        $message =
            "Olá, {$this->quote->client->name}! "
            . "Segue o orçamento #{$number} "
            . "da {$this->quote->business->name}:\n\n"
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
    | Criar nova versão
    |--------------------------------------------------------------------------
    */

    public function createNewVersion()
    {
        $business = Auth::user()->business;

        abort_unless($business, 403);

        $newQuote = DB::transaction(function () use ($business) {

            /*
             * Carrega e trava a proposta original.
             */
            $source = $business
                ->quotes()
                ->with([
                    'items' => fn($query) =>
                    $query->orderBy('sort_order'),
                ])
                ->whereKey($this->quoteId)
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Rascunho não precisa gerar nova versão.
             *
             * Se chegar aqui por algum motivo, apenas
             * retornamos o próprio orçamento.
             */
            if ($source->status === 'draft') {
                return $source;
            }

            /*
             * Todas as versões apontam para a primeira
             * proposta da família.
             *
             * V1:
             * root_quote_id = null
             *
             * V2:
             * root_quote_id = ID da V1
             *
             * V3:
             * root_quote_id = ID da V1
             */
            $rootId =
                $source->root_quote_id
                ?: $source->id;

            /*
             * Descobre qual é a maior versão existente
             * desta proposta.
             */
            $currentMaxVersion = $business
                ->quotes()
                ->withTrashed()
                ->where(function ($query) use ($rootId) {
                    $query
                        ->whereKey($rootId)
                        ->orWhere(
                            'root_quote_id',
                            $rootId
                        );
                })
                ->max('version');

            $nextVersion =
                ((int) $currentMaxVersion) + 1;

            /*
             * Próximo número geral de orçamento.
             */
            $lastQuote = $business
                ->quotes()
                ->withTrashed()
                ->lockForUpdate()
                ->orderByDesc('number')
                ->first();

            $nextNumber =
                ($lastQuote?->number ?? 0) + 1;

            /*
             * Validade.
             *
             * Se a proposta anterior ainda estiver válida,
             * mantemos a data.
             *
             * Se estiver vencida ou sem validade,
             * criamos uma nova validade de 7 dias.
             */
            if (
                $source->valid_until
                && $source->valid_until
                ->copy()
                ->endOfDay()
                ->isFuture()
            ) {
                $validUntil =
                    $source->valid_until->copy();
            } else {
                $validUntil =
                    now()
                    ->addDays(7)
                    ->startOfDay();
            }

            /*
             * Cria uma nova proposta independente.
             *
             * O public_token será criado automaticamente
             * pelo model Quote.
             */
            $newQuote = $business
                ->quotes()
                ->create([
                    'client_id' =>
                    $source->client_id,

                    'root_quote_id' =>
                    $rootId,

                    'version' =>
                    $nextVersion,

                    'number' =>
                    $nextNumber,

                    'title' =>
                    $source->title,

                    'description' =>
                    $source->description,

                    'subtotal' =>
                    $source->subtotal,

                    'discount' =>
                    $source->discount,

                    'total' =>
                    $source->total,

                    'status' =>
                    'draft',

                    'valid_until' =>
                    $validUntil,

                    'notes' =>
                    $source->notes,

                    'sent_at' =>
                    null,

                    'first_viewed_at' =>
                    null,

                    'accepted_at' =>
                    null,

                    'rejected_at' =>
                    null,
                ]);

            /*
             * Copia todos os itens.
             */
            $newQuote
                ->items()
                ->createMany(
                    $source
                        ->items
                        ->map(fn($item) => [
                            'type' =>
                            $item->type,

                            'description' =>
                            $item->description,

                            'quantity' =>
                            $item->quantity,

                            'unit' =>
                            $item->unit,

                            'unit_price' =>
                            $item->unit_price,

                            'total' =>
                            $item->total,

                            'sort_order' =>
                            $item->sort_order,
                        ])
                        ->all()
                );

            /*
             * Histórico da nova versão.
             */
            $newQuote
                ->events()
                ->create([
                    'type' =>
                    'created',

                    'metadata' => [
                        'source_quote_id' =>
                        $source->id,

                        'source_quote_number' =>
                        $source->number,

                        'version' =>
                        $nextVersion,
                    ],
                ]);

            /*
             * Histórico da proposta original.
             */
            $source
                ->events()
                ->create([
                    'type' =>
                    'version_created',

                    'metadata' => [
                        'new_quote_id' =>
                        $newQuote->id,

                        'new_quote_number' =>
                        $newQuote->number,

                        'version' =>
                        $nextVersion,
                    ],
                ]);

            return $newQuote;
        });

        /*
         * Caso fosse rascunho.
         */
        if ($newQuote->id === $this->quoteId) {
            return $this->redirect(
                route(
                    'quotes.edit',
                    $newQuote->id
                ),
                navigate: true
            );
        }

        session()->flash(
            'success',
            'Nova versão criada. Revise as informações antes de enviar ao cliente.'
        );

        /*
         * A nova versão já abre em modo edição.
         */
        return $this->redirect(
            route(
                'quotes.edit',
                $newQuote->id
            ),
            navigate: true
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
            'Orçamento criado',

            'updated' =>
            'Orçamento editado',

            'sent' =>
            'Orçamento enviado',

            'viewed' =>
            'Cliente visualizou',

            'accepted' =>
            'Cliente aceitou',

            'rejected' =>
            'Cliente recusou',

            'version_created' =>
            'Nova versão criada',
            'follow_up' => 'Follow-up realizado',

            default =>
            ucfirst($type),
        };
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

            'version_created' =>
            'bg-violet-500',

            'follow_up' =>
            'bg-cyan-500',

            default =>
            'bg-zinc-400 dark:bg-zinc-600',
        };
    }
};
?>

<div
    wire:poll.10s
    class="mx-auto max-w-7xl space-y-6">

    {{-- ========================================================= --}}
    {{-- MENSAGEM --}}
    {{-- ========================================================= --}}

    @if (session('success'))

    <div
        class="
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
    {{-- CABEÇALHO --}}
    {{-- ========================================================= --}}

    <div
        class="
            flex flex-col gap-5

            xl:flex-row
            xl:items-start
            xl:justify-between
        ">

        {{-- IDENTIFICAÇÃO --}}

        <div class="min-w-0">

            <a
                href="{{ route('quotes.index') }}"
                wire:navigate

                class="
                    inline-flex
                    items-center
                    gap-2

                    text-sm
                    font-medium

                    text-zinc-500

                    transition

                    hover:text-zinc-950

                    dark:text-zinc-400
                    dark:hover:text-white
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
                        d="M15 18l-6-6 6-6" />
                </svg>

                Voltar para orçamentos

            </a>


            <div class="mt-4 flex flex-wrap items-center gap-2.5">

                {{-- NÚMERO --}}

                <h1
                    class="
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


                {{-- STATUS --}}

                <span
                    class="
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


                {{-- VERSÃO --}}

                @if ($this->quote->version > 1)

                <span
                    class="
                            inline-flex
                            items-center
                            gap-1

                            rounded-full

                            bg-violet-100

                            px-2.5 py-1

                            text-xs
                            font-semibold
                            text-violet-700

                            dark:bg-violet-950
                            dark:text-violet-300
                        ">

                    <svg
                        class="size-3"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M8 7h11v11H8z" />

                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M5 16H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h11a1 1 0 0 1 1 1v1" />
                    </svg>

                    Versão {{ $this->quote->version }}

                </span>

                @endif

            </div>


            <h2
                class="
                    mt-2

                    text-lg
                    font-semibold

                    text-zinc-800
                    dark:text-zinc-200
                ">
                {{ $this->quote->title }}
            </h2>


            <p
                class="
                    mt-1

                    text-sm

                    text-zinc-500
                    dark:text-zinc-400
                ">
                Criado em
                {{ $this->quote->created_at->format(
                    'd/m/Y \à\s H:i'
                ) }}
            </p>

        </div>


        {{-- ===================================================== --}}
        {{-- AÇÕES + TOTAL --}}
        {{-- ===================================================== --}}

        <div
            class="
                flex flex-col
                gap-3

                lg:flex-row
                lg:items-center
            ">

            <div
                x-data="{ copied: false }"
                class="flex flex-wrap gap-2">

                {{-- ================================================= --}}
                {{-- EDITAR --}}
                {{-- ================================================= --}}

                @if ($this->quote->status === 'draft')

                <a
                    href="{{ route(
                            'quotes.edit',
                            $this->quote->id
                        ) }}"
                    wire:navigate

                    class="
                            inline-flex
                            items-center
                            justify-center
                            gap-2

                            rounded-lg

                            border border-zinc-300

                            bg-white

                            px-4 py-2.5

                            text-sm
                            font-semibold
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
                    <svg
                        class="size-4"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z" />
                    </svg>

                    Editar orçamento

                </a>

                @endif


                {{-- ================================================= --}}
                {{-- NOVA VERSÃO --}}
                {{-- ================================================= --}}

                @if ($this->quote->status !== 'draft')

                <button
                    type="button"

                    wire:click="createNewVersion"

                    wire:confirm="Será criado um novo orçamento em rascunho com os mesmos dados desta proposta. Deseja continuar?"

                    wire:loading.attr="disabled"
                    wire:target="createNewVersion"

                    class="
                            inline-flex
                            items-center
                            justify-center
                            gap-2

                            rounded-lg

                            border border-violet-200

                            bg-violet-50

                            px-4 py-2.5

                            text-sm
                            font-semibold
                            text-violet-700

                            shadow-sm

                            transition

                            hover:bg-violet-100

                            disabled:cursor-not-allowed
                            disabled:opacity-60

                            dark:border-violet-900
                            dark:bg-violet-950/40
                            dark:text-violet-300
                            dark:hover:bg-violet-950/70
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
                            d="M8 7h11v11H8z" />

                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M5 16H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h11a1 1 0 0 1 1 1v1" />
                    </svg>


                    <span
                        wire:loading.remove
                        wire:target="createNewVersion">
                        Criar nova versão
                    </span>


                    <span
                        wire:loading
                        wire:target="createNewVersion">
                        Criando...
                    </span>

                </button>

                @endif


                {{-- ================================================= --}}
                {{-- VISUALIZAR PDF --}}
                {{-- ================================================= --}}

                <a
                    href="{{ route(
                        'quotes.pdf.preview',
                        $this->quote->id
                    ) }}"

                    target="_blank"
                    rel="noopener noreferrer"

                    class="
                        inline-flex
                        items-center
                        justify-center
                        gap-2

                        rounded-lg

                        border border-zinc-300

                        bg-white

                        px-4 py-2.5

                        text-sm
                        font-semibold
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

                    <svg
                        class="size-4"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" />

                        <circle
                            cx="12"
                            cy="12"
                            r="2.5" />
                    </svg>

                    Visualizar PDF

                </a>


                {{-- ================================================= --}}
                {{-- BAIXAR PDF --}}
                {{-- ================================================= --}}

                <a
                    href="{{ route(
                        'quotes.pdf',
                        $this->quote->id
                    ) }}"

                    class="
                        inline-flex
                        items-center
                        justify-center
                        gap-2

                        rounded-lg

                        border border-zinc-300

                        bg-white

                        px-4 py-2.5

                        text-sm
                        font-semibold
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

                    <svg
                        class="size-4"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 3v12" />

                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="m7 10 5 5 5-5" />

                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M5 21h14" />
                    </svg>

                    Baixar PDF

                </a>


                {{-- ================================================= --}}
                {{-- COPIAR LINK --}}
                {{-- ================================================= --}}

                <button
                    type="button"

                    wire:click="markAsSent"

                    x-on:click="
                        navigator.clipboard.writeText(
                            @js($this->publicUrl)
                        );

                        copied = true;

                        setTimeout(
                            () => copied = false,
                            2000
                        );
                    "

                    class="
                        inline-flex
                        items-center
                        justify-center
                        gap-2

                        rounded-lg

                        border border-zinc-300

                        bg-white

                        px-4 py-2.5

                        text-sm
                        font-semibold
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

                    <svg
                        class="size-4"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M10 13a5 5 0 0 0 7.1.1l2-2a5 5 0 0 0-7.1-7.1l-1.1 1.1" />

                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M14 11a5 5 0 0 0-7.1-.1l-2 2A5 5 0 0 0 12 20l1.1-1.1" />
                    </svg>


                    <span x-show="!copied">
                        Copiar link
                    </span>


                    <span
                        x-show="copied"
                        x-cloak

                        class="
                            text-emerald-600
                            dark:text-emerald-400
                        ">
                        Copiado!
                    </span>

                </button>


                {{-- ================================================= --}}
                {{-- WHATSAPP --}}
                {{-- ================================================= --}}

                <a
                    href="{{ $this->whatsappUrl }}"

                    target="_blank"
                    rel="noopener noreferrer"

                    wire:click="markAsSent"

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

                    Enviar por WhatsApp

                </a>

            </div>


            {{-- ================================================= --}}
            {{-- TOTAL --}}
            {{-- ================================================= --}}

            <div
                class="
                    min-w-44

                    rounded-xl

                    border border-emerald-200

                    bg-emerald-50

                    px-5 py-3

                    shadow-sm

                    dark:border-zinc-700
                    dark:bg-zinc-900
                ">

                <p
                    class="
                        text-xs
                        font-semibold
                        uppercase
                        tracking-wide

                        text-emerald-700
                        dark:text-emerald-400
                    ">
                    Total
                </p>


                <p
                    class="
                        mt-1

                        whitespace-nowrap

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

    </div>


    {{-- ========================================================= --}}
    {{-- GRID PRINCIPAL --}}
    {{-- ========================================================= --}}

    <div
        class="
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

            <section
                class="
                    rounded-2xl

                    border border-zinc-200

                    bg-white

                    shadow-sm

                    dark:border-zinc-800
                    dark:bg-zinc-900
                ">

                <div
                    class="
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

                        <div
                            class="
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

                            <p
                                class="
                                    font-semibold

                                    text-zinc-950
                                    dark:text-white
                                ">
                                {{ $this->quote->client->name }}
                            </p>


                            <div
                                class="
                                    mt-2
                                    space-y-1

                                    text-sm

                                    text-zinc-500
                                    dark:text-zinc-400
                                ">

                                @if ($this->quote->client->document)

                                <p>
                                    CPF/CNPJ:

                                    <span
                                        class="
                                                text-zinc-700
                                                dark:text-zinc-300
                                            ">
                                        {{ $this->quote->client->document }}
                                    </span>
                                </p>

                                @endif


                                @if ($this->quote->client->whatsapp)

                                <p>
                                    WhatsApp:

                                    <span
                                        class="
                                                text-zinc-700
                                                dark:text-zinc-300
                                            ">
                                        {{ $this->quote->client->whatsapp }}
                                    </span>
                                </p>

                                @elseif ($this->quote->client->phone)

                                <p>
                                    Telefone:

                                    <span
                                        class="
                                                text-zinc-700
                                                dark:text-zinc-300
                                            ">
                                        {{ $this->quote->client->phone }}
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

            <section
                class="
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


                <p
                    class="
                            mt-3

                            whitespace-pre-line

                            text-sm
                            leading-6

                            text-zinc-600
                            dark:text-zinc-300
                        ">
                    {{ $this->quote->description }}
                </p>

            </section>

            @endif


            {{-- ================================================== --}}
            {{-- ITENS --}}
            {{-- ================================================== --}}

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
                            Itens do orçamento
                        </h3>

                        <p
                            class="
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


                <div
                    class="
                        divide-y
                        divide-zinc-100

                        dark:divide-zinc-800
                    ">

                    @foreach ($this->quote->items as $item)

                    <div
                        wire:key="quote-item-{{ $item->id }}"

                        class="
                                px-6 py-4

                                transition

                                hover:bg-zinc-50

                                dark:hover:bg-zinc-800/30
                            ">

                        <div
                            class="
                                    flex flex-col
                                    gap-4

                                    sm:flex-row
                                    sm:items-center
                                    sm:justify-between
                                ">

                            <div class="min-w-0">

                                <div class="flex flex-wrap items-center gap-2">

                                    <p
                                        class="
                                                font-semibold

                                                text-zinc-900
                                                dark:text-white
                                            ">
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

            <section
                class="
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

        </div>


        {{-- ====================================================== --}}
        {{-- LATERAL --}}
        {{-- ====================================================== --}}

        <aside class="space-y-6 xl:sticky xl:top-6">


            {{-- ================================================== --}}
            {{-- RESUMO --}}
            {{-- ================================================== --}}

            <div
                class="
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

                        <span
                            class="
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


                        <span
                            class="
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

                    <div
                        class="
                            border-t
                            border-zinc-200

                            pt-4

                            dark:border-zinc-800
                        ">

                        <p class="text-sm text-zinc-500 dark:text-zinc-400">
                            Total
                        </p>

                        <p
                            class="
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

                <div
                    class="
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

                        <span
                            class="
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

                <div
                    class="
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

                    <span
                        class="
                            font-semibold

                            text-zinc-700
                            dark:text-zinc-300
                        ">
                        {{ $this->quote->version }}
                    </span>

                </div>


                {{-- STATUS --}}

                <div
                    class="
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


                    <span
                        class="
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
            {{-- HISTÓRICO --}}
            {{-- ================================================== --}}

            <div
                class="
                    rounded-2xl

                    border border-zinc-200

                    bg-white

                    p-5

                    shadow-sm

                    dark:border-zinc-800
                    dark:bg-zinc-900
                ">

                <div>

                    <h3 class="font-semibold text-zinc-950 dark:text-white">
                        Histórico
                    </h3>

                    <p
                        class="
                            mt-1

                            text-xs

                            text-zinc-500
                            dark:text-zinc-400
                        ">
                        Acompanhe o que aconteceu com este orçamento.
                    </p>

                </div>


                <div class="mt-5">

                    @forelse ($this->quote->events as $event)

                    <div
                        wire:key="quote-event-{{ $event->id }}"

                        class="
                                relative

                                flex
                                gap-3

                                pb-5

                                last:pb-0
                            ">

                        {{-- LINHA --}}

                        @if (! $loop->last)

                        <div
                            class="
                                        absolute

                                        left-[5px]
                                        top-3

                                        h-full
                                        w-px

                                        bg-zinc-200

                                        dark:bg-zinc-800
                                    "></div>

                        @endif


                        {{-- PONTO --}}

                        <div
                            class="
                                    relative
                                    z-10

                                    mt-1

                                    size-3
                                    shrink-0

                                    rounded-full

                                    ring-4
                                    ring-white

                                    {{ $this->eventClasses(
                                        $event->type
                                    ) }}

                                    dark:ring-zinc-900
                                "></div>


                        {{-- TEXTO --}}

                        <div class="min-w-0">

                            <p
                                class="
                                        text-sm
                                        font-medium

                                        text-zinc-800
                                        dark:text-zinc-200
                                    ">
                                {{ $this->eventLabel(
                                        $event->type
                                    ) }}
                            </p>


                            {{-- Detalhe da versão criada --}}

                            @if (
                            $event->type === 'version_created'
                            && isset(
                            $event->metadata['new_quote_number']
                            )
                            )

                            <p
                                class="
                                            mt-0.5

                                            text-xs
                                            font-medium

                                            text-violet-600
                                            dark:text-violet-400
                                        ">
                                Orçamento
                                #{{ str_pad(
                                            $event->metadata['new_quote_number'],
                                            4,
                                            '0',
                                            STR_PAD_LEFT
                                        ) }}

                                @if (isset($event->metadata['version']))
                                • Versão {{ $event->metadata['version'] }}
                                @endif
                            </p>

                            @endif


                            <p
                                class="
                                        mt-0.5

                                        text-xs

                                        text-zinc-500
                                        dark:text-zinc-400
                                    ">
                                {{ $event->created_at->format(
                                        'd/m/Y \à\s H:i'
                                    ) }}
                            </p>

                        </div>

                    </div>

                    @empty

                    <p
                        class="
                                text-sm

                                text-zinc-500
                                dark:text-zinc-400
                            ">
                        Nenhum evento registrado.
                    </p>

                    @endforelse

                </div>

            </div>

        </aside>

    </div>

</div>