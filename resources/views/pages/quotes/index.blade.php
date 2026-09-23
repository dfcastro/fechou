<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Propostas | Fechou')] class extends Component
{

    /*
    |--------------------------------------------------------------------------
    | Filtro de pós-aceite
    |--------------------------------------------------------------------------
    */

    #[Url(
        as: 'post',
        except: ''
    )]
    public string $postAcceptance = '';


    use WithPagination;

    public string $search = '';
    public string $status = '';

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
    | Propostas
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function quotes()
    {
        if (! $this->business) {
            return collect();
        }

        $search = trim($this->search);

        return $this->business
            ->quotes()

            /*
             * FILTRO PÓS-ACEITE
             *
             * Recebe o parâmetro ?post=...
             * enviado pelo Dashboard.
             */
            ->when(
                $this->postAcceptance !== '',
                function ($query) {

                    return match (
                        $this->postAcceptance
                    ) {

                        /*
                         * Todos os negócios aceitos.
                         */
                        'closed' =>
                            $query->where(
                                'status',
                                'accepted'
                            ),

                        /*
                         * Aceitos ainda não pagos.
                         */
                        'receivable' =>
                            $query
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
                                ),

                        /*
                         * Aceitos cuja execução
                         * ainda não começou.
                         */
                        'awaiting' =>
                            $query
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
                                ),

                        /*
                         * Em execução.
                         */
                        'in_progress' =>
                            $query
                                ->where(
                                    'status',
                                    'accepted'
                                )
                                ->where(
                                    'execution_status',
                                    'in_progress'
                                ),

                        /*
                         * Execução concluída.
                         */
                        'completed' =>
                            $query
                                ->where(
                                    'status',
                                    'accepted'
                                )
                                ->where(
                                    'execution_status',
                                    'completed'
                                ),

                        default => $query,
                    };
                }
            )
            ->with([
                'client',

                /*
                 * Quando estivermos vendo V2, V3...
                 * carregamos também a proposta raiz.
                 */
                'rootQuote' => fn($query) =>
                $query->withCount('versions'),
            ])

            /*
             * Para a V1 sabermos quantas versões filhas existem.
             */
            ->withCount('versions')

            ->when(
                $search !== '',
                function ($query) use ($search) {

                    $numberSearch = ltrim(
                        str_replace('#', '', $search),
                        '0'
                    );

                    $query->where(function ($query) use (
                        $search,
                        $numberSearch
                    ) {
                        $query
                            ->where(
                                'title',
                                'like',
                                '%' . $search . '%'
                            )

                            ->orWhereHas(
                                'client',
                                fn($query) =>
                                $query->where(
                                    'name',
                                    'like',
                                    '%' . $search . '%'
                                )
                            );

                        if (
                            $numberSearch !== ''
                            && ctype_digit($numberSearch)
                        ) {
                            $query->orWhere(
                                'number',
                                (int) $numberSearch
                            );
                        }
                    });
                }
            )

            ->when(
                $this->status !== '',
                fn($query) =>
                $query->where(
                    'status',
                    $this->status
                )
            )

            ->orderByDesc('created_at')
            ->paginate(10);
    }

    public function postAcceptanceLabel(): ?string
    {
        return match (
            $this->postAcceptance
        ) {
            'closed' =>
                'Negócios fechados',

            'receivable' =>
                'A receber',

            'awaiting' =>
                'Aguardando execução',

            'in_progress' =>
                'Em execução',

            'completed' =>
                'Concluídos',

            default =>
                null,
        };
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

        return $this->business
            ->quotes()
            ->whereIn(
                'status',
                ['sent', 'viewed']
            )
            ->count();
    }

    #[Computed]
    public function viewedCount(): int
    {
        if (! $this->business) {
            return 0;
        }

        return $this->business
            ->quotes()
            ->where('status', 'viewed')
            ->count();
    }

    #[Computed]
    public function acceptedCount(): int
    {
        if (! $this->business) {
            return 0;
        }

        return $this->business
            ->quotes()
            ->where('status', 'accepted')
            ->count();
    }

    #[Computed]
    public function openAmount(): float
    {
        if (! $this->business) {
            return 0;
        }

        return (float) $this->business
            ->quotes()
            ->whereIn(
                'status',
                ['sent', 'viewed']
            )
            ->sum('total');
    }

    /*
    |--------------------------------------------------------------------------
    | Filtros
    |--------------------------------------------------------------------------
    */

    public function updatedSearch(): void
    {
        $this->resetPage();
    }


    public function updatedStatus(): void
    {
        if ($this->status !== '') {
            $this->postAcceptance = '';
        }

        $this->resetPage();
    }


    public function updatedPostAcceptance(): void
    {
        if ($this->postAcceptance !== '') {
            $this->status = '';
        }

        $this->resetPage();
    }


    public function clearFilters(): void
    {
        $this->search = '';
        $this->status = '';
        $this->postAcceptance = '';

        $this->resetPage();
    }

    /*
    |--------------------------------------------------------------------------
    | Versionamento
    |--------------------------------------------------------------------------
    */

    public function familyVersionsCount($quote): int
    {
        /*
         * V1:
         *
         * versions_count = quantidade de V2, V3...
         *
         * Total da família:
         * V1 + filhas
         */
        if (! $quote->root_quote_id) {
            return 1 + (int) $quote->versions_count;
        }

        /*
         * V2/V3:
         *
         * buscamos a contagem diretamente da V1.
         */
        return 1
            + (int) (
                $quote->rootQuote?->versions_count
                ?? 0
            );
    }

    public function rootQuoteNumber($quote): ?int
    {
        if (! $quote->root_quote_id) {
            return null;
        }

        return $quote->rootQuote?->number;
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

            default => ucfirst($status),
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
    | Ações rápidas do pós-aceite
    |--------------------------------------------------------------------------
    */

    public function markAsPaidFromList(
        int $quoteId
    ): void {
        abort_unless(
            $this->business,
            403
        );

        $changed =
            \Illuminate\Support\Facades\DB::transaction(
                function () use ($quoteId): bool {

                    $quote = $this
                        ->business
                        ->quotes()
                        ->whereKey($quoteId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    abort_unless(
                        $quote->status === 'accepted',
                        422
                    );

                    if (
                        ($quote->payment_status ?? 'pending')
                        === 'paid'
                    ) {
                        return false;
                    }

                    $quote->update([
                        'payment_status' => 'paid',
                        'paid_at' => now(),
                    ]);

                    $quote->events()->create([
                        'type' => 'payment_received',
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                    ]);

                    return true;
                }
            );

        if ($changed) {
            $this->dispatch(
                'quote-toast',
                message:
                    'Pagamento registrado com sucesso.'
            );
        }
    }


    public function startExecutionFromList(
        int $quoteId
    ): void {
        abort_unless(
            $this->business,
            403
        );

        $changed =
            \Illuminate\Support\Facades\DB::transaction(
                function () use ($quoteId): bool {

                    $quote = $this
                        ->business
                        ->quotes()
                        ->whereKey($quoteId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    abort_unless(
                        $quote->status === 'accepted',
                        422
                    );

                    if (
                        ! in_array(
                            $quote->execution_status,
                            [
                                null,
                                'pending',
                            ],
                            true
                        )
                    ) {
                        return false;
                    }

                    $quote->update([
                        'execution_status' =>
                            'in_progress',

                        'execution_started_at' =>
                            now(),

                        'completed_at' =>
                            null,
                    ]);

                    $quote->events()->create([
                        'type' => 'execution_started',
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                    ]);

                    return true;
                }
            );

        if ($changed) {
            $this->dispatch(
                'quote-toast',
                message:
                    'Execução iniciada.'
            );
        }
    }


    public function completeExecutionFromList(
        int $quoteId
    ): void {
        abort_unless(
            $this->business,
            403
        );

        $changed =
            \Illuminate\Support\Facades\DB::transaction(
                function () use ($quoteId): bool {

                    $quote = $this
                        ->business
                        ->quotes()
                        ->whereKey($quoteId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    abort_unless(
                        $quote->status === 'accepted',
                        422
                    );

                    if (
                        $quote->execution_status
                        !== 'in_progress'
                    ) {
                        return false;
                    }

                    $quote->update([
                        'execution_status' =>
                            'completed',

                        'completed_at' =>
                            now(),
                    ]);

                    $quote->events()->create([
                        'type' => 'execution_completed',
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                    ]);

                    return true;
                }
            );

        if ($changed) {
            $this->dispatch(
                'quote-toast',
                message:
                    'Execução concluída.'
            );
        }
    }


    public function postAcceptanceBadges($quote): array
    {
        if ($quote->status !== 'accepted') {
            return [];
        }

        $badges = [];


        /*
         * Pagamento e execução são independentes.
         *
         * Por isso uma proposta pode, por exemplo,
         * estar em execução e ainda ter pagamento
         * pendente.
         */
        if (
            ($quote->payment_status ?? 'pending')
            !== 'paid'
        ) {
            $badges[] = [
                'label' =>
                    'Pagamento pendente',

                'classes' =>
                    'bg-amber-100 text-amber-800 '
                    . 'dark:bg-amber-950/60 '
                    . 'dark:text-amber-300',
            ];
        }


        $executionStatus =
            $quote->execution_status
            ?? 'pending';


        $badges[] = match ($executionStatus) {

            'in_progress' => [
                'label' =>
                    'Em execução',

                'classes' =>
                    'bg-blue-100 text-blue-700 '
                    . 'dark:bg-blue-950/70 '
                    . 'dark:text-blue-300',
            ],

            'completed' => [
                'label' =>
                    'Concluído',

                'classes' =>
                    'bg-emerald-100 text-emerald-700 '
                    . 'dark:bg-emerald-950/60 '
                    . 'dark:text-emerald-300',
            ],

            default => [
                'label' =>
                    'Aguardando execução',

                'classes' =>
                    'bg-zinc-100 text-zinc-700 '
                    . 'dark:bg-zinc-800 '
                    . 'dark:text-zinc-300',
            ],
        };


        return $badges;
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
                Propostas
            </h1>

        {{-- ===================================================== --}}
        {{-- FILTRO ATIVO DO PÓS-ACEITE --}}
        {{-- ===================================================== --}}

        @if ($this->postAcceptanceLabel())

            <div
                class="
                    mt-3
                    flex w-fit
                    flex-wrap items-center gap-2
                    rounded-lg

                    border border-emerald-200
                    bg-emerald-50

                    px-3 py-2

                    text-sm
                    text-emerald-800

                    dark:border-emerald-900
                    dark:bg-emerald-950/30
                    dark:text-emerald-300
                "
            >

                <span>
                    Exibindo:
                    <strong>
                        {{
                            $this
                                ->postAcceptanceLabel()
                        }}
                    </strong>
                </span>

                <a
                    href="{{
                        route(
                            'quotes.index'
                        )
                    }}"
                    wire:navigate

                    class="
                        font-semibold
                        underline
                        underline-offset-2

                        hover:no-underline
                    "
                >
                    Limpar filtro
                </a>

            </div>

        @endif


            <p
                class="
                    mt-1
                    text-sm

                    text-zinc-500
                    dark:text-zinc-400
                ">
                Acompanhe suas propostas e respostas dos clientes.
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

            Nova proposta

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
            md:grid-cols-4
        ">

        {{-- AGUARDANDO --}}

        <div
            class="
                rounded-xl

                border border-amber-200

                bg-amber-50

                px-4 py-3

                dark:border-amber-900/60
                dark:bg-amber-950/20
            ">

            <div class="flex items-center justify-between gap-3">

                <div>

                    <p
                        class="
                            text-xs font-medium
                            text-amber-700
                            dark:text-amber-400
                        ">
                        Aguardando cliente
                    </p>

                    <p
                        class="
                            mt-1
                            text-xl font-bold
                            text-zinc-950
                            dark:text-zinc-100
                        ">
                        {{ $this->waitingCount }}
                    </p>

                </div>


                <div
                    class="
                        flex size-9
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
                        <circle
                            cx="12"
                            cy="12"
                            r="9" />

                        <path
                            stroke-linecap="round"
                            d="M12 7v5l3 2" />
                    </svg>
                </div>

            </div>

        </div>


        {{-- VISUALIZADOS --}}

        <div
            class="
                rounded-xl

                border border-blue-200

                bg-blue-50

                px-4 py-3

                dark:border-blue-900/60
                dark:bg-blue-950/20
            ">

            <div class="flex items-center justify-between gap-3">

                <div>

                    <p
                        class="
                            text-xs font-medium

                            text-blue-700
                            dark:text-blue-400
                        ">
                        Visualizados
                    </p>

                    <p
                        class="
                            mt-1

                            text-xl font-bold

                            text-zinc-950
                            dark:text-zinc-100
                        ">
                        {{ $this->viewedCount }}
                    </p>

                </div>


                <div
                    class="
                        flex size-9
                        items-center justify-center

                        rounded-lg

                        bg-blue-100
                        text-blue-700

                        dark:bg-blue-950
                        dark:text-blue-300
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
                </div>

            </div>

        </div>


        {{-- ACEITOS --}}

        <div
            class="
                rounded-xl

                border border-emerald-200

                bg-emerald-50

                px-4 py-3

                dark:border-emerald-900/60
                dark:bg-emerald-950/20
            ">

            <div class="flex items-center justify-between gap-3">

                <div>

                    <p
                        class="
                            text-xs font-medium

                            text-emerald-700
                            dark:text-emerald-400
                        ">
                        Aceitos
                    </p>

                    <p
                        class="
                            mt-1

                            text-xl font-bold

                            text-zinc-950
                            dark:text-zinc-100
                        ">
                        {{ $this->acceptedCount }}
                    </p>

                </div>


                <div
                    class="
                        flex size-9
                        items-center justify-center

                        rounded-lg

                        bg-emerald-100
                        text-emerald-700

                        dark:bg-emerald-950
                        dark:text-emerald-300
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
                </div>

            </div>

        </div>


        {{-- EM ABERTO --}}

        <div
            class="
                rounded-xl

                border border-zinc-200

                bg-white

                px-4 py-3

                dark:border-zinc-800
                dark:bg-zinc-900
            ">

            <div class="flex items-center justify-between gap-3">

                <div class="min-w-0">

                    <p
                        class="
                            text-xs font-medium

                            text-zinc-500
                            dark:text-zinc-400
                        ">
                        Em aberto
                    </p>

                    <p
                        class="
                            mt-1

                            truncate

                            text-xl font-bold

                            text-zinc-950
                            dark:text-zinc-100
                        ">
                        R$ {{ number_format(
                            $this->openAmount,
                            2,
                            ',',
                            '.'
                        ) }}
                    </p>

                </div>


                <div
                    class="
                        flex size-9
                        shrink-0
                        items-center justify-center

                        rounded-lg

                        bg-zinc-100

                        text-zinc-600

                        dark:bg-zinc-800
                        dark:text-zinc-300
                    ">
                    <span class="text-sm font-bold">
                        R$
                    </span>
                </div>

            </div>

        </div>

    </div>


    {{-- ========================================================= --}}
    {{-- FILTROS --}}
    {{-- ========================================================= --}}

    <div
        class="
            rounded-2xl

            border border-zinc-200

            bg-white

            p-4

            shadow-sm

            dark:border-zinc-800
            dark:bg-zinc-900
        ">

        <div
            class="
                flex flex-col
                gap-3

                md:flex-row
                md:items-center
            ">

            {{-- PESQUISA --}}

            <div class="relative flex-1">

                <svg
                    class="
                        pointer-events-none

                        absolute
                        left-3 top-1/2

                        size-4

                        -translate-y-1/2

                        text-zinc-400
                    "
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2">
                    <circle
                        cx="11"
                        cy="11"
                        r="7" />

                    <path
                        stroke-linecap="round"
                        d="m20 20-3.5-3.5" />
                </svg>


                <input
                    type="text"

                    wire:model.live.debounce.300ms="search"

                    placeholder="Buscar por número, cliente ou título..."

                    class="
                        w-full

                        rounded-lg

                        border border-zinc-300

                        bg-white

                        py-2.5
                        pl-9 pr-3

                        text-sm
                        text-zinc-900

                        placeholder:text-zinc-400

                        outline-none

                        focus:border-emerald-500
                        focus:ring-2
                        focus:ring-emerald-500/20

                        dark:border-zinc-700
                        dark:bg-zinc-950
                        dark:text-white
                    ">

            </div>


            {{-- STATUS --}}

            <select
                wire:model.live="status"

                class="
                    rounded-lg

                    border border-zinc-300

                    bg-white

                    px-3 py-2.5

                    text-sm
                    text-zinc-900

                    dark:border-zinc-700
                    dark:bg-zinc-950
                    dark:text-zinc-200
                ">
                <option value="">
                    Todos os status
                </option>

                <option value="draft">
                    Rascunho
                </option>

                <option value="sent">
                    Enviado
                </option>

                <option value="viewed">
                    Visualizado
                </option>

                <option value="accepted">
                    Aceito
                </option>

                <option value="rejected">
                    Recusado
                </option>

                <option value="expired">
                    Expirado
                </option>
            </select>


            {{-- PÓS-ACEITE --}}

            <select
                wire:model.live="postAcceptance"

                class="
                    rounded-lg

                    border border-zinc-300

                    bg-white

                    px-3 py-2.5

                    text-sm
                    text-zinc-900

                    dark:border-zinc-700
                    dark:bg-zinc-950
                    dark:text-zinc-200
                ">

                <option value="">
                    Todos os pós-aceites
                </option>

                <option value="closed">
                    Negócios fechados
                </option>

                <option value="receivable">
                    A receber
                </option>

                <option value="awaiting">
                    Aguardando execução
                </option>

                <option value="in_progress">
                    Em execução
                </option>

                <option value="completed">
                    Concluídos
                </option>

            </select>


            @if (
                $search
                || $status
                || $this->postAcceptance !== ''
            )

            <button
                type="button"

                wire:click="clearFilters"

                class="
                        rounded-lg

                        border border-zinc-300

                        bg-white

                        px-3 py-2.5

                        text-sm
                        font-medium
                        text-zinc-600

                        hover:bg-zinc-100
                        hover:text-zinc-950

                        dark:border-zinc-700
                        dark:bg-zinc-900
                        dark:text-zinc-300
                        dark:hover:bg-zinc-800
                        dark:hover:text-white
                    ">
                Limpar
            </button>

            @endif

        </div>

    </div>


    {{-- ========================================================= --}}
    {{-- LISTA --}}
    {{-- ========================================================= --}}

    <div
        x-data="{ quickAction: null, quoteId: null, quoteNumber: null, toast: null, toastTimer: null }"
        @quote-toast.window="
            toast = $event.detail.message;

            clearTimeout(toastTimer);

            toastTimer = setTimeout(
                () => toast = null,
                3500
            );
        "
        @keydown.escape.window="quickAction = null; toast = null"
        class="
            overflow-hidden

            rounded-2xl

            border border-zinc-200

            bg-white

            shadow-sm

            dark:border-zinc-800
            dark:bg-zinc-900
        ">


        {{-- ===================================================== --}}
        {{-- TOAST DE AÇÃO RÁPIDA --}}
        {{-- ===================================================== --}}

        <div
            x-cloak
            x-show="toast !== null"

            x-transition:enter="
                transition
                ease-out
                duration-200
            "
            x-transition:enter-start="
                opacity-0
                translate-y-2
            "
            x-transition:enter-end="
                opacity-100
                translate-y-0
            "
            x-transition:leave="
                transition
                ease-in
                duration-150
            "
            x-transition:leave-start="
                opacity-100
                translate-y-0
            "
            x-transition:leave-end="
                opacity-0
                translate-y-2
            "

            class="
                pointer-events-none

                fixed
                right-4
                top-4
                z-[60]

                w-[calc(100%-2rem)]
                max-w-sm

                sm:right-6
                sm:top-6
            "
        >

            <div
                class="
                    pointer-events-auto

                    flex
                    items-start
                    gap-3

                    rounded-xl

                    border
                    border-emerald-200

                    bg-white

                    px-4 py-3.5

                    shadow-xl
                    shadow-black/10

                    dark:border-emerald-900
                    dark:bg-zinc-900
                "
            >

                <div
                    class="
                        mt-0.5

                        flex
                        size-7
                        shrink-0
                        items-center
                        justify-center

                        rounded-full

                        bg-emerald-100
                        text-emerald-700

                        dark:bg-emerald-950
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
                            d="m5 12 4 4L19 6"
                        />
                    </svg>
                </div>


                <div class="min-w-0 flex-1">

                    <p
                        class="
                            text-xs
                            font-semibold
                            uppercase
                            tracking-wide

                            text-emerald-600
                            dark:text-emerald-400
                        "
                    >
                        Atualizado
                    </p>

                    <p
                        class="
                            mt-0.5

                            text-sm
                            font-medium

                            text-zinc-800
                            dark:text-zinc-100
                        "

                        x-text="toast"
                    ></p>

                </div>


                <button
                    type="button"

                    @click="
                        clearTimeout(toastTimer);
                        toast = null;
                    "

                    class="
                        shrink-0

                        rounded-lg

                        p-1

                        text-zinc-400

                        transition

                        hover:bg-zinc-100
                        hover:text-zinc-700

                        dark:hover:bg-zinc-800
                        dark:hover:text-zinc-200
                    "
                >
                    <span class="sr-only">
                        Fechar notificação
                    </span>

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
                            d="M6 6l12 12M18 6 6 18"
                        />
                    </svg>
                </button>

            </div>

        </div>

        @forelse ($this->quotes as $quote)

        <div
            wire:key="quote-{{ $quote->id }}"
            data-quote-row="{{ $quote->id }}"

            class="
                border-b
                border-zinc-100

                last:border-b-0

                dark:border-zinc-800
            "
        >

            <a
                href="{{ route(
                        'quotes.show',
                        $quote->id
                    ) }}"

                wire:navigate

                class="
                    group
                    block

                    px-5 py-4

                    transition

                    hover:bg-zinc-50

                    sm:px-6

                    dark:hover:bg-zinc-800/40
                "
            >

            <div
                class="
                        flex flex-col
                        gap-4

                        sm:flex-row
                        sm:items-center
                        sm:justify-between
                    ">

                {{-- ================================================= --}}
                {{-- IDENTIFICAÇÃO --}}
                {{-- ================================================= --}}

                <div class="min-w-0 flex-1">

                    <div
                        class="
                                flex flex-wrap
                                items-center
                                gap-2
                            ">

                        {{-- NÚMERO --}}

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


                        {{-- STATUS --}}

                        <span
                            class="
                                    inline-flex

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


                        {{-- ============================================= --}}
                        {{-- STATUS PÓS-ACEITE --}}
                        {{-- ============================================= --}}

                        @foreach (
                            $this->postAcceptanceBadges(
                                $quote
                            ) as $postBadge
                        )

                            <span
                                class="
                                    inline-flex
                                    items-center

                                    rounded-full

                                    px-2 py-0.5

                                    text-[11px]
                                    font-semibold

                                    {{
                                        $postBadge[
                                            'classes'
                                        ]
                                    }}
                                "
                            >
                                {{
                                    $postBadge[
                                        'label'
                                    ]
                                }}
                            </span>

                        @endforeach


                        {{-- VERSÃO --}}

                        @if ($quote->version > 1)

                        <span
                            class="
                                        inline-flex
                                        items-center
                                        gap-1

                                        rounded-full

                                        bg-violet-100

                                        px-2 py-0.5

                                        text-[11px]
                                        font-semibold
                                        text-violet-700

                                        dark:bg-violet-950
                                        dark:text-violet-300
                                    ">
                            V{{ $quote->version }}
                        </span>

                        @endif

                    </div>


                    {{-- TÍTULO --}}

                    <p
                        class="
                                mt-1

                                truncate

                                font-semibold

                                text-zinc-800

                                transition

                                group-hover:text-zinc-950

                                dark:text-zinc-200
                                dark:group-hover:text-white
                            ">
                        {{ $quote->title }}
                    </p>


                    {{-- CLIENTE --}}

                    <p
                        class="
                                mt-1

                                truncate

                                text-sm

                                text-zinc-500
                                dark:text-zinc-400
                            ">
                        {{ $quote->client->name }}
                    </p>


                    {{-- ================================================= --}}
                    {{-- INFORMAÇÃO DE VERSIONAMENTO --}}
                    {{-- ================================================= --}}

                    @if ($quote->root_quote_id)

                    <div
                        class="
                                    mt-2

                                    inline-flex
                                    items-center
                                    gap-1.5

                                    text-xs
                                    font-medium

                                    text-violet-600
                                    dark:text-violet-400
                                ">

                        <svg
                            class="size-3.5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M9 6H5a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-2" />

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="m15 3 6 6-6 6M21 9H9" />
                        </svg>


                        Nova versão da proposta

                        @if ($this->rootQuoteNumber($quote))

                        #{{ str_pad(
                                        $this->rootQuoteNumber($quote),
                                        4,
                                        '0',
                                        STR_PAD_LEFT
                                    ) }}

                        @endif

                    </div>


                    @elseif ($this->familyVersionsCount($quote) > 1)

                    <div
                        class="
                                    mt-2

                                    inline-flex
                                    items-center
                                    gap-1.5

                                    text-xs
                                    font-medium

                                    text-violet-600
                                    dark:text-violet-400
                                ">

                        <svg
                            class="size-3.5"
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


                        {{ $this->familyVersionsCount($quote) }}
                        versões desta proposta

                    </div>

                    @endif

                </div>


                {{-- ================================================= --}}
                {{-- VALOR --}}
                {{-- ================================================= --}}

                <div
                    class="
                            flex items-center
                            justify-between
                            gap-5

                            sm:shrink-0
                        ">

                    <div class="sm:text-right">

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

                                    text-base
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


                        <p
                            class="
                                    mt-1

                                    text-xs

                                    text-zinc-400
                                    dark:text-zinc-500
                                ">
                            {{ $quote->created_at->format(
                                    'd/m/Y'
                                ) }}
                        </p>

                    </div>


                    <svg
                        class="
                                size-5

                                shrink-0

                                text-zinc-300

                                transition

                                group-hover:translate-x-1
                                group-hover:text-zinc-500

                                dark:text-zinc-600
                                dark:group-hover:text-zinc-300
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

            </div>

        </a>


            {{-- ================================================= --}}
            {{-- AÇÕES RÁPIDAS PÓS-ACEITE --}}
            {{-- ================================================= --}}

            @if (
                $quote->status === 'accepted'
                && (
                    ($quote->payment_status ?? 'pending')
                        !== 'paid'
                    || in_array(
                        $quote->execution_status,
                        [
                            null,
                            'pending',
                            'in_progress',
                        ],
                        true
                    )
                )
            )

                <div
                    class="
                        flex
                        flex-wrap
                        items-center
                        gap-2

                        px-5
                        pb-4

                        sm:px-6
                    "
                >




                    @if (
                        ($quote->payment_status ?? 'pending')
                        !== 'paid'
                    )

                        <button
                            type="button"

                            data-quick-action="payment"

                            @click="
                                quickAction = 'payment';
                                quoteId = {{ $quote->id }};
                                quoteNumber = '{{ str_pad(
                                    $quote->number,
                                    4,
                                    '0',
                                    STR_PAD_LEFT
                                ) }}';
                            "

                            class="
                                inline-flex
                                items-center
                                gap-1.5

                                rounded-lg

                                border
                                border-amber-200

                                bg-amber-50

                                px-3 py-1.5

                                text-xs
                                font-semibold
                                text-amber-800

                                transition

                                hover:bg-amber-100

                                dark:border-amber-900
                                dark:bg-amber-950/40
                                dark:text-amber-300
                                dark:hover:bg-amber-950/70
                            "
                        >
                            Marcar como pago
                        </button>

                    @endif


                    @if (
                        in_array(
                            $quote->execution_status,
                            [
                                null,
                                'pending',
                            ],
                            true
                        )
                    )

                        <button
                            type="button"

                            data-quick-action="start"

                            @click="
                                quickAction = 'start';
                                quoteId = {{ $quote->id }};
                                quoteNumber = '{{ str_pad(
                                    $quote->number,
                                    4,
                                    '0',
                                    STR_PAD_LEFT
                                ) }}';
                            "

                            class="
                                inline-flex
                                items-center
                                gap-1.5

                                rounded-lg

                                border
                                border-blue-200

                                bg-blue-50

                                px-3 py-1.5

                                text-xs
                                font-semibold
                                text-blue-700

                                transition

                                hover:bg-blue-100

                                dark:border-blue-900
                                dark:bg-blue-950/40
                                dark:text-blue-300
                                dark:hover:bg-blue-950/70
                            "
                        >
                            Iniciar execução
                        </button>


                    @elseif (
                        $quote->execution_status
                        === 'in_progress'
                    )

                        <button
                            type="button"

                            data-quick-action="complete"

                            @click="
                                quickAction = 'complete';
                                quoteId = {{ $quote->id }};
                                quoteNumber = '{{ str_pad(
                                    $quote->number,
                                    4,
                                    '0',
                                    STR_PAD_LEFT
                                ) }}';
                            "

                            class="
                                inline-flex
                                items-center
                                gap-1.5

                                rounded-lg

                                border
                                border-emerald-200

                                bg-emerald-50

                                px-3 py-1.5

                                text-xs
                                font-semibold
                                text-emerald-700

                                transition

                                hover:bg-emerald-100

                                dark:border-emerald-900
                                dark:bg-emerald-950/40
                                dark:text-emerald-300
                                dark:hover:bg-emerald-950/70
                            "
                        >
                            Concluir execução
                        </button>

                    @endif

                </div>

            @endif

        </div>


        @empty

        {{-- ================================================= --}}
        {{-- VAZIO --}}
        {{-- ================================================= --}}

        <div class="px-6 py-16 text-center">

            <div
                class="
                        mx-auto

                        flex size-12
                        items-center
                        justify-center

                        rounded-full

                        bg-zinc-100

                        text-zinc-400

                        dark:bg-zinc-800
                        dark:text-zinc-500
                    ">
                <svg
                    class="size-6"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M6 2h9l4 4v16H6V2Z" />

                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M14 2v5h5" />
                </svg>
            </div>


            <h3
                class="
                        mt-4

                        font-semibold

                        text-zinc-900
                        dark:text-white
                    ">

                @if ($this->postAcceptance === 'closed')

                    Nenhum negócio fechado

                @elseif ($this->postAcceptance === 'receivable')

                    Nenhum pagamento pendente

                @elseif ($this->postAcceptance === 'awaiting')

                    Nenhuma proposta aguardando execução

                @elseif ($this->postAcceptance === 'in_progress')

                    Nenhuma proposta em execução

                @elseif ($this->postAcceptance === 'completed')

                    Nenhuma execução concluída

                @elseif ($search || $status)

                    Nenhuma proposta encontrada

                @else

                    Nenhuma proposta ainda

                @endif

            </h3>


            <p
                class="
                        mx-auto
                        mt-1

                        max-w-sm

                        text-sm

                        text-zinc-500
                        dark:text-zinc-400
                    ">

                @if ($this->postAcceptance === 'closed')

                    Nenhuma proposta aceita corresponde
                    a este filtro no momento.

                @elseif ($this->postAcceptance === 'receivable')

                    Todos os negócios fechados estão
                    com o pagamento registrado.

                @elseif ($this->postAcceptance === 'awaiting')

                    Não há negócios aguardando
                    o início da execução.

                @elseif ($this->postAcceptance === 'in_progress')

                    Não há serviços ou pedidos
                    em execução no momento.

                @elseif ($this->postAcceptance === 'completed')

                    Nenhuma execução foi marcada
                    como concluída ainda.

                @elseif ($search || $status)

                    Tente alterar os filtros para
                    encontrar outras propostas.

                @else

                    Crie sua primeira proposta e envie
                    uma proposta profissional ao cliente.

                @endif

            </p>


            @if ($this->postAcceptance !== '')

                <a
                    href="{{ route('quotes.index') }}"
                    wire:navigate

                    class="
                        mt-5

                        inline-flex
                        items-center
                        justify-center
                        gap-2

                        rounded-lg

                        border
                        border-zinc-300

                        bg-white

                        px-4 py-2.5

                        text-sm
                        font-semibold
                        text-zinc-700

                        transition

                        hover:bg-zinc-100

                        dark:border-zinc-700
                        dark:bg-zinc-900
                        dark:text-zinc-200
                        dark:hover:bg-zinc-800
                    "
                >
                    Limpar filtro
                </a>


            @elseif (! $search && ! $status)

                <a
                    href="{{ route('quotes.create') }}"
                    wire:navigate

                    class="
                        mt-5

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

                        transition

                        hover:bg-emerald-700

                        dark:bg-emerald-500
                        dark:text-zinc-950
                        dark:hover:bg-emerald-400
                    "
                >
                    + Criar proposta
                </a>

            @endif

        </div>

        @endforelse


        {{-- ===================================================== --}}
        {{-- MODAL DE AÇÃO RÁPIDA --}}
        {{-- ===================================================== --}}

        <div
            x-cloak
            x-show="quickAction !== null"
            x-transition.opacity

            @click.self="quickAction = null"

            class="
                fixed inset-0 z-50

                flex items-center
                justify-center

                bg-black/50

                p-4
            "
        >
            <div
                x-show="quickAction !== null"
                x-transition

                class="
                    w-full
                    max-w-md

                    rounded-2xl

                    border
                    border-zinc-200

                    bg-white

                    p-6

                    shadow-2xl

                    dark:border-zinc-800
                    dark:bg-zinc-900
                "
            >

                <div
                    class="
                        flex items-start
                        justify-between
                        gap-4
                    "
                >

                    <div>
                        <h3
                            class="
                                text-lg
                                font-semibold

                                text-zinc-950
                                dark:text-white
                            "

                            x-text="
                                quickAction === 'payment'
                                    ? 'Confirmar pagamento'
                                    : (
                                        quickAction === 'start'
                                            ? 'Iniciar execução'
                                            : 'Concluir execução'
                                    )
                            "
                        ></h3>

                        <p
                            class="
                                mt-2

                                text-sm
                                leading-6

                                text-zinc-500
                                dark:text-zinc-400
                            "

                            x-text="
                                quickAction === 'payment'
                                    ? 'Registrar o pagamento da proposta #' + quoteNumber + '?'
                                    : (
                                        quickAction === 'start'
                                            ? 'Registrar o início da execução da proposta #' + quoteNumber + '?'
                                            : 'Marcar a execução da proposta #' + quoteNumber + ' como concluída?'
                                    )
                            "
                        ></p>
                    </div>


                    <button
                        type="button"

                        @click="quickAction = null"

                        class="
                            shrink-0

                            rounded-lg

                            p-1.5

                            text-zinc-400

                            transition

                            hover:bg-zinc-100
                            hover:text-zinc-700

                            dark:hover:bg-zinc-800
                            dark:hover:text-zinc-200
                        "
                    >
                        <span class="sr-only">
                            Fechar
                        </span>

                        <svg
                            class="size-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                d="M6 6l12 12M18 6 6 18"
                            />
                        </svg>
                    </button>

                </div>


                <div
                    class="
                        mt-6

                        flex
                        justify-end
                        gap-2
                    "
                >

                    <button
                        type="button"

                        @click="quickAction = null"

                        class="
                            rounded-lg

                            border
                            border-zinc-300

                            bg-white

                            px-4 py-2

                            text-sm
                            font-semibold
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
                            const action = quickAction;
                            const id = quoteId;

                            quickAction = null;

                            if (action === 'payment') {
                                $wire.markAsPaidFromList(id);
                            }

                            if (action === 'start') {
                                $wire.startExecutionFromList(id);
                            }

                            if (action === 'complete') {
                                $wire.completeExecutionFromList(id);
                            }
                        "

                        class="
                            rounded-lg

                            bg-emerald-600

                            px-4 py-2

                            text-sm
                            font-semibold
                            text-white

                            transition

                            hover:bg-emerald-700

                            dark:bg-emerald-500
                            dark:text-zinc-950
                            dark:hover:bg-emerald-400
                        "

                        x-text="
                            quickAction === 'payment'
                                ? 'Confirmar pagamento'
                                : (
                                    quickAction === 'start'
                                        ? 'Iniciar execução'
                                        : 'Concluir execução'
                                )
                        "
                    ></button>

                </div>

            </div>
        </div>

    </div>


    {{-- ========================================================= --}}
    {{-- PAGINAÇÃO --}}
    {{-- ========================================================= --}}

    @if (
    $this->business
    && method_exists($this->quotes, 'hasPages')
    && $this->quotes->hasPages()
    )

    <div>
        {{ $this->quotes->links() }}
    </div>

    @endif

</div>