<?php

use App\Models\Quote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pipeline | Fechou')] class extends Component
{
    public string $search = '';


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
    | Apenas a versão mais recente da família
    |--------------------------------------------------------------------------
    */

    private function latestFamilyQuery(): Builder
    {
        if (! $this->business) {
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
    | Propostas do Pipeline
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function pipelineQuotes()
    {
        if (! $this->business) {
            return collect();
        }

        $search = trim(
            $this->search
        );

        return (
            clone $this->latestFamilyQuery()
        )
            ->whereIn(
                'status',
                [
                    'sent',
                    'viewed',
                    'accepted',
                ]
            )
            ->with([
                'client',

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
            ->when(
                $search !== '',
                function ($query) use ($search) {

                    $numberSearch = ltrim(
                        str_replace(
                            '#',
                            '',
                            $search
                        ),
                        '0'
                    );

                    $query->where(
                        function ($query) use (
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
                                    fn ($query) =>
                                        $query->where(
                                            'name',
                                            'like',
                                            '%' . $search . '%'
                                        )
                                );

                            if (
                                $numberSearch !== ''
                                && ctype_digit(
                                    $numberSearch
                                )
                            ) {
                                $query->orWhere(
                                    'number',
                                    (int) $numberSearch
                                );
                            }
                        }
                    );
                }
            )
            ->orderByDesc(
                'updated_at'
            )
            ->get();
    }


    /*
    |--------------------------------------------------------------------------
    | Classificação da etapa
    |--------------------------------------------------------------------------
    |
    | Uma proposta aparece somente uma vez.
    |
    */

    public function pipelineStage(
        Quote $quote
    ): string {

        if ($quote->status === 'sent') {
            return 'awaiting_client';
        }


        if ($quote->status === 'viewed') {
            return 'viewed';
        }


        $executionStatus =
            $quote->execution_status
            ?: 'pending';


        if (
            $executionStatus
            === 'completed'
        ) {
            return 'completed';
        }


        if (
            $executionStatus
            === 'in_progress'
        ) {
            return 'in_progress';
        }


        if (
            $quote->payment_status
            !== 'paid'
        ) {
            return 'receivable';
        }


        return 'awaiting_execution';
    }


    /*
    |--------------------------------------------------------------------------
    | Definição das colunas
    |--------------------------------------------------------------------------
    */

    private function columnDefinitions(): array
    {
        return [

            'awaiting_client' => [
                'label' =>
                    'Aguardando cliente',

                'description' =>
                    'Enviadas e ainda não visualizadas.',

                'dot' =>
                    'bg-blue-500',

                'badge' =>
                    'bg-blue-50 text-blue-700 '
                    . 'dark:bg-blue-950/50 '
                    . 'dark:text-blue-300',
            ],


            'viewed' => [
                'label' =>
                    'Visualizadas',

                'description' =>
                    'Cliente já abriu a proposta.',

                'dot' =>
                    'bg-amber-500',

                'badge' =>
                    'bg-amber-50 text-amber-700 '
                    . 'dark:bg-amber-950/50 '
                    . 'dark:text-amber-300',
            ],


            'receivable' => [
                'label' =>
                    'A receber',

                'description' =>
                    'Aceitas com pagamento pendente.',

                'dot' =>
                    'bg-orange-500',

                'badge' =>
                    'bg-orange-50 text-orange-700 '
                    . 'dark:bg-orange-950/50 '
                    . 'dark:text-orange-300',
            ],


            'awaiting_execution' => [
                'label' =>
                    'Aguardando execução',

                'description' =>
                    'Pagas e prontas para iniciar.',

                'dot' =>
                    'bg-zinc-500',

                'badge' =>
                    'bg-zinc-100 text-zinc-700 '
                    . 'dark:bg-zinc-800 '
                    . 'dark:text-zinc-300',
            ],


            'in_progress' => [
                'label' =>
                    'Em execução',

                'description' =>
                    'Trabalhos atualmente em andamento.',

                'dot' =>
                    'bg-sky-500',

                'badge' =>
                    'bg-sky-50 text-sky-700 '
                    . 'dark:bg-sky-950/50 '
                    . 'dark:text-sky-300',
            ],


            'completed' => [
                'label' =>
                    'Concluídas',

                'description' =>
                    'Execuções finalizadas.',

                'dot' =>
                    'bg-emerald-500',

                'badge' =>
                    'bg-emerald-50 text-emerald-700 '
                    . 'dark:bg-emerald-950/50 '
                    . 'dark:text-emerald-300',
            ],

        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Colunas com propostas
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function pipelineColumns(): array
    {
        $quotes =
            $this->pipelineQuotes;

        $columns = [];

        foreach (
            $this->columnDefinitions()
            as $key => $definition
        ) {

            $columnQuotes =
                $quotes
                    ->filter(
                        fn ($quote) =>
                            $this->pipelineStage(
                                $quote
                            ) === $key
                    )
                    ->sortByDesc(
                        function ($quote) use ($key) {

                            return $this
                                ->stageEnteredAt(
                                    $quote,
                                    $key
                                )
                                ?->timestamp
                                ?? 0;
                        }
                    )
                    ->values();


            $columns[] = [
                'key' => $key,

                ...$definition,

                'quotes' =>
                    $columnQuotes,

                'count' =>
                    $columnQuotes->count(),
            ];
        }

        return $columns;
    }


    #[Computed]
    public function pipelineTotal(): int
    {
        return $this
            ->pipelineQuotes
            ->count();
    }


    /*
    |--------------------------------------------------------------------------
    | Data de entrada na etapa
    |--------------------------------------------------------------------------
    */

    private function eventAt(
        Quote $quote,
        string $type
    ) {
        return $quote
            ->events
            ->firstWhere(
                'type',
                $type
            )
            ?->created_at;
    }


    public function stageEnteredAt(
        Quote $quote,
        string $stage
    ) {
        return match ($stage) {

            'awaiting_client' =>
                $quote->sent_at
                ?? $this->eventAt(
                    $quote,
                    'sent'
                )
                ?? $quote->created_at,


            'viewed' =>
                $quote->first_viewed_at
                ?? $this->eventAt(
                    $quote,
                    'viewed'
                )
                ?? $quote->updated_at,


            'receivable' =>
                $quote->accepted_at
                ?? $this->eventAt(
                    $quote,
                    'accepted'
                )
                ?? $quote->updated_at,


            'awaiting_execution' =>
                $quote->paid_at
                ?? $this->eventAt(
                    $quote,
                    'payment_received'
                )
                ?? $quote->accepted_at
                ?? $quote->updated_at,


            'in_progress' =>
                $quote->execution_started_at
                ?? $this->eventAt(
                    $quote,
                    'execution_started'
                )
                ?? $quote->updated_at,


            'completed' =>
                $quote->completed_at
                ?? $this->eventAt(
                    $quote,
                    'execution_completed'
                )
                ?? $quote->updated_at,


            default =>
                $quote->updated_at,
        };
    }


    public function stageAge(
        Quote $quote,
        string $stage
    ): string {
        $date =
            $this->stageEnteredAt(
                $quote,
                $stage
            );

        if (! $date) {
            return 'Sem data da etapa';
        }

        return $date
            ->diffForHumans();
    }

    /*
    |--------------------------------------------------------------------------
    | Ações rápidas do Pipeline
    |--------------------------------------------------------------------------
    */

    private function refreshPipeline(): void
    {
        unset(
            $this->pipelineQuotes,
            $this->pipelineColumns,
            $this->pipelineTotal
        );
    }


    private function acceptedQuoteForUpdate(
        int $quoteId
    ): Quote {

        abort_unless(
            $this->business,
            404
        );


        $quote = Quote::query()
            ->where(
                'business_id',
                $this->business->id
            )
            ->lockForUpdate()
            ->findOrFail(
                $quoteId
            );


        abort_unless(
            $quote->status === 'accepted',
            422
        );


        return $quote;
    }


    public function markAsPaidFromPipeline(
        int $quoteId
    ): void {

        $changed = DB::transaction(
            function () use ($quoteId) {

                $quote =
                    $this->acceptedQuoteForUpdate(
                        $quoteId
                    );


                if (
                    $quote->payment_status
                    === 'paid'
                ) {
                    return false;
                }


                $quote->update([
                    'payment_status' =>
                        'paid',

                    'paid_at' =>
                        now(),
                ]);


                $quote
                    ->events()
                    ->create([
                        'type' =>
                            'payment_received',

                        'metadata' => [
                            'origin' =>
                                'pipeline',
                        ],
                    ]);


                return true;
            }
        );


        if (! $changed) {
            return;
        }


        $this->refreshPipeline();


        $this->dispatch(
            'pipeline-toast',
            message:
                'Pagamento marcado como recebido.'
        );
    }


    public function startExecutionFromPipeline(
        int $quoteId
    ): void {

        $changed = DB::transaction(
            function () use ($quoteId) {

                $quote =
                    $this->acceptedQuoteForUpdate(
                        $quoteId
                    );


                $status =
                    $quote->execution_status
                    ?: 'pending';


                if (
                    in_array(
                        $status,
                        [
                            'in_progress',
                            'completed',
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


                $quote
                    ->events()
                    ->create([
                        'type' =>
                            'execution_started',

                        'metadata' => [
                            'origin' =>
                                'pipeline',
                        ],
                    ]);


                return true;
            }
        );


        if (! $changed) {
            return;
        }


        $this->refreshPipeline();


        $this->dispatch(
            'pipeline-toast',
            message:
                'Execução iniciada.'
        );
    }


    public function completeExecutionFromPipeline(
        int $quoteId
    ): void {

        $changed = DB::transaction(
            function () use ($quoteId) {

                $quote =
                    $this->acceptedQuoteForUpdate(
                        $quoteId
                    );


                if (
                    $quote->execution_status
                    === 'completed'
                ) {
                    return false;
                }


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


                $quote
                    ->events()
                    ->create([
                        'type' =>
                            'execution_completed',

                        'metadata' => [
                            'origin' =>
                                'pipeline',
                        ],
                    ]);


                return true;
            }
        );


        if (! $changed) {
            return;
        }


        $this->refreshPipeline();


        $this->dispatch(
            'pipeline-toast',
            message:
                'Execução concluída.'
        );
    }

};
?>


<div
    x-data="{
        confirmOpen: false,
        confirmAction: null,
        confirmQuoteId: null,
        confirmTitle: '',
        confirmMessage: '',
        confirmButton: '',

        toastOpen: false,
        toastMessage: '',
        toastTimer: null,

        ask(action, quoteId) {
            this.confirmAction = action;
            this.confirmQuoteId = quoteId;

            if (action === 'pay') {
                this.confirmTitle =
                    'Confirmar pagamento';

                this.confirmMessage =
                    'Deseja marcar esta proposta como paga?';

                this.confirmButton =
                    'Marcar como pago';
            }

            if (action === 'start') {
                this.confirmTitle =
                    'Iniciar execução';

                this.confirmMessage =
                    'Deseja iniciar a execução deste negócio?';

                this.confirmButton =
                    'Iniciar execução';
            }

            if (action === 'complete') {
                this.confirmTitle =
                    'Concluir execução';

                this.confirmMessage =
                    'Deseja marcar a execução como concluída?';

                this.confirmButton =
                    'Concluir execução';
            }

            this.confirmOpen = true;
        },

        async confirm() {
            const action =
                this.confirmAction;

            const quoteId =
                this.confirmQuoteId;

            this.confirmOpen = false;


            if (action === 'pay') {
                await $wire
                    .markAsPaidFromPipeline(
                        quoteId
                    );
            }


            if (action === 'start') {
                await $wire
                    .startExecutionFromPipeline(
                        quoteId
                    );
            }


            if (action === 'complete') {
                await $wire
                    .completeExecutionFromPipeline(
                        quoteId
                    );
            }
        }
    }"

    x-on:pipeline-toast.window="
        toastMessage =
            $event.detail.message;

        toastOpen = true;

        clearTimeout(
            toastTimer
        );

        toastTimer =
            setTimeout(
                () => {
                    toastOpen = false;
                },
                3500
            );
    "

    class="
        mx-auto
        w-full
        max-w-[1800px]
        space-y-6
    "
>

    {{-- ========================================================= --}}
    {{-- CABEÇALHO --}}
    {{-- ========================================================= --}}

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

            <div
                class="
                    flex
                    flex-wrap
                    items-center
                    gap-2
                "
            >

                <h1
                    class="
                        text-2xl
                        font-semibold
                        tracking-tight

                        text-zinc-950
                        dark:text-white
                    "
                >
                    Pipeline
                </h1>


                <span
                    class="
                        rounded-full

                        bg-zinc-100

                        px-2.5 py-1

                        text-xs
                        font-semibold

                        text-zinc-600

                        dark:bg-zinc-800
                        dark:text-zinc-300
                    "
                >
                    {{ $this->pipelineTotal }}
                    {{
                        $this->pipelineTotal === 1
                            ? 'proposta'
                            : 'propostas'
                    }}
                </span>

            </div>


            <p
                class="
                    mt-1

                    max-w-2xl

                    text-sm
                    leading-6

                    text-zinc-500
                    dark:text-zinc-400
                "
            >
                Acompanhe cada proposta desde o envio
                até a conclusão do negócio.
            </p>

        </div>


        <div
            class="
                flex
                flex-wrap
                gap-2
            "
        >

            <a
                href="{{ route('quotes.index') }}"
                wire:navigate

                class="
                    inline-flex
                    items-center
                    justify-center
                    gap-2

                    rounded-lg

                    border
                    border-zinc-300

                    bg-white

                    px-3.5 py-2

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
                Ver lista
            </a>


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

                    px-3.5 py-2

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
                Nova proposta
            </a>

        </div>

    </div>


    {{-- ========================================================= --}}
    {{-- BUSCA --}}
    {{-- ========================================================= --}}

    <div
        class="
            rounded-2xl

            border
            border-zinc-200

            bg-white

            p-4

            shadow-sm

            dark:border-zinc-800
            dark:bg-zinc-900
        "
    >

        <label
            for="pipeline-search"

            class="
                text-xs
                font-semibold

                text-zinc-600
                dark:text-zinc-300
            "
        >
            Buscar no pipeline
        </label>


        <div
            class="
                relative
                mt-2
            "
        >

            <svg
                class="
                    pointer-events-none

                    absolute
                    left-3
                    top-1/2

                    size-4

                    -translate-y-1/2

                    text-zinc-400
                "

                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                aria-hidden="true"
            >
                <circle
                    cx="11"
                    cy="11"
                    r="7"
                />

                <path
                    stroke-linecap="round"
                    d="m20 20-3.5-3.5"
                />
            </svg>


            <input
                id="pipeline-search"

                type="search"

                wire:model.live.debounce.400ms="search"

                placeholder="
                    Cliente, título ou número da proposta
                "

                class="
                    w-full

                    rounded-xl

                    border
                    border-zinc-300

                    bg-white

                    py-2.5
                    pl-10
                    pr-3

                    text-sm

                    text-zinc-900

                    outline-none

                    transition

                    placeholder:text-zinc-400

                    focus:border-emerald-500
                    focus:ring-2
                    focus:ring-emerald-500/10

                    dark:border-zinc-700
                    dark:bg-zinc-950
                    dark:text-white
                "
            />

        </div>

    </div>


    {{-- ========================================================= --}}
    {{-- KANBAN --}}
    {{-- ========================================================= --}}

    <div
        class="
            grid
            grid-cols-1
            gap-4

            md:grid-cols-2
            xl:grid-cols-3
            2xl:grid-cols-6
        "
    >

        @foreach (
            $this->pipelineColumns
            as $column
        )

            <section
                data-pipeline-column="{{
                    $column['key']
                }}"

                class="
                    min-w-0

                    rounded-2xl

                    border
                    border-zinc-200

                    bg-zinc-50/70

                    dark:border-zinc-800
                    dark:bg-zinc-950/30
                "
            >

                {{-- CABEÇALHO DA COLUNA --}}

                <div
                    class="
                        border-b
                        border-zinc-200

                        p-4

                        dark:border-zinc-800
                    "
                >

                    <div
                        class="
                            flex
                            items-start
                            justify-between
                            gap-3
                        "
                    >

                        <div
                            class="
                                min-w-0
                            "
                        >

                            <div
                                class="
                                    flex
                                    items-center
                                    gap-2
                                "
                            >

                                <span
                                    class="
                                        size-2.5
                                        shrink-0

                                        rounded-full

                                        {{
                                            $column[
                                                'dot'
                                            ]
                                        }}
                                    "
                                ></span>


                                <h2
                                    class="
                                        text-sm
                                        font-semibold

                                        text-zinc-900
                                        dark:text-zinc-100
                                    "
                                >
                                    {{
                                        $column[
                                            'label'
                                        ]
                                    }}
                                </h2>

                            </div>


                            <p
                                class="
                                    mt-1

                                    text-[11px]
                                    leading-4

                                    text-zinc-500
                                    dark:text-zinc-400
                                "
                            >
                                {{
                                    $column[
                                        'description'
                                    ]
                                }}
                            </p>

                        </div>


                        <span
                            class="
                                inline-flex
                                min-w-7
                                shrink-0
                                items-center
                                justify-center

                                rounded-full

                                px-2
                                py-1

                                text-xs
                                font-bold

                                {{
                                    $column[
                                        'badge'
                                    ]
                                }}
                            "
                        >
                            {{
                                $column[
                                    'count'
                                ]
                            }}
                        </span>

                    </div>

                </div>


                {{-- CARDS --}}

                <div
                    class="
                        space-y-3
                        p-3
                    "
                >

                    @forelse (
                        $column['quotes']
                        as $quote
                    )

                        <article
                            wire:key="pipeline-{{
                                $quote->id
                            }}"

                            data-pipeline-card-stage="{{
                                $column['key']
                            }}"

                            data-quote-id="{{
                                $quote->id
                            }}"

                            class="
                                group

                                overflow-hidden

                                rounded-xl

                                border
                                border-zinc-200

                                bg-white

                                shadow-sm

                                transition

                                hover:-translate-y-0.5
                                hover:border-zinc-300
                                hover:shadow-md

                                dark:border-zinc-800
                                dark:bg-zinc-900
                                dark:hover:border-zinc-700
                            "
                        >

                            <a
                                href="{{
                                    route(
                                        'quotes.show',
                                        $quote->id
                                    )
                                }}"

                                wire:navigate

                                class="
                                    block
                                    p-4
                                "
                            >

                            <div
                                class="
                                    flex
                                    items-start
                                    justify-between
                                    gap-3
                                "
                            >

                                <div
                                    class="
                                        min-w-0
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

                                        <span
                                            class="
                                                text-sm
                                                font-bold

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


                                        @if (
                                            ($quote->version ?? 1)
                                            > 1
                                        )

                                            <span
                                                class="
                                                    rounded-full

                                                    bg-violet-50

                                                    px-2
                                                    py-0.5

                                                    text-[10px]
                                                    font-semibold

                                                    text-violet-700

                                                    dark:bg-violet-950/50
                                                    dark:text-violet-300
                                                "
                                            >
                                                V{{
                                                    $quote->version
                                                }}
                                            </span>

                                        @endif

                                    </div>


                                    <p
                                        class="
                                            mt-1

                                            truncate

                                            text-xs
                                            font-medium

                                            text-zinc-600
                                            dark:text-zinc-300
                                        "
                                    >
                                        {{
                                            $quote
                                                ->client
                                                ?->name
                                            ?? 'Cliente'
                                        }}
                                    </p>

                                </div>


                                <span
                                    class="
                                        shrink-0

                                        text-zinc-300

                                        transition

                                        group-hover:
                                        translate-x-0.5

                                        group-hover:
                                        text-zinc-500

                                        dark:text-zinc-700
                                        dark:group-hover:text-zinc-400
                                    "
                                >
                                    →
                                </span>

                            </div>


                            <p
                                class="
                                    mt-3

                                    line-clamp-2

                                    text-sm
                                    font-semibold
                                    leading-5

                                    text-zinc-800
                                    dark:text-zinc-200
                                "
                            >
                                {{ $quote->title }}
                            </p>


                            <div
                                class="
                                    mt-4

                                    flex
                                    items-end
                                    justify-between
                                    gap-3
                                "
                            >

                                <div>

                                    <p
                                        class="
                                            text-[10px]
                                            font-medium
                                            uppercase
                                            tracking-wide

                                            text-zinc-400
                                            dark:text-zinc-500
                                        "
                                    >
                                        Valor
                                    </p>

                                    <p
                                        class="
                                            mt-0.5

                                            text-sm
                                            font-bold

                                            text-zinc-950
                                            dark:text-white
                                        "
                                    >
                                        R$
                                        {{
                                            number_format(
                                                $quote->total,
                                                2,
                                                ',',
                                                '.'
                                            )
                                        }}
                                    </p>

                                </div>


                                <p
                                    class="
                                        text-right

                                        text-[10px]
                                        leading-4

                                        text-zinc-400
                                        dark:text-zinc-500
                                    "
                                >
                                    {{
                                        $this->stageAge(
                                            $quote,
                                            $column['key']
                                        )
                                    }}
                                </p>

                            </div>


                            @if (
                                $quote->status
                                === 'accepted'
                            )

                                <div
                                    class="
                                        mt-3

                                        flex
                                        flex-wrap
                                        gap-1.5
                                    "
                                >

                                    @if (
                                        $quote->payment_status
                                        === 'paid'
                                    )

                                        <span
                                            class="
                                                rounded-full

                                                bg-emerald-50

                                                px-2
                                                py-1

                                                text-[10px]
                                                font-semibold

                                                text-emerald-700

                                                dark:bg-emerald-950/50
                                                dark:text-emerald-300
                                            "
                                        >
                                            Pago
                                        </span>

                                    @else

                                        <span
                                            class="
                                                rounded-full

                                                bg-amber-50

                                                px-2
                                                py-1

                                                text-[10px]
                                                font-semibold

                                                text-amber-700

                                                dark:bg-amber-950/50
                                                dark:text-amber-300
                                            "
                                        >
                                            Pagamento pendente
                                        </span>

                                    @endif


                                    @if (
                                        $this->business
                                            ?->payment_collection_enabled
                                        && $quote
                                            ->payment_collection_enabled
                                    )

                                        <span
                                            data-pipeline-payment-collection

                                            class="
                                                inline-flex
                                                items-center

                                                px-1

                                                text-[10px]
                                                font-medium
                                                text-violet-600

                                                dark:text-violet-400
                                            "
                                        >
                                            Cobrança ativa
                                        </span>

                                    @endif


                                    @if (
                                        $quote->execution_status
                                        === 'in_progress'
                                    )

                                        <span
                                            class="
                                                rounded-full

                                                bg-sky-50

                                                px-2
                                                py-1

                                                text-[10px]
                                                font-semibold

                                                text-sky-700

                                                dark:bg-sky-950/50
                                                dark:text-sky-300
                                            "
                                        >
                                            Em execução
                                        </span>

                                    @elseif (
                                        $quote->execution_status
                                        === 'completed'
                                    )

                                        <span
                                            class="
                                                rounded-full

                                                bg-emerald-50

                                                px-2
                                                py-1

                                                text-[10px]
                                                font-semibold

                                                text-emerald-700

                                                dark:bg-emerald-950/50
                                                dark:text-emerald-300
                                            "
                                        >
                                            Concluído
                                        </span>

                                    @endif

                                </div>

                            @endif

                            </a>


                            @if (
                                $quote->status
                                === 'accepted'
                                && (
                                    $quote->payment_status
                                        !== 'paid'
                                    || in_array(
                                        $quote->execution_status
                                            ?: 'pending',
                                        [
                                            'pending',
                                            'in_progress',
                                        ],
                                        true
                                    )
                                )
                            )

                                <div
                                    data-pipeline-actions

                                    class="
                                        flex
                                        flex-wrap
                                        items-center
                                        gap-1.5

                                        px-4
                                        pb-3
                                    "
                                >

                                    @if (
                                        $quote->payment_status
                                        !== 'paid'
                                    )

                                        <button
                                            type="button"

                                            x-on:click="
                                                ask(
                                                    'pay',
                                                    {{
                                                        $quote->id
                                                    }}
                                                )
                                            "

                                            class="
                                                inline-flex
                                                items-center
                                                justify-center

                                                rounded-lg

                                                border
                                                border-amber-200

                                                bg-amber-50

                                                px-2.5
                                                py-1.5

                                                text-[11px]
                                                font-semibold

                                                text-amber-700

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
                                        $quote->payment_status
                                            !== 'paid'
                                        && $this->business
                                            ?->payment_collection_enabled
                                        && $quote
                                            ->payment_collection_enabled
                                    )

                                        <a
                                            data-pipeline-collect-payment

                                            href="{{
                                                route(
                                                    'quotes.show',
                                                    $quote->id
                                                )
                                            }}#cobranca"

                                            wire:navigate

                                            class="
                                                inline-flex
                                                items-center
                                                justify-center

                                                rounded-lg

                                                border
                                                border-violet-200

                                                bg-violet-50

                                                px-2.5
                                                py-1.5

                                                text-[11px]
                                                font-semibold

                                                text-violet-700

                                                transition

                                                hover:bg-violet-100

                                                dark:border-violet-900
                                                dark:bg-violet-950/40
                                                dark:text-violet-300
                                                dark:hover:bg-violet-950/70
                                            "
                                        >
                                            Cobrar
                                        </a>

                                    @endif


                                    @if (
                                        (
                                            $quote->execution_status
                                            ?: 'pending'
                                        )
                                        === 'pending'
                                    )

                                        <button
                                            type="button"

                                            x-on:click="
                                                ask(
                                                    'start',
                                                    {{
                                                        $quote->id
                                                    }}
                                                )
                                            "

                                            class="
                                                inline-flex
                                                items-center
                                                justify-center

                                                rounded-lg

                                                border
                                                border-sky-200

                                                bg-sky-50

                                                px-2.5
                                                py-1.5

                                                text-[11px]
                                                font-semibold

                                                text-sky-700

                                                transition

                                                hover:bg-sky-100

                                                dark:border-sky-900
                                                dark:bg-sky-950/40
                                                dark:text-sky-300
                                                dark:hover:bg-sky-950/70
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

                                            x-on:click="
                                                ask(
                                                    'complete',
                                                    {{
                                                        $quote->id
                                                    }}
                                                )
                                            "

                                            class="
                                                inline-flex
                                                items-center
                                                justify-center

                                                rounded-lg

                                                border
                                                border-emerald-200

                                                bg-emerald-50

                                                px-2.5
                                                py-1.5

                                                text-[11px]
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

                        </article>

                    @empty

                        <div
                            class="
                                rounded-xl

                                border
                                border-dashed
                                border-zinc-200

                                px-4
                                py-8

                                text-center

                                dark:border-zinc-800
                            "
                        >

                            <p
                                class="
                                    text-xs
                                    font-medium

                                    text-zinc-400
                                    dark:text-zinc-500
                                "
                            >
                                Nenhuma proposta
                                nesta etapa.
                            </p>

                        </div>

                    @endforelse

                </div>

            </section>

        @endforeach

    </div>


    {{-- ========================================================= --}}
    {{-- CONFIRMAÇÃO DAS AÇÕES --}}
    {{-- ========================================================= --}}

    <div
        x-cloak
        x-show="confirmOpen"

        x-on:keydown.escape.window="
            confirmOpen = false
        "

        class="
            fixed
            inset-0
            z-50

            flex
            items-center
            justify-center

            p-4
        "
    >

        <div
            x-show="confirmOpen"

            x-transition.opacity

            x-on:click="
                confirmOpen = false
            "

            class="
                absolute
                inset-0

                bg-black/60
                backdrop-blur-sm
            "
        ></div>


        <div
            x-show="confirmOpen"

            x-transition

            role="dialog"
            aria-modal="true"

            class="
                relative
                z-10

                w-full
                max-w-md

                rounded-2xl

                border
                border-zinc-200

                bg-white

                p-5

                shadow-2xl

                dark:border-zinc-800
                dark:bg-zinc-900
            "
        >

            <h2
                class="
                    text-lg
                    font-semibold

                    text-zinc-950
                    dark:text-white
                "

                x-text="confirmTitle"
            ></h2>


            <p
                class="
                    mt-2

                    text-sm
                    leading-6

                    text-zinc-500
                    dark:text-zinc-400
                "

                x-text="confirmMessage"
            ></p>


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

                    x-on:click="
                        confirmOpen = false
                    "

                    class="
                        rounded-lg

                        border
                        border-zinc-300

                        px-3.5
                        py-2

                        text-sm
                        font-semibold

                        text-zinc-700

                        transition

                        hover:bg-zinc-100

                        dark:border-zinc-700
                        dark:text-zinc-200
                        dark:hover:bg-zinc-800
                    "
                >
                    Cancelar
                </button>


                <button
                    type="button"

                    x-on:click="confirm()"

                    class="
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
                    <span
                        x-text="confirmButton"
                    ></span>
                </button>

            </div>

        </div>

    </div>


    {{-- ========================================================= --}}
    {{-- TOAST --}}
    {{-- ========================================================= --}}

    <div
        x-cloak
        x-show="toastOpen"

        x-transition

        class="
            fixed
            right-4
            top-4
            z-[60]

            max-w-sm

            rounded-xl

            border
            border-emerald-200

            bg-white

            px-4
            py-3

            text-sm
            font-semibold

            text-zinc-800

            shadow-xl

            dark:border-emerald-900
            dark:bg-zinc-900
            dark:text-zinc-100
        "
    >

        <div
            class="
                flex
                items-center
                gap-2
            "
        >

            <span
                class="
                    flex
                    size-6
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
                ✓
            </span>


            <span
                x-text="toastMessage"
            ></span>

        </div>

    </div>


</div>
