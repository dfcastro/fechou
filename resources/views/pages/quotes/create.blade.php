<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Novo orçamento | Fechou')] class extends Component
{
    /*
    |--------------------------------------------------------------------------
    | Cliente
    |--------------------------------------------------------------------------
    */

    public ?int $clientId = null;
    public string $clientSearch = '';
    public bool $showClientResults = false;

    /*
    |--------------------------------------------------------------------------
    | Cadastro rápido de cliente
    |--------------------------------------------------------------------------
    */

    public bool $showClientModal = false;

    public string $newClientName = '';
    public string $newClientWhatsapp = '';
    public string $newClientDocument = '';
    public string $newClientEmail = '';

    /*
    |--------------------------------------------------------------------------
    | Orçamento
    |--------------------------------------------------------------------------
    */

    public string $title = '';
    public string $description = '';
    public string $validUntil = '';
    public string $discount = '0';
    public string $notes = '';

    /*
    |--------------------------------------------------------------------------
    | Itens
    |--------------------------------------------------------------------------
    */

    public array $items = [];

    public bool $showItemForm = false;
    public ?int $editingItemIndex = null;

    public string $itemType = 'service';
    public string $itemDescription = '';
    public string $itemQuantity = '1';
    public string $itemUnit = 'serviço';
    public string $itemUnitPrice = '';

    /*
    |--------------------------------------------------------------------------
    | Inicialização
    |--------------------------------------------------------------------------
    */

    public function mount(): void
    {
        $this->validUntil = now()
            ->addDays(7)
            ->format('Y-m-d');
    }

    #[Computed]
    public function business()
    {
        return Auth::user()->business;
    }

    /*
    |--------------------------------------------------------------------------
    | Pesquisa de clientes
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function clientResults()
    {
        if (! $this->business) {
            return collect();
        }

        $search = trim($this->clientSearch);

        if (mb_strlen($search) < 2) {
            return collect();
        }

        return $this->business
            ->clients()
            ->where(function ($query) use ($search) {
                $query
                    ->where('name', 'like', $search . '%')
                    ->orWhere('document', 'like', $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%')
                    ->orWhere('whatsapp', 'like', '%' . $search . '%');
            })
            ->orderBy('name')
            ->limit(20)
            ->get();
    }

    public function updatedClientSearch(): void
    {
        /*
         * Se o usuário alterar o texto manualmente,
         * o cliente anterior deixa de estar selecionado.
         */
        $this->clientId = null;
        $this->showClientResults = true;

        $this->resetValidation('clientId');
    }

    public function selectClient(int $clientId): void
    {
        abort_unless($this->business, 403);

        $client = $this->business
            ->clients()
            ->whereKey($clientId)
            ->firstOrFail();

        $this->clientId = $client->id;
        $this->clientSearch = $client->name;
        $this->showClientResults = false;

        $this->resetValidation('clientId');
    }

    public function clearClient(): void
    {
        $this->clientId = null;
        $this->clientSearch = '';
        $this->showClientResults = false;

        $this->resetValidation('clientId');
    }

    /*
    |--------------------------------------------------------------------------
    | Cadastro rápido de cliente
    |--------------------------------------------------------------------------
    */

    public function openClientModal(): void
    {
        $this->newClientName = trim($this->clientSearch);

        $this->newClientWhatsapp = '';
        $this->newClientDocument = '';
        $this->newClientEmail = '';

        $this->showClientResults = false;
        $this->showClientModal = true;

        $this->resetValidation([
            'newClientName',
            'newClientWhatsapp',
            'newClientDocument',
            'newClientEmail',
        ]);
    }

    public function closeClientModal(): void
    {
        $this->showClientModal = false;

        $this->resetValidation([
            'newClientName',
            'newClientWhatsapp',
            'newClientDocument',
            'newClientEmail',
        ]);
    }

    public function saveNewClient(): void
    {
        abort_unless($this->business, 403);

        $validated = $this->validate([
            'newClientName' => [
                'required',
                'string',
                'max:255',
            ],

            'newClientWhatsapp' => [
                'nullable',
                'string',
                'max:30',
            ],

            'newClientDocument' => [
                'nullable',
                'string',
                'max:20',
            ],

            'newClientEmail' => [
                'nullable',
                'email',
                'max:255',
            ],
        ], [
            'newClientName.required' =>
            'Informe o nome do cliente.',

            'newClientEmail.email' =>
            'Informe um e-mail válido.',
        ]);

        /*
         * Evita cadastro duplicado quando o mesmo CPF/CNPJ
         * já existe para esta empresa.
         */
        if ($validated['newClientDocument']) {

            $existingClient = $this->business
                ->clients()
                ->where(
                    'document',
                    trim($validated['newClientDocument'])
                )
                ->first();

            if ($existingClient) {

                $this->addError(
                    'newClientDocument',
                    'Já existe um cliente cadastrado com este CPF/CNPJ.'
                );

                return;
            }
        }

        $client = $this->business
            ->clients()
            ->create([
                'name' =>
                trim($validated['newClientName']),

                'document' =>
                $validated['newClientDocument']
                    ? trim($validated['newClientDocument'])
                    : null,

                'email' =>
                $validated['newClientEmail']
                    ? trim($validated['newClientEmail'])
                    : null,

                'whatsapp' =>
                $validated['newClientWhatsapp']
                    ? trim($validated['newClientWhatsapp'])
                    : null,

                'phone' =>
                null,

                'notes' =>
                null,
            ]);

        /*
         * O cliente criado já fica selecionado.
         */
        $this->clientId = $client->id;
        $this->clientSearch = $client->name;

        $this->showClientModal = false;
        $this->showClientResults = false;

        $this->newClientName = '';
        $this->newClientWhatsapp = '';
        $this->newClientDocument = '';
        $this->newClientEmail = '';

        $this->resetValidation('clientId');
    }

    /*
    |--------------------------------------------------------------------------
    | Itens
    |--------------------------------------------------------------------------
    */

    public function newItem(string $type): void
    {
        if (! in_array(
            $type,
            ['service', 'material', 'other'],
            true
        )) {
            return;
        }

        $this->resetItemForm();

        $this->itemType = $type;

        $this->itemUnit = match ($type) {
            'service' => 'serviço',
            'material' => 'un',
            default => 'un',
        };

        $this->showItemForm = true;
    }

    public function editItem(int $index): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        $item = $this->items[$index];

        $this->editingItemIndex = $index;

        $this->itemType = $item['type'];
        $this->itemDescription = $item['description'];
        $this->itemQuantity = (string) $item['quantity'];
        $this->itemUnit = $item['unit'];
        $this->itemUnitPrice = (string) $item['unit_price'];

        $this->showItemForm = true;

        $this->resetValidation([
            'itemDescription',
            'itemQuantity',
            'itemUnit',
            'itemUnitPrice',
        ]);
    }

    public function saveItem(): void
    {
        $validated = $this->validate([
            'itemType' => [
                'required',
                'in:service,material,other',
            ],

            'itemDescription' => [
                'required',
                'string',
                'max:255',
            ],

            'itemQuantity' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'itemUnit' => [
                'required',
                'string',
                'max:20',
            ],

            'itemUnitPrice' => [
                'required',
                'numeric',
                'min:0',
            ],
        ], [
            'itemDescription.required' =>
            'Informe a descrição do item.',

            'itemQuantity.required' =>
            'Informe a quantidade.',

            'itemQuantity.gt' =>
            'A quantidade deve ser maior que zero.',

            'itemUnit.required' =>
            'Informe a unidade.',

            'itemUnitPrice.required' =>
            'Informe o valor unitário.',
        ]);

        $item = [
            'type' =>
            $validated['itemType'],

            'description' =>
            trim($validated['itemDescription']),

            'quantity' =>
            (float) $validated['itemQuantity'],

            'unit' =>
            trim($validated['itemUnit']),

            'unit_price' =>
            (float) $validated['itemUnitPrice'],
        ];

        if ($this->editingItemIndex !== null) {
            $this->items[$this->editingItemIndex] = $item;
        } else {
            $this->items[] = $item;
        }

        $this->cancelItemForm();
    }

    public function removeItem(int $index): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        unset($this->items[$index]);

        $this->items = array_values($this->items);
    }

    public function cancelItemForm(): void
    {
        $this->showItemForm = false;

        $this->resetItemForm();
    }

    private function resetItemForm(): void
    {
        $this->editingItemIndex = null;

        $this->itemType = 'service';
        $this->itemDescription = '';
        $this->itemQuantity = '1';
        $this->itemUnit = 'serviço';
        $this->itemUnitPrice = '';

        $this->resetValidation([
            'itemDescription',
            'itemQuantity',
            'itemUnit',
            'itemUnitPrice',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Totais
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function itemFormTotal(): float
    {
        return
            (float) $this->itemQuantity
            * (float) $this->itemUnitPrice;
    }

    #[Computed]
    public function subtotal(): float
    {
        return collect($this->items)
            ->sum(function ($item) {

                return
                    (float) $item['quantity']
                    * (float) $item['unit_price'];
            });
    }

    #[Computed]
    public function discountValue(): float
    {
        return max(
            0,
            min(
                (float) $this->discount,
                $this->subtotal
            )
        );
    }

    #[Computed]
    public function total(): float
    {
        return max(
            0,
            $this->subtotal - $this->discountValue
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Visual
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
            'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',

            'material' =>
            'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300',

            default =>
            'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Salvar orçamento
    |--------------------------------------------------------------------------
    */

    public function save()
    {
        abort_unless($this->business, 403);

        $validated = $this->validate([
            'clientId' => [
                'required',
                'integer',
            ],

            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'validUntil' => [
                'nullable',
                'date',
            ],

            'discount' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.type' => [
                'required',
                'in:service,material,other',
            ],

            'items.*.description' => [
                'required',
                'string',
                'max:255',
            ],

            'items.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'items.*.unit' => [
                'required',
                'string',
                'max:20',
            ],

            'items.*.unit_price' => [
                'required',
                'numeric',
                'min:0',
            ],
        ], [
            'clientId.required' =>
            'Selecione um cliente.',

            'title.required' =>
            'Informe o título do orçamento.',

            'items.required' =>
            'Adicione pelo menos um item.',

            'items.min' =>
            'Adicione pelo menos um item.',
        ]);

        /*
         * Garante que o cliente pertence à empresa logada.
         */
        $client = $this->business
            ->clients()
            ->whereKey($validated['clientId'])
            ->firstOrFail();

        $quote = DB::transaction(function () use ($validated, $client) {

            /*
             * Próximo número sequencial da empresa.
             */
            $lastQuote = $this->business
                ->quotes()
                ->withTrashed()
                ->lockForUpdate()
                ->orderByDesc('number')
                ->first();

            $number =
                ($lastQuote?->number ?? 0) + 1;

            /*
             * Recalcula tudo no servidor.
             */
            $items = collect($validated['items'])
                ->map(function ($item, $index) {

                    $quantity =
                        (float) $item['quantity'];

                    $unitPrice =
                        (float) $item['unit_price'];

                    return [
                        'type' =>
                        $item['type'],

                        'description' =>
                        trim($item['description']),

                        'quantity' =>
                        $quantity,

                        'unit' =>
                        trim($item['unit']),

                        'unit_price' =>
                        $unitPrice,

                        'total' =>
                        $quantity * $unitPrice,

                        'sort_order' =>
                        $index,
                    ];
                });

            $subtotal =
                (float) $items->sum('total');

            $discount = max(
                0,
                min(
                    (float) ($validated['discount'] ?? 0),
                    $subtotal
                )
            );

            $quote = $this->business
                ->quotes()
                ->create([
                    'client_id' =>
                    $client->id,

                    'number' =>
                    $number,

                    'title' =>
                    trim($validated['title']),

                    'description' =>
                    $validated['description'] ?: null,

                    'subtotal' =>
                    $subtotal,

                    'discount' =>
                    $discount,

                    'total' =>
                    $subtotal - $discount,

                    'status' =>
                    'draft',

                    'valid_until' =>
                    $validated['validUntil'] ?: null,

                    'notes' =>
                    $validated['notes'] ?: null,
                ]);

            $quote
                ->items()
                ->createMany(
                    $items->all()
                );

            $quote
                ->events()
                ->create([
                    'type' => 'created',
                ]);

            return $quote;
        });

        session()->flash(
            'success',
            'Orçamento criado com sucesso.'
        );

        return $this->redirect(
            route('quotes.show', $quote->id),
            navigate: true
        );
    }
};
?>

<div class="mx-auto max-w-7xl space-y-6">

    {{-- ========================================================= --}}
    {{-- CABEÇALHO --}}
    {{-- ========================================================= --}}

    <div>

        <a
            href="{{ route('quotes.index') }}"
            wire:navigate
            class="
                inline-flex items-center gap-2
                text-sm font-medium
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


        <h1
            class="
                mt-3
                text-2xl font-semibold
                tracking-tight
                text-zinc-950
                dark:text-white
            ">
            Novo orçamento
        </h1>

        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
            Monte uma proposta profissional em poucos passos.
        </p>

    </div>


    {{-- ========================================================= --}}
    {{-- FORMULÁRIO --}}
    {{-- ========================================================= --}}

    <form wire:submit="save">

        <div
            class="
                grid items-start gap-6

                xl:grid-cols-[minmax(0,1fr)_340px]
            ">

            {{-- ================================================= --}}
            {{-- COLUNA PRINCIPAL --}}
            {{-- ================================================= --}}

            <div class="space-y-6">


                {{-- ================================================= --}}
                {{-- 1. DADOS --}}
                {{-- ================================================= --}}

                <section
                    class="
                        overflow-visible
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
                            px-6 py-5

                            dark:border-zinc-800
                        ">

                        <div class="flex items-center gap-3">

                            <div
                                class="
                                    flex size-8
                                    items-center justify-center
                                    rounded-full

                                    bg-emerald-100

                                    text-sm font-bold
                                    text-emerald-700

                                    dark:bg-emerald-950
                                    dark:text-emerald-300
                                ">
                                1
                            </div>


                            <div>

                                <h2 class="font-semibold text-zinc-950 dark:text-white">
                                    Dados do orçamento
                                </h2>

                                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                    Selecione o cliente e informe os dados principais.
                                </p>

                            </div>

                        </div>

                    </div>


                    <div class="grid gap-5 p-6 md:grid-cols-2">

                        {{-- ========================================= --}}
                        {{-- CLIENTE --}}
                        {{-- ========================================= --}}

                        <div class="relative md:col-span-2">

                            <div class="mb-1.5 flex items-center justify-between gap-3">

                                <label
                                    class="
                                        block
                                        text-sm font-medium
                                        text-zinc-700
                                        dark:text-zinc-300
                                    ">
                                    Cliente *
                                </label>


                                <button
                                    type="button"
                                    wire:click="openClientModal"
                                    class="
                                        text-xs font-semibold
                                        text-emerald-600

                                        hover:text-emerald-700

                                        dark:text-emerald-400
                                        dark:hover:text-emerald-300
                                    ">
                                    + Novo cliente
                                </button>

                            </div>


                            <div class="relative">

                                <svg
                                    class="
                                        pointer-events-none
                                        absolute left-3 top-1/2
                                        size-5 -translate-y-1/2

                                        text-zinc-400
                                        dark:text-zinc-500
                                    "
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2">
                                    <circle cx="11" cy="11" r="7" />

                                    <path
                                        stroke-linecap="round"
                                        d="m20 20-3.5-3.5" />
                                </svg>


                                <input
                                    type="text"

                                    wire:model.live.debounce.300ms="clientSearch"

                                    wire:focus="$set('showClientResults', true)"

                                    placeholder="Digite o nome, CPF/CNPJ ou telefone..."

                                    autocomplete="off"

                                    class="
                                        w-full rounded-lg

                                        border border-zinc-300
                                        bg-white

                                        py-2.5
                                        pl-10
                                        pr-10

                                        text-sm
                                        text-zinc-900

                                        placeholder:text-zinc-400

                                        outline-none

                                        transition

                                        focus:border-emerald-500
                                        focus:ring-2
                                        focus:ring-emerald-500/20

                                        dark:border-zinc-700
                                        dark:bg-zinc-950
                                        dark:text-zinc-100
                                        dark:placeholder:text-zinc-600
                                    ">


                                @if ($clientId || $clientSearch)

                                <button
                                    type="button"
                                    wire:click="clearClient"

                                    title="Limpar cliente"

                                    class="
                                            absolute
                                            right-2 top-1/2

                                            inline-flex size-7
                                            -translate-y-1/2
                                            items-center justify-center

                                            rounded-md

                                            text-zinc-400

                                            transition

                                            hover:bg-zinc-100
                                            hover:text-zinc-700

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
                                            d="M6 6l12 12M18 6L6 18" />
                                    </svg>
                                </button>

                                @endif

                            </div>


                            {{-- CLIENTE SELECIONADO --}}

                            @if ($clientId)

                            <div
                                class="
                                        mt-2
                                        inline-flex items-center gap-2

                                        rounded-full

                                        bg-emerald-100

                                        px-3 py-1

                                        text-xs font-semibold
                                        text-emerald-700

                                        dark:bg-emerald-950
                                        dark:text-emerald-300
                                    ">
                                <svg
                                    class="size-3.5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.5">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M5 12l4 4L19 6" />
                                </svg>

                                Cliente selecionado
                            </div>

                            @endif


                            @error('clientId')

                            <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">
                                {{ $message }}
                            </p>

                            @enderror


                            {{-- ===================================== --}}
                            {{-- RESULTADOS DA PESQUISA --}}
                            {{-- ===================================== --}}

                            @if (
                            $showClientResults
                            && ! $clientId
                            && mb_strlen(trim($clientSearch)) >= 2
                            )

                            <div
                                class="
                                        absolute z-50
                                        mt-2

                                        max-h-80
                                        w-full

                                        overflow-y-auto

                                        rounded-xl

                                        border border-zinc-200

                                        bg-white

                                        shadow-xl

                                        dark:border-zinc-700
                                        dark:bg-zinc-900
                                    ">

                                <div
                                    wire:loading
                                    wire:target="clientSearch"

                                    class="
                                            px-4 py-4

                                            text-sm
                                            text-zinc-500

                                            dark:text-zinc-400
                                        ">
                                    Pesquisando...
                                </div>


                                <div
                                    wire:loading.remove
                                    wire:target="clientSearch">

                                    @forelse ($this->clientResults as $client)

                                    <button
                                        type="button"

                                        wire:key="client-result-{{ $client->id }}"

                                        wire:click="selectClient({{ $client->id }})"

                                        class="
                                                    flex w-full
                                                    items-center
                                                    justify-between
                                                    gap-4

                                                    border-b
                                                    border-zinc-100

                                                    px-4 py-3

                                                    text-left

                                                    transition

                                                    last:border-0

                                                    hover:bg-emerald-50

                                                    dark:border-zinc-800
                                                    dark:hover:bg-emerald-950/30
                                                ">

                                        <div class="min-w-0">

                                            <p
                                                class="
                                                            truncate
                                                            font-medium

                                                            text-zinc-900
                                                            dark:text-white
                                                        ">
                                                {{ $client->name }}
                                            </p>


                                            <div
                                                class="
                                                            mt-1
                                                            flex flex-wrap
                                                            gap-3

                                                            text-xs

                                                            text-zinc-500
                                                            dark:text-zinc-400
                                                        ">

                                                @if ($client->document)

                                                <span>
                                                    {{ $client->document }}
                                                </span>

                                                @endif


                                                @if ($client->whatsapp)

                                                <span>
                                                    {{ $client->whatsapp }}
                                                </span>

                                                @elseif ($client->phone)

                                                <span>
                                                    {{ $client->phone }}
                                                </span>

                                                @endif

                                            </div>

                                        </div>


                                        <svg
                                            class="
                                                        size-4
                                                        shrink-0

                                                        text-zinc-300

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

                                    </button>


                                    @empty

                                    {{-- NENHUM CLIENTE --}}

                                    <div class="px-5 py-5 text-center">

                                        <div
                                            class="
                                                        mx-auto

                                                        flex size-10
                                                        items-center
                                                        justify-center

                                                        rounded-full

                                                        bg-zinc-100
                                                        text-zinc-400

                                                        dark:bg-zinc-800
                                                        dark:text-zinc-400
                                                    ">
                                            <svg
                                                class="size-5"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2">
                                                <circle
                                                    cx="9"
                                                    cy="8"
                                                    r="3" />

                                                <path
                                                    stroke-linecap="round"
                                                    d="M3 20c0-4 2-6 6-6 2 0 3.5.5 4.5 1.5M18 13v6M15 16h6" />
                                            </svg>
                                        </div>


                                        <p
                                            class="
                                                        mt-3
                                                        text-sm font-medium

                                                        text-zinc-700
                                                        dark:text-zinc-200
                                                    ">
                                            Nenhum cliente encontrado
                                        </p>


                                        <p
                                            class="
                                                        mt-1
                                                        text-xs

                                                        text-zinc-500
                                                        dark:text-zinc-400
                                                    ">
                                            Cadastre o cliente sem sair deste orçamento.
                                        </p>


                                        <button
                                            type="button"

                                            wire:click="openClientModal"

                                            class="
                                                        mt-4

                                                        inline-flex
                                                        items-center
                                                        justify-center
                                                        gap-2

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
                                                    ">
                                            + Cadastrar novo cliente
                                        </button>

                                    </div>

                                    @endforelse

                                </div>

                            </div>

                            @endif

                        </div>


                        {{-- ========================================= --}}
                        {{-- TÍTULO --}}
                        {{-- ========================================= --}}

                        <div>

                            <label
                                class="
                                    mb-1.5 block
                                    text-sm font-medium
                                    text-zinc-700
                                    dark:text-zinc-300
                                ">
                                Título *
                            </label>

                            <input
                                type="text"
                                wire:model="title"

                                placeholder="Ex.: Instalação de ar-condicionado"

                                class="
                                    w-full rounded-lg

                                    border border-zinc-300

                                    bg-white

                                    px-3 py-2.5

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


                            @error('title')

                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ $message }}
                            </p>

                            @enderror

                        </div>


                        {{-- VALIDADE --}}

                        <div>

                            <label
                                class="
                                    mb-1.5 block
                                    text-sm font-medium
                                    text-zinc-700
                                    dark:text-zinc-300
                                ">
                                Validade
                            </label>

                            <input
                                type="date"
                                wire:model="validUntil"

                                class="
                                    w-full rounded-lg

                                    border border-zinc-300

                                    bg-white

                                    px-3 py-2.5

                                    text-sm
                                    text-zinc-900

                                    dark:border-zinc-700
                                    dark:bg-zinc-950
                                    dark:text-white
                                ">

                        </div>


                        {{-- DESCRIÇÃO --}}

                        <div class="md:col-span-2">

                            <label
                                class="
                                    mb-1.5 block
                                    text-sm font-medium
                                    text-zinc-700
                                    dark:text-zinc-300
                                ">
                                Descrição geral
                            </label>

                            <textarea
                                wire:model="description"

                                rows="3"

                                placeholder="Descreva resumidamente o serviço ou proposta..."

                                class="
                                    w-full resize-y
                                    rounded-lg

                                    border border-zinc-300

                                    bg-white

                                    px-3 py-2.5

                                    text-sm
                                    text-zinc-900

                                    placeholder:text-zinc-400

                                    dark:border-zinc-700
                                    dark:bg-zinc-950
                                    dark:text-white
                                "></textarea>

                        </div>

                    </div>

                </section>


                {{-- ================================================= --}}
                {{-- 2. ITENS --}}
                {{-- ================================================= --}}

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
                            border-b border-zinc-200
                            px-6 py-5

                            dark:border-zinc-800
                        ">

                        <div
                            class="
                                flex flex-col gap-4

                                sm:flex-row
                                sm:items-center
                                sm:justify-between
                            ">

                            <div class="flex items-center gap-3">

                                <div
                                    class="
                                        flex size-8
                                        items-center
                                        justify-center

                                        rounded-full

                                        bg-emerald-100

                                        text-sm font-bold
                                        text-emerald-700

                                        dark:bg-emerald-950
                                        dark:text-emerald-300
                                    ">
                                    2
                                </div>


                                <div>

                                    <h2 class="font-semibold text-zinc-950 dark:text-white">
                                        Itens
                                    </h2>

                                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ count($items) }}
                                        {{ count($items) === 1 ? 'item' : 'itens' }}
                                    </p>

                                </div>

                            </div>


                            <div class="flex flex-wrap gap-2">

                                <button
                                    type="button"
                                    wire:click="newItem('service')"

                                    class="
                                        rounded-lg

                                        border border-blue-200
                                        bg-blue-50

                                        px-3 py-2

                                        text-sm font-semibold
                                        text-blue-700

                                        hover:bg-blue-100

                                        dark:border-blue-900
                                        dark:bg-blue-950/40
                                        dark:text-blue-300
                                    ">
                                    🔧 Serviço
                                </button>


                                <button
                                    type="button"
                                    wire:click="newItem('material')"

                                    class="
                                        rounded-lg

                                        border border-violet-200
                                        bg-violet-50

                                        px-3 py-2

                                        text-sm font-semibold
                                        text-violet-700

                                        hover:bg-violet-100

                                        dark:border-violet-900
                                        dark:bg-violet-950/40
                                        dark:text-violet-300
                                    ">
                                    📦 Material
                                </button>


                                <button
                                    type="button"
                                    wire:click="newItem('other')"

                                    class="
                                        rounded-lg

                                        border border-zinc-300
                                        bg-white

                                        px-3 py-2

                                        text-sm font-semibold
                                        text-zinc-700

                                        hover:bg-zinc-100

                                        dark:border-zinc-700
                                        dark:bg-zinc-900
                                        dark:text-zinc-200
                                        dark:hover:bg-zinc-800
                                    ">
                                    + Outro
                                </button>

                            </div>

                        </div>

                    </div>


                    {{-- =========================================== --}}
                    {{-- FORMULÁRIO DO ITEM --}}
                    {{-- =========================================== --}}

                    @if ($showItemForm)

                    <div
                        class="
                                border-b border-zinc-200

                                bg-zinc-50

                                p-6

                                dark:border-zinc-800
                                dark:bg-zinc-950/40
                            ">

                        <div class="mb-5 flex items-center justify-between">

                            <div class="flex items-center gap-2">

                                <span
                                    class="
                                            rounded-full

                                            px-2.5 py-1

                                            text-xs font-semibold

                                            {{ $this->typeClasses($itemType) }}
                                        ">
                                    {{ $this->typeLabel($itemType) }}
                                </span>


                                <h3 class="font-semibold text-zinc-950 dark:text-white">

                                    {{ $editingItemIndex !== null
                                            ? 'Editar item'
                                            : 'Adicionar item'
                                        }}

                                </h3>

                            </div>


                            <button
                                type="button"
                                wire:click="cancelItemForm"

                                class="
                                        flex size-8
                                        items-center
                                        justify-center

                                        rounded-lg

                                        text-zinc-400

                                        hover:bg-zinc-200
                                        hover:text-zinc-700

                                        dark:hover:bg-zinc-800
                                        dark:hover:text-white
                                    ">
                                ×
                            </button>

                        </div>


                        <div class="grid gap-4 lg:grid-cols-12">

                            <div class="lg:col-span-5">

                                <label
                                    class="
                                            mb-1.5 block
                                            text-sm font-medium
                                            text-zinc-700
                                            dark:text-zinc-300
                                        ">
                                    Descrição *
                                </label>

                                <input
                                    type="text"
                                    wire:model="itemDescription"

                                    placeholder="Ex.: Instalação do equipamento"

                                    class="
                                            w-full rounded-lg

                                            border border-zinc-300

                                            bg-white

                                            px-3 py-2.5

                                            text-sm
                                            text-zinc-900

                                            dark:border-zinc-700
                                            dark:bg-zinc-900
                                            dark:text-white
                                        ">

                                @error('itemDescription')

                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                    {{ $message }}
                                </p>

                                @enderror

                            </div>


                            <div class="lg:col-span-2">

                                <label
                                    class="
                                            mb-1.5 block
                                            text-sm font-medium
                                            text-zinc-700
                                            dark:text-zinc-300
                                        ">
                                    Quantidade
                                </label>

                                <input
                                    type="number"

                                    min="0.001"
                                    step="0.001"

                                    wire:model.live.debounce.200ms="itemQuantity"

                                    class="
                                            w-full rounded-lg

                                            border border-zinc-300

                                            bg-white

                                            px-3 py-2.5

                                            text-sm
                                            text-zinc-900

                                            dark:border-zinc-700
                                            dark:bg-zinc-900
                                            dark:text-white
                                        ">

                            </div>


                            <div class="lg:col-span-2">

                                <label
                                    class="
                                            mb-1.5 block
                                            text-sm font-medium
                                            text-zinc-700
                                            dark:text-zinc-300
                                        ">
                                    Unidade
                                </label>

                                <select
                                    wire:model="itemUnit"

                                    class="
                                            w-full rounded-lg

                                            border border-zinc-300

                                            bg-white

                                            px-3 py-2.5

                                            text-sm
                                            text-zinc-900

                                            dark:border-zinc-700
                                            dark:bg-zinc-900
                                            dark:text-white
                                        ">
                                    <option value="un">un</option>
                                    <option value="serviço">serviço</option>
                                    <option value="hora">hora</option>
                                    <option value="dia">dia</option>
                                    <option value="m">metro</option>
                                    <option value="m²">m²</option>
                                    <option value="m³">m³</option>
                                    <option value="kg">kg</option>
                                    <option value="l">litro</option>
                                    <option value="pct">pacote</option>
                                    <option value="cx">caixa</option>
                                </select>

                            </div>


                            <div class="lg:col-span-3">

                                <label
                                    class="
                                            mb-1.5 block
                                            text-sm font-medium
                                            text-zinc-700
                                            dark:text-zinc-300
                                        ">
                                    Valor unitário
                                </label>

                                <input
                                    type="number"

                                    min="0"
                                    step="0.01"

                                    wire:model.live.debounce.200ms="itemUnitPrice"

                                    class="
                                            w-full rounded-lg

                                            border border-zinc-300

                                            bg-white

                                            px-3 py-2.5

                                            text-sm
                                            text-zinc-900

                                            dark:border-zinc-700
                                            dark:bg-zinc-900
                                            dark:text-white
                                        ">

                            </div>

                        </div>


                        <div
                            class="
                                    mt-5

                                    flex flex-col
                                    gap-4

                                    border-t
                                    border-zinc-200

                                    pt-5

                                    sm:flex-row
                                    sm:items-center
                                    sm:justify-between

                                    dark:border-zinc-800
                                ">

                            <div>

                                <p
                                    class="
                                            text-xs
                                            uppercase
                                            tracking-wide

                                            text-zinc-500
                                            dark:text-zinc-400
                                        ">
                                    Total do item
                                </p>

                                <p class="mt-1 text-xl font-bold text-zinc-950 dark:text-white">
                                    R$ {{ number_format(
                                            $this->itemFormTotal,
                                            2,
                                            ',',
                                            '.'
                                        ) }}
                                </p>

                            </div>


                            <div class="flex gap-2">

                                <button
                                    type="button"
                                    wire:click="cancelItemForm"

                                    class="
                                            rounded-lg

                                            border border-zinc-300
                                            bg-white

                                            px-4 py-2.5

                                            text-sm font-medium
                                            text-zinc-700

                                            hover:bg-zinc-100

                                            dark:border-zinc-700
                                            dark:bg-zinc-900
                                            dark:text-zinc-200
                                        ">
                                    Cancelar
                                </button>


                                <button
                                    type="button"
                                    wire:click="saveItem"

                                    class="
                                            rounded-lg

                                            bg-emerald-600

                                            px-4 py-2.5

                                            text-sm font-semibold
                                            text-white

                                            hover:bg-emerald-700

                                            dark:bg-emerald-500
                                            dark:text-zinc-950
                                        ">

                                    {{ $editingItemIndex !== null
                                            ? 'Salvar alterações'
                                            : 'Adicionar ao orçamento'
                                        }}

                                </button>

                            </div>

                        </div>

                    </div>

                    @endif


                    {{-- =========================================== --}}
                    {{-- LISTA DOS ITENS --}}
                    {{-- =========================================== --}}

                    @if (count($items))

                    <div class="divide-y divide-zinc-100 dark:divide-zinc-800">

                        @foreach ($items as $index => $item)

                        <div
                            wire:key="quote-item-{{ $index }}"

                            class="
                                        flex flex-col gap-4

                                        px-6 py-4

                                        sm:flex-row
                                        sm:items-center
                                        sm:justify-between

                                        hover:bg-zinc-50

                                        dark:hover:bg-zinc-800/40
                                    ">

                            <div class="min-w-0">

                                <div class="flex flex-wrap items-center gap-2">

                                    <p
                                        class="
                                                    font-semibold

                                                    text-zinc-900
                                                    dark:text-white
                                                ">
                                        {{ $item['description'] }}
                                    </p>


                                    <span
                                        class="
                                                    rounded-full

                                                    px-2 py-0.5

                                                    text-[11px]
                                                    font-semibold

                                                    {{ $this->typeClasses($item['type']) }}
                                                ">
                                        {{ $this->typeLabel($item['type']) }}
                                    </span>

                                </div>


                                <p
                                    class="
                                                mt-1

                                                text-sm

                                                text-zinc-500
                                                dark:text-zinc-400
                                            ">
                                    {{ $item['quantity'] }}

                                    {{ $item['unit'] }}

                                    ×

                                    R$ {{ number_format(
                                                (float) $item['unit_price'],
                                                2,
                                                ',',
                                                '.'
                                            ) }}
                                </p>

                            </div>


                            <div class="flex items-center gap-4">

                                <p
                                    class="
                                                font-bold

                                                text-zinc-950
                                                dark:text-white
                                            ">
                                    R$ {{ number_format(
                                                (float) $item['quantity']
                                                * (float) $item['unit_price'],
                                                2,
                                                ',',
                                                '.'
                                            ) }}
                                </p>


                                <button
                                    type="button"

                                    wire:click="editItem({{ $index }})"

                                    class="
                                                text-sm font-medium

                                                text-zinc-500

                                                hover:text-zinc-950

                                                dark:text-zinc-400
                                                dark:hover:text-white
                                            ">
                                    Editar
                                </button>


                                <button
                                    type="button"

                                    wire:click="removeItem({{ $index }})"

                                    class="
                                                text-sm font-medium

                                                text-red-500

                                                hover:text-red-700

                                                dark:text-red-400
                                            ">
                                    Remover
                                </button>

                            </div>

                        </div>

                        @endforeach

                    </div>


                    @else

                    <div class="px-6 py-12 text-center">

                        <p class="text-sm font-medium text-zinc-600 dark:text-zinc-300">
                            Nenhum item adicionado.
                        </p>

                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            Escolha Serviço, Material ou Outro para começar.
                        </p>

                    </div>

                    @endif


                    @error('items')

                    <div
                        class="
                                border-t border-red-100

                                bg-red-50

                                px-6 py-3

                                text-sm
                                text-red-700

                                dark:border-red-950
                                dark:bg-red-950/30
                                dark:text-red-300
                            ">
                        {{ $message }}
                    </div>

                    @enderror

                </section>


                {{-- ================================================= --}}
                {{-- 3. CONDIÇÕES --}}
                {{-- ================================================= --}}

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

                            px-6 py-5

                            dark:border-zinc-800
                        ">

                        <div class="flex items-center gap-3">

                            <div
                                class="
                                    flex size-8
                                    items-center justify-center

                                    rounded-full

                                    bg-emerald-100

                                    text-sm font-bold
                                    text-emerald-700

                                    dark:bg-emerald-950
                                    dark:text-emerald-300
                                ">
                                3
                            </div>


                            <div>

                                <h2 class="font-semibold text-zinc-950 dark:text-white">
                                    Condições e observações
                                </h2>

                                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                    Pagamento, prazo, garantia e demais informações.
                                </p>

                            </div>

                        </div>

                    </div>


                    <div class="p-6">

                        <textarea
                            wire:model="notes"

                            rows="4"

                            placeholder="Ex.: Pagamento de 50% na aprovação e 50% na conclusão..."

                            class="
                                w-full resize-y
                                rounded-lg

                                border border-zinc-300

                                bg-white

                                px-3 py-2.5

                                text-sm
                                text-zinc-900

                                placeholder:text-zinc-400

                                dark:border-zinc-700
                                dark:bg-zinc-950
                                dark:text-white
                            "></textarea>

                    </div>

                </section>

            </div>


            {{-- ================================================= --}}
            {{-- RESUMO LATERAL --}}
            {{-- ================================================= --}}

            <aside
                class="
                    rounded-2xl

                    border border-zinc-200

                    bg-white

                    p-5

                    shadow-sm

                    xl:sticky
                    xl:top-6

                    dark:border-zinc-800
                    dark:bg-zinc-900
                ">

                <h2 class="font-semibold text-zinc-950 dark:text-white">
                    Resumo
                </h2>

                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">

                    {{ count($items) }}

                    {{ count($items) === 1 ? 'item' : 'itens' }}

                </p>


                <div class="mt-6 space-y-4">

                    <div class="flex justify-between text-sm">

                        <span class="text-zinc-500 dark:text-zinc-400">
                            Subtotal
                        </span>

                        <span class="font-medium text-zinc-900 dark:text-zinc-200">

                            R$ {{ number_format(
                                $this->subtotal,
                                2,
                                ',',
                                '.'
                            ) }}

                        </span>

                    </div>


                    <div>

                        <label
                            class="
                                mb-1.5 block

                                text-sm

                                text-zinc-500
                                dark:text-zinc-400
                            ">
                            Desconto
                        </label>


                        <div class="relative">

                            <span
                                class="
                                    absolute left-3 top-1/2

                                    -translate-y-1/2

                                    text-sm
                                    text-zinc-400
                                ">
                                R$
                            </span>


                            <input
                                type="number"

                                min="0"
                                step="0.01"

                                wire:model.live.debounce.200ms="discount"

                                class="
                                    w-full rounded-lg

                                    border border-zinc-300

                                    bg-white

                                    py-2
                                    pl-9
                                    pr-3

                                    text-right
                                    text-sm
                                    text-zinc-900

                                    dark:border-zinc-700
                                    dark:bg-zinc-950
                                    dark:text-white
                                ">

                        </div>

                    </div>


                    <div
                        class="
                            border-t border-zinc-200

                            pt-5

                            dark:border-zinc-800
                        ">

                        <p class="text-sm text-zinc-500 dark:text-zinc-400">
                            Total
                        </p>

                        <p
                            class="
                                mt-1

                                text-3xl
                                font-bold
                                tracking-tight

                                text-zinc-950
                                dark:text-zinc-100
                            ">
                            R$ {{ number_format(
                                $this->total,
                                2,
                                ',',
                                '.'
                            ) }}
                        </p>

                    </div>

                </div>


                <div class="mt-6 space-y-2">

                    <button
                        type="submit"

                        wire:loading.attr="disabled"

                        class="
                            inline-flex w-full
                            items-center justify-center

                            rounded-lg

                            bg-emerald-600

                            px-4 py-3

                            text-sm font-semibold
                            text-white

                            shadow-sm

                            transition

                            hover:bg-emerald-700

                            disabled:cursor-not-allowed
                            disabled:opacity-60

                            dark:bg-emerald-500
                            dark:text-zinc-950
                            dark:hover:bg-emerald-400
                        ">

                        <span
                            wire:loading.remove
                            wire:target="save">
                            Criar orçamento
                        </span>

                        <span
                            wire:loading
                            wire:target="save">
                            Criando...
                        </span>

                    </button>


                    <a
                        href="{{ route('quotes.index') }}"
                        wire:navigate

                        class="
                            inline-flex w-full
                            items-center justify-center

                            rounded-lg

                            border border-zinc-300

                            bg-white

                            px-4 py-2.5

                            text-sm font-medium
                            text-zinc-700

                            hover:bg-zinc-100

                            dark:border-zinc-700
                            dark:bg-zinc-900
                            dark:text-zinc-200
                            dark:hover:bg-zinc-800
                        ">
                        Cancelar
                    </a>

                </div>

            </aside>

        </div>

    </form>


    {{-- ========================================================= --}}
    {{-- MODAL - CADASTRO RÁPIDO DE CLIENTE --}}
    {{-- ========================================================= --}}

    @if ($showClientModal)

    <div
        class="
                fixed inset-0 z-[100]

                flex items-center
                justify-center

                bg-zinc-950/60

                p-4

                backdrop-blur-[2px]
            ">

        {{-- Clique fora fecha --}}

        <button
            type="button"
            wire:click="closeClientModal"
            class="absolute inset-0 cursor-default"
            aria-label="Fechar"></button>


        <div
            class="
                    relative z-10

                    w-full
                    max-w-lg

                    overflow-hidden

                    rounded-2xl

                    border border-zinc-200

                    bg-white

                    shadow-2xl

                    dark:border-zinc-700
                    dark:bg-zinc-900
                ">

            {{-- CABEÇALHO --}}

            <div
                class="
                        flex items-start
                        justify-between
                        gap-4

                        border-b border-zinc-200

                        px-6 py-5

                        dark:border-zinc-800
                    ">

                <div>

                    <div class="flex items-center gap-3">

                        <div
                            class="
                                    flex size-9
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
                                <circle
                                    cx="9"
                                    cy="8"
                                    r="3" />

                                <path
                                    stroke-linecap="round"
                                    d="M3 20c0-4 2-6 6-6 2 0 3.5.5 4.5 1.5M18 13v6M15 16h6" />
                            </svg>
                        </div>


                        <div>

                            <h2 class="font-semibold text-zinc-950 dark:text-white">
                                Novo cliente
                            </h2>

                            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                                Cadastro rápido para este orçamento.
                            </p>

                        </div>

                    </div>

                </div>


                <button
                    type="button"

                    wire:click="closeClientModal"

                    class="
                            flex size-8
                            shrink-0
                            items-center
                            justify-center

                            rounded-lg

                            text-zinc-400

                            hover:bg-zinc-100
                            hover:text-zinc-700

                            dark:hover:bg-zinc-800
                            dark:hover:text-white
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
                </button>

            </div>


            {{-- CAMPOS --}}

            <div class="space-y-4 px-6 py-5">

                {{-- NOME --}}

                <div>

                    <label
                        class="
                                mb-1.5 block

                                text-sm font-medium

                                text-zinc-700
                                dark:text-zinc-300
                            ">
                        Nome *
                    </label>

                    <input
                        type="text"

                        wire:model="newClientName"

                        placeholder="Nome do cliente"

                        class="
                                w-full rounded-lg

                                border border-zinc-300

                                bg-white

                                px-3 py-2.5

                                text-sm
                                text-zinc-900

                                outline-none

                                focus:border-emerald-500
                                focus:ring-2
                                focus:ring-emerald-500/20

                                dark:border-zinc-700
                                dark:bg-zinc-950
                                dark:text-white
                            ">


                    @error('newClientName')

                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ $message }}
                    </p>

                    @enderror

                </div>


                {{-- WHATSAPP --}}

                <div>

                    <label
                        class="
                                mb-1.5 block

                                text-sm font-medium

                                text-zinc-700
                                dark:text-zinc-300
                            ">
                        WhatsApp
                    </label>

                    <input
                        type="text"

                        wire:model="newClientWhatsapp"

                        placeholder="(33) 99999-9999"

                        class="
                                w-full rounded-lg

                                border border-zinc-300

                                bg-white

                                px-3 py-2.5

                                text-sm
                                text-zinc-900

                                dark:border-zinc-700
                                dark:bg-zinc-950
                                dark:text-white
                            ">

                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        Facilita o envio do orçamento pelo WhatsApp.
                    </p>

                </div>


                <div class="grid gap-4 sm:grid-cols-2">

                    {{-- CPF/CNPJ --}}

                    <div>

                        <label
                            class="
                                    mb-1.5 block

                                    text-sm font-medium

                                    text-zinc-700
                                    dark:text-zinc-300
                                ">
                            CPF/CNPJ
                        </label>

                        <input
                            type="text"

                            wire:model="newClientDocument"

                            placeholder="Opcional"

                            class="
                                    w-full rounded-lg

                                    border border-zinc-300

                                    bg-white

                                    px-3 py-2.5

                                    text-sm
                                    text-zinc-900

                                    dark:border-zinc-700
                                    dark:bg-zinc-950
                                    dark:text-white
                                ">


                        @error('newClientDocument')

                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                            {{ $message }}
                        </p>

                        @enderror

                    </div>


                    {{-- E-MAIL --}}

                    <div>

                        <label
                            class="
                                    mb-1.5 block

                                    text-sm font-medium

                                    text-zinc-700
                                    dark:text-zinc-300
                                ">
                            E-mail
                        </label>

                        <input
                            type="email"

                            wire:model="newClientEmail"

                            placeholder="Opcional"

                            class="
                                    w-full rounded-lg

                                    border border-zinc-300

                                    bg-white

                                    px-3 py-2.5

                                    text-sm
                                    text-zinc-900

                                    dark:border-zinc-700
                                    dark:bg-zinc-950
                                    dark:text-white
                                ">


                        @error('newClientEmail')

                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                            {{ $message }}
                        </p>

                        @enderror

                    </div>

                </div>

            </div>


            {{-- RODAPÉ --}}

            <div
                class="
                        flex flex-col-reverse
                        gap-2

                        border-t border-zinc-200

                        bg-zinc-50

                        px-6 py-4

                        sm:flex-row
                        sm:justify-end

                        dark:border-zinc-800
                        dark:bg-zinc-950/40
                    ">

                <button
                    type="button"

                    wire:click="closeClientModal"

                    class="
                            rounded-lg

                            border border-zinc-300

                            bg-white

                            px-4 py-2.5

                            text-sm font-semibold
                            text-zinc-700

                            hover:bg-zinc-100

                            dark:border-zinc-700
                            dark:bg-zinc-900
                            dark:text-zinc-200
                            dark:hover:bg-zinc-800
                        ">
                    Cancelar
                </button>


                <button
                    type="button"

                    wire:click="saveNewClient"

                    wire:loading.attr="disabled"
                    wire:target="saveNewClient"

                    class="
                            inline-flex
                            items-center
                            justify-center

                            rounded-lg

                            bg-emerald-600

                            px-4 py-2.5

                            text-sm font-semibold
                            text-white

                            shadow-sm

                            transition

                            hover:bg-emerald-700

                            disabled:cursor-not-allowed
                            disabled:opacity-60

                            dark:bg-emerald-500
                            dark:text-zinc-950
                            dark:hover:bg-emerald-400
                        ">

                    <span
                        wire:loading.remove
                        wire:target="saveNewClient">
                        Cadastrar e selecionar
                    </span>

                    <span
                        wire:loading
                        wire:target="saveNewClient">
                        Cadastrando...
                    </span>

                </button>

            </div>

        </div>

    </div>

    @endif

</div>