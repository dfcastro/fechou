<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Orçamentos | Fechou')] class extends Component
{
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
    | Orçamentos
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
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->status = '';

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
                Orçamentos
            </h1>

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


            @if ($search || $status)

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
        class="
            overflow-hidden

            rounded-2xl

            border border-zinc-200

            bg-white

            shadow-sm

            dark:border-zinc-800
            dark:bg-zinc-900
        ">

        @forelse ($this->quotes as $quote)

        <a
            href="{{ route(
                    'quotes.show',
                    $quote->id
                ) }}"

            wire:navigate

            wire:key="quote-{{ $quote->id }}"

            class="
                    group

                    block

                    border-b
                    border-zinc-100

                    px-5 py-4

                    transition

                    last:border-b-0

                    hover:bg-zinc-50

                    sm:px-6

                    dark:border-zinc-800
                    dark:hover:bg-zinc-800/40
                ">

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


                        Nova versão do orçamento

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
                @if ($search || $status)
                Nenhum orçamento encontrado
                @else
                Nenhum orçamento ainda
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
                @if ($search || $status)

                Tente alterar os filtros para encontrar outros orçamentos.

                @else

                Crie seu primeiro orçamento e envie uma proposta profissional ao cliente.

                @endif
            </p>


            @if (! $search && ! $status)

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

                            hover:bg-emerald-700

                            dark:bg-emerald-500
                            dark:text-zinc-950
                        ">
                + Criar orçamento
            </a>

            @endif

        </div>

        @endforelse

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