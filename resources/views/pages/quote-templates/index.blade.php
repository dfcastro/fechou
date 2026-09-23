<?php

use App\Models\QuoteTemplate;
use App\Models\Quote;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Modelos de proposta | Fechou')]
class extends Component
{
    public bool $showForm = false;

    public ?int $editingTemplateId = null;
    public ?int $deletingTemplateId = null;

    public string $sourceQuoteNumber = '';

    public string $name = '';
    public string $title = '';
    public string $description = '';
    public string $validityDays = '7';
    public string $discount = '0';
    public string $notes = '';

    public array $items = [];

    public ?int $editingItemIndex = null;

    public string $itemType = 'service';
    public string $itemDescription = '';
    public string $itemQuantity = '1';
    public string $itemUnit = 'serviço';
    public string $itemUnitPrice = '';


    #[Computed]
    public function business()
    {
        return Auth::user()?->business;
    }


    #[Computed]
    public function templates()
    {
        if (! $this->business) {
            return collect();
        }

        return QuoteTemplate::query()
            ->where(
                'business_id',
                $this->business->id
            )
            ->withCount('items')
            ->orderBy('name')
            ->get();
    }


    public function mount(): void
    {
        /*
         * PROPOSTA INFORMADA PELA URL
         *
         * /modelos?proposta=15
         *
         * Apenas preenche o editor.
         * O modelo só é criado ao salvar.
         */
        $quoteId =
            request()->integer(
                'proposta'
            );

        if ($quoteId <= 0) {
            return;
        }

        abort_unless(
            $this->business,
            403
        );

        $quote = Quote::query()
            ->where(
                'business_id',
                $this->business->id
            )
            ->with([
                'items' => fn ($query) =>
                    $query->orderBy(
                        'sort_order'
                    ),
            ])
            ->findOrFail(
                $quoteId
            );

        $this->editingTemplateId = null;
        $this->deletingTemplateId = null;

        $this->name =
            $quote->title;

        $this->title =
            $quote->title;

        $this->description =
            $quote->description ?? '';

        $this->discount =
            (string) $quote->discount;

        $this->notes =
            $quote->notes ?? '';

        if ($quote->valid_until) {

            $days = now()
                ->startOfDay()
                ->diffInDays(
                    $quote->valid_until
                        ->copy()
                        ->startOfDay(),
                    false
                );

            $this->validityDays =
                (string) max(
                    1,
                    (int) $days
                );

        } else {

            $this->validityDays = '7';
        }

        $this->items = $quote
            ->items
            ->map(
                fn ($item) => [
                    'type' =>
                        $item->type,

                    'description' =>
                        $item->description,

                    'quantity' =>
                        (float) $item->quantity,

                    'unit' =>
                        $item->unit,

                    'unit_price' =>
                        (float) $item->unit_price,
                ]
            )
            ->values()
            ->all();

        $this->sourceQuoteNumber =
            str_pad(
                (string) $quote->number,
                4,
                '0',
                STR_PAD_LEFT
            );

        $this->showForm = true;

        $this->resetItemForm();

        $this->resetValidation();
    }


    public function newTemplate(): void
    {
        $this->resetEditor();

        $this->sourceQuoteNumber = '';

        $this->showForm = true;
    }


    public function editTemplate(
        int $templateId
    ): void {

        abort_unless(
            $this->business,
            403
        );

        $template = QuoteTemplate::query()
            ->where(
                'business_id',
                $this->business->id
            )
            ->with('items')
            ->findOrFail(
                $templateId
            );


        $this->editingTemplateId =
            $template->id;

        $this->name =
            $template->name;

        $this->title =
            $template->title;

        $this->description =
            $template->description ?? '';

        $this->validityDays =
            (string) $template->validity_days;

        $this->discount =
            (string) $template->discount;

        $this->notes =
            $template->notes ?? '';

        $this->items = $template
            ->items
            ->map(
                fn ($item) => [
                    'type' =>
                        $item->type,

                    'description' =>
                        $item->description,

                    'quantity' =>
                        (float) $item->quantity,

                    'unit' =>
                        $item->unit,

                    'unit_price' =>
                        (float) $item->unit_price,
                ]
            )
            ->values()
            ->all();


        $this->showForm = true;

        $this->resetItemForm();

        $this->resetValidation();
    }


    public function cancelEditor(): void
    {
        $this->resetEditor();
    }


    private function resetEditor(): void
    {
        $this->showForm = false;

        $this->editingTemplateId = null;

        $this->name = '';
        $this->title = '';
        $this->description = '';
        $this->validityDays = '7';
        $this->discount = '0';
        $this->notes = '';

        $this->items = [];

        $this->resetItemForm();

        $this->resetValidation();
    }


    public function updatedItemType(): void
    {
        $this->itemUnit = match (
            $this->itemType
        ) {
            'service' => 'serviço',
            'material' => 'un',
            default => 'un',
        };
    }


    public function itemUnitOptions(): array
    {
        return match ($this->itemType) {
            'service' => [
                'serviço' => 'Serviço',
                'hora' => 'Hora',
                'dia' => 'Dia',
                'un' => 'Unidade',
                'm' => 'Metro',
                'm²' => 'Metro quadrado',
                'm³' => 'Metro cúbico',
            ],

            'material' => [
                'un' => 'Unidade',
                'm' => 'Metro',
                'm²' => 'Metro quadrado',
                'm³' => 'Metro cúbico',
                'kg' => 'Quilograma',
                'l' => 'Litro',
                'pct' => 'Pacote',
                'cx' => 'Caixa',
            ],

            default => [
                'un' => 'Unidade',
                'serviço' => 'Serviço',
                'hora' => 'Hora',
                'dia' => 'Dia',
                'm' => 'Metro',
                'm²' => 'Metro quadrado',
                'm³' => 'Metro cúbico',
                'kg' => 'Quilograma',
                'l' => 'Litro',
                'pct' => 'Pacote',
                'cx' => 'Caixa',
            ],
        };
    }


    public function saveItem(): void
    {
        $allowedUnits = array_keys(
            $this->itemUnitOptions()
        );

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
                'in:' . implode(
                    ',',
                    $allowedUnits
                ),
            ],

            'itemUnitPrice' => [
                'required',
                'numeric',
                'min:0',
            ],
        ]);


        $item = [
            'type' =>
                $validated['itemType'],

            'description' =>
                trim(
                    $validated[
                        'itemDescription'
                    ]
                ),

            'quantity' =>
                (float) $validated[
                    'itemQuantity'
                ],

            'unit' =>
                $validated['itemUnit'],

            'unit_price' =>
                (float) $validated[
                    'itemUnitPrice'
                ],
        ];


        if (
            $this->editingItemIndex
            !== null
        ) {
            $this->items[
                $this->editingItemIndex
            ] = $item;
        } else {
            $this->items[] = $item;
        }


        $this->resetItemForm();

        $this->resetValidation([
            'items',
        ]);
    }


    public function editItem(int $index): void
    {
        if (! isset(
            $this->items[$index]
        )) {
            return;
        }

        $item =
            $this->items[$index];

        $this->editingItemIndex =
            $index;

        $this->itemType =
            $item['type'];

        $this->itemDescription =
            $item['description'];

        $this->itemQuantity =
            (string) $item['quantity'];

        $this->itemUnit =
            $item['unit'];

        $this->itemUnitPrice =
            (string) $item['unit_price'];
    }


    public function removeItem(
        int $index
    ): void {

        if (! isset(
            $this->items[$index]
        )) {
            return;
        }

        unset(
            $this->items[$index]
        );

        $this->items =
            array_values(
                $this->items
            );

        if (
            $this->editingItemIndex
            === $index
        ) {
            $this->resetItemForm();
        }
    }


    public function cancelItemEdit(): void
    {
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
    }


    public function saveTemplate(): void
    {
        abort_unless(
            $this->business,
            403
        );

        $validated =
            $this->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
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

                'validityDays' => [
                    'required',
                    'integer',
                    'min:1',
                    'max:365',
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
                'name.required' =>
                    'Informe um nome para o modelo.',

                'title.required' =>
                    'Informe o título da proposta.',

                'items.required' =>
                    'Adicione pelo menos um item.',

                'items.min' =>
                    'Adicione pelo menos um item.',
            ]);


        DB::transaction(
            function () use ($validated) {

                if (
                    $this->editingTemplateId
                ) {
                    $template =
                        QuoteTemplate::query()
                            ->where(
                                'business_id',
                                $this->business->id
                            )
                            ->findOrFail(
                                $this
                                    ->editingTemplateId
                            );
                } else {
                    $template =
                        new QuoteTemplate();

                    $template->business_id =
                        $this->business->id;
                }


                $template->fill([
                    'name' =>
                        trim(
                            $validated['name']
                        ),

                    'title' =>
                        trim(
                            $validated['title']
                        ),

                    'description' =>
                        filled(
                            $validated[
                                'description'
                            ] ?? null
                        )
                            ? trim(
                                $validated[
                                    'description'
                                ]
                            )
                            : null,

                    'validity_days' =>
                        (int) $validated[
                            'validityDays'
                        ],

                    'discount' =>
                        (float) (
                            $validated[
                                'discount'
                            ]
                            ?? 0
                        ),

                    'notes' =>
                        filled(
                            $validated[
                                'notes'
                            ] ?? null
                        )
                            ? trim(
                                $validated[
                                    'notes'
                                ]
                            )
                            : null,
                ]);


                $template->save();


                $template
                    ->items()
                    ->delete();


                $items = collect(
                    $validated['items']
                )
                    ->values()
                    ->map(
                        function (
                            $item,
                            $index
                        ) {
                            return [
                                'type' =>
                                    $item['type'],

                                'description' =>
                                    trim(
                                        $item[
                                            'description'
                                        ]
                                    ),

                                'quantity' =>
                                    (float) $item[
                                        'quantity'
                                    ],

                                'unit' =>
                                    $item['unit'],

                                'unit_price' =>
                                    (float) $item[
                                        'unit_price'
                                    ],

                                'sort_order' =>
                                    $index,
                            ];
                        }
                    );


                $template
                    ->items()
                    ->createMany(
                        $items->all()
                    );
            }
        );


        unset(
            $this->templates
        );

        session()->flash(
            'success',
            $this->editingTemplateId
                ? 'Modelo atualizado com sucesso.'
                : 'Modelo criado com sucesso.'
        );


        $this->resetEditor();
    }


    public function askDelete(
        int $templateId
    ): void {

        abort_unless(
            $this->business,
            403
        );

        QuoteTemplate::query()
            ->where(
                'business_id',
                $this->business->id
            )
            ->findOrFail(
                $templateId
            );

        $this->deletingTemplateId =
            $templateId;
    }


    public function cancelDelete(): void
    {
        $this->deletingTemplateId =
            null;
    }


    public function deleteTemplate(): void
    {
        abort_unless(
            $this->business,
            403
        );

        if (
            ! $this->deletingTemplateId
        ) {
            return;
        }

        $template =
            QuoteTemplate::query()
                ->where(
                    'business_id',
                    $this->business->id
                )
                ->findOrFail(
                    $this
                        ->deletingTemplateId
                );


        $template->delete();


        $this->deletingTemplateId =
            null;

        unset(
            $this->templates
        );


        session()->flash(
            'success',
            'Modelo excluído.'
        );
    }


    public function typeLabel(
        string $type
    ): string {

        return match ($type) {
            'service' => 'Serviço',
            'material' => 'Material',
            default => 'Outro',
        };
    }
};
?>

<div class="mx-auto w-full max-w-6xl space-y-6">

    <div
        class="
            flex flex-col gap-4

            sm:flex-row
            sm:items-start
            sm:justify-between
        "
    >

        <div>

            <a
                href="{{ route('quotes.create') }}"
                wire:navigate
                class="
                    text-sm font-medium
                    text-zinc-500
                    hover:text-zinc-900

                    dark:text-zinc-400
                    dark:hover:text-white
                "
            >
                ← Voltar para nova proposta
            </a>

            <h1
                class="
                    mt-3
                    text-2xl font-semibold
                    tracking-tight
                    text-zinc-950
                    dark:text-white
                "
            >
                Modelos de proposta
            </h1>

            <p
                class="
                    mt-1
                    text-sm
                    text-zinc-500
                    dark:text-zinc-400
                "
            >
                Reutilize textos, serviços,
                materiais e valores frequentes.
            </p>

        </div>


        @if (! $showForm)

            <button
                type="button"
                wire:click="newTemplate"
                class="
                    rounded-lg
                    bg-emerald-600
                    px-4 py-2.5
                    text-sm font-semibold
                    text-white
                    hover:bg-emerald-700
                "
            >
                Novo modelo
            </button>

        @endif

    </div>


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
            "
        >
            {{ session('success') }}
        </div>

    @endif


    @if ($showForm)

        <form
            wire:submit="saveTemplate"
            class="
                rounded-2xl
                border border-zinc-200
                bg-white
                shadow-sm

                dark:border-zinc-800
                dark:bg-zinc-900
            "
        >

            <div
                class="
                    border-b border-zinc-200
                    px-5 py-4

                    dark:border-zinc-800
                "
            >

                <h2
                    class="
                        font-semibold
                        text-zinc-950
                        dark:text-white
                    "
                >
                    {{
                        $editingTemplateId
                            ? 'Editar modelo'
                            : 'Novo modelo'
                    }}
                </h2>

                <p
                    class="
                        mt-1 text-sm
                        text-zinc-500
                        dark:text-zinc-400
                    "
                >
                    O cliente não faz parte do modelo.
                    Ele será escolhido ao criar cada proposta.
                </p>


                @if ($sourceQuoteNumber)

                    <div
                        data-source-quote-template
                        class="
                            mt-3
                            inline-flex
                            items-center
                            gap-2

                            rounded-lg
                            border border-blue-200
                            border-l-4 border-l-blue-500
                            bg-blue-50/70

                            px-3 py-2

                            text-xs font-medium
                            text-zinc-700

                            dark:border-blue-950
                            dark:border-l-blue-500
                            dark:bg-blue-950/20
                            dark:text-zinc-300
                        "
                    >
                        <span
                            class="
                                rounded-full
                                bg-blue-100
                                px-2 py-0.5
                                text-[10px] font-bold
                                tracking-wide
                                text-blue-700

                                dark:bg-blue-950
                                dark:text-blue-300
                            "
                        >
                            ORIGEM
                        </span>

                        <span>
                            Baseado na proposta

                            <strong
                                class="
                                    font-semibold
                                    text-zinc-950
                                    dark:text-white
                                "
                            >
                                #{{ $sourceQuoteNumber }}
                            </strong>

                            · Revise e salve o modelo.
                        </span>
                    </div>

                @endif

            </div>


            <div class="space-y-5 p-5">

                <div
                    class="
                        grid gap-4
                        md:grid-cols-2
                    "
                >

                    <div>

                        <label class="mb-1.5 block text-sm font-medium">
                            Nome do modelo
                        </label>

                        <input
                            type="text"
                            wire:model="name"
                            placeholder="Ex.: Manutenção preventiva"
                            class="
                                w-full rounded-lg
                                border border-zinc-300
                                bg-white px-3 py-2.5
                                text-sm

                                dark:border-zinc-700
                                dark:bg-zinc-950
                                dark:text-white
                            "
                        />

                        @error('name')
                            <p class="mt-1 text-xs text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                    </div>


                    <div>

                        <label class="mb-1.5 block text-sm font-medium">
                            Título da proposta
                        </label>

                        <input
                            type="text"
                            wire:model="title"
                            placeholder="Ex.: Serviço de manutenção preventiva"
                            class="
                                w-full rounded-lg
                                border border-zinc-300
                                bg-white px-3 py-2.5
                                text-sm

                                dark:border-zinc-700
                                dark:bg-zinc-950
                                dark:text-white
                            "
                        />

                        @error('title')
                            <p class="mt-1 text-xs text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                    </div>


                    <div>

                        <label class="mb-1.5 block text-sm font-medium">
                            Validade
                        </label>

                        <div class="flex items-center gap-2">

                            <input
                                type="number"
                                min="1"
                                max="365"
                                wire:model="validityDays"
                                class="
                                    w-28 rounded-lg
                                    border border-zinc-300
                                    bg-white px-3 py-2.5
                                    text-sm

                                    dark:border-zinc-700
                                    dark:bg-zinc-950
                                    dark:text-white
                                "
                            />

                            <span class="text-sm text-zinc-500">
                                dias após criar a proposta
                            </span>

                        </div>

                    </div>


                    <div>

                        <label class="mb-1.5 block text-sm font-medium">
                            Desconto padrão
                        </label>

                        <input
                            type="number"
                            min="0"
                            step="0.01"
                            wire:model="discount"
                            class="
                                w-full rounded-lg
                                border border-zinc-300
                                bg-white px-3 py-2.5
                                text-sm

                                dark:border-zinc-700
                                dark:bg-zinc-950
                                dark:text-white
                            "
                        />

                    </div>


                    <div class="md:col-span-2">

                        <label class="mb-1.5 block text-sm font-medium">
                            Descrição
                        </label>

                        <textarea
                            rows="3"
                            wire:model="description"
                            class="
                                w-full resize-y
                                rounded-lg
                                border border-zinc-300
                                bg-white px-3 py-2.5
                                text-sm

                                dark:border-zinc-700
                                dark:bg-zinc-950
                                dark:text-white
                            "
                        ></textarea>

                    </div>


                    <div class="md:col-span-2">

                        <label class="mb-1.5 block text-sm font-medium">
                            Observações
                        </label>

                        <textarea
                            rows="3"
                            wire:model="notes"
                            class="
                                w-full resize-y
                                rounded-lg
                                border border-zinc-300
                                bg-white px-3 py-2.5
                                text-sm

                                dark:border-zinc-700
                                dark:bg-zinc-950
                                dark:text-white
                            "
                        ></textarea>

                    </div>

                </div>


                <div
                    class="
                        rounded-xl
                        border border-zinc-200
                        p-4

                        dark:border-zinc-800
                    "
                >

                    <div
                        class="
                            flex items-center
                            justify-between gap-3
                        "
                    >

                        <div>

                            <h3 class="font-semibold text-zinc-950 dark:text-white">
                                Itens do modelo
                            </h3>

                            <p class="mt-1 text-xs text-zinc-500">
                                Serviços, materiais e outros itens recorrentes.
                            </p>

                        </div>

                        <span
                            class="
                                rounded-full
                                bg-zinc-100
                                px-2.5 py-1
                                text-xs font-semibold
                                text-zinc-600

                                dark:bg-zinc-800
                                dark:text-zinc-300
                            "
                        >
                            {{ count($items) }}
                            {{ count($items) === 1 ? 'item' : 'itens' }}
                        </span>

                    </div>


                    @if ($items)

                        <div class="mt-4 space-y-2">

                            @foreach ($items as $index => $item)

                                <div
                                    wire:key="template-item-{{ $index }}"
                                    class="
                                        flex flex-col gap-2

                                        rounded-lg
                                        bg-zinc-50
                                        px-3 py-2.5

                                        sm:flex-row
                                        sm:items-center
                                        sm:justify-between

                                        dark:bg-zinc-950/50
                                    "
                                >

                                    <div class="min-w-0">

                                        <div class="flex flex-wrap items-center gap-2">

                                            <span
                                                class="
                                                    rounded-full
                                                    bg-zinc-200
                                                    px-2 py-0.5
                                                    text-[10px] font-semibold
                                                    text-zinc-600

                                                    dark:bg-zinc-800
                                                    dark:text-zinc-300
                                                "
                                            >
                                                {{ $this->typeLabel($item['type']) }}
                                            </span>

                                            <span class="font-medium text-zinc-900 dark:text-white">
                                                {{ $item['description'] }}
                                            </span>

                                        </div>

                                        <p class="mt-1 text-xs text-zinc-500">
                                            {{ $item['quantity'] }}
                                            {{ $item['unit'] }}
                                            ×
                                            R$
                                            {{
                                                number_format(
                                                    $item['unit_price'],
                                                    2,
                                                    ',',
                                                    '.'
                                                )
                                            }}
                                        </p>

                                    </div>


                                    <div class="flex gap-2">

                                        <button
                                            type="button"
                                            wire:click="editItem({{ $index }})"
                                            class="
                                                text-xs font-semibold
                                                text-blue-600
                                                hover:text-blue-700
                                            "
                                        >
                                            Editar
                                        </button>

                                        <button
                                            type="button"
                                            wire:click="removeItem({{ $index }})"
                                            class="
                                                text-xs font-semibold
                                                text-red-600
                                                hover:text-red-700
                                            "
                                        >
                                            Remover
                                        </button>

                                    </div>

                                </div>

                            @endforeach

                        </div>

                    @endif


                    <div
                        class="
                            mt-4
                            grid gap-3

                            md:grid-cols-2
                            xl:grid-cols-5
                        "
                    >

                        <select
                            wire:model.live="itemType"
                            class="
                                rounded-lg
                                border border-zinc-300
                                bg-white px-3 py-2
                                text-sm

                                dark:border-zinc-700
                                dark:bg-zinc-950
                                dark:text-white
                            "
                        >
                            <option value="service">
                                Serviço
                            </option>
                            <option value="material">
                                Material
                            </option>
                            <option value="other">
                                Outro
                            </option>
                        </select>


                        <input
                            type="text"
                            wire:model="itemDescription"
                            placeholder="Descrição"
                            class="
                                rounded-lg
                                border border-zinc-300
                                bg-white px-3 py-2
                                text-sm

                                md:col-span-1
                                xl:col-span-1

                                dark:border-zinc-700
                                dark:bg-zinc-950
                                dark:text-white
                            "
                        />


                        <input
                            type="number"
                            min="0.001"
                            step="0.001"
                            wire:model="itemQuantity"
                            placeholder="Qtd."
                            class="
                                rounded-lg
                                border border-zinc-300
                                bg-white px-3 py-2
                                text-sm

                                dark:border-zinc-700
                                dark:bg-zinc-950
                                dark:text-white
                            "
                        />


                        <select
                            wire:model="itemUnit"
                            class="
                                rounded-lg
                                border border-zinc-300
                                bg-white px-3 py-2
                                text-sm

                                dark:border-zinc-700
                                dark:bg-zinc-950
                                dark:text-white
                            "
                        >
                            @foreach (
                                $this->itemUnitOptions()
                                as $value => $label
                            )
                                <option value="{{ $value }}">
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>


                        <input
                            type="number"
                            min="0"
                            step="0.01"
                            wire:model="itemUnitPrice"
                            placeholder="Valor unitário"
                            class="
                                rounded-lg
                                border border-zinc-300
                                bg-white px-3 py-2
                                text-sm

                                dark:border-zinc-700
                                dark:bg-zinc-950
                                dark:text-white
                            "
                        />

                    </div>


                    @error('itemDescription')
                        <p class="mt-2 text-xs text-red-600">
                            {{ $message }}
                        </p>
                    @enderror

                    @error('itemUnitPrice')
                        <p class="mt-2 text-xs text-red-600">
                            {{ $message }}
                        </p>
                    @enderror

                    @error('items')
                        <p class="mt-2 text-xs text-red-600">
                            {{ $message }}
                        </p>
                    @enderror


                    <div class="mt-3 flex flex-wrap gap-2">

                        <button
                            type="button"
                            wire:click="saveItem"
                            class="
                                rounded-lg
                                border border-zinc-300
                                px-3 py-2
                                text-sm font-semibold
                                text-zinc-700

                                hover:bg-zinc-100

                                dark:border-zinc-700
                                dark:text-zinc-200
                                dark:hover:bg-zinc-800
                            "
                        >
                            {{
                                $editingItemIndex !== null
                                    ? 'Atualizar item'
                                    : 'Adicionar item'
                            }}
                        </button>


                        @if (
                            $editingItemIndex !== null
                        )

                            <button
                                type="button"
                                wire:click="cancelItemEdit"
                                class="
                                    px-3 py-2
                                    text-sm font-semibold
                                    text-zinc-500
                                "
                            >
                                Cancelar edição
                            </button>

                        @endif

                    </div>

                </div>

            </div>


            <div
                class="
                    flex flex-wrap
                    items-center
                    justify-end
                    gap-2

                    border-t border-zinc-200
                    bg-zinc-50/70

                    px-6 py-4

                    dark:border-zinc-800
                    dark:bg-zinc-950/30
                "
            >

                <button
                    type="button"
                    wire:click="cancelEditor"
                    class="
                        rounded-lg
                        border border-zinc-300
                        px-4 py-2.5
                        text-sm font-semibold
                        text-zinc-600

                        dark:border-zinc-700
                        dark:text-zinc-300
                    "
                >
                    Cancelar
                </button>


                <button
                    type="submit"
                    class="
                        rounded-lg
                        bg-emerald-600
                        px-4 py-2.5
                        text-sm font-semibold
                        text-white
                        hover:bg-emerald-700
                    "
                >
                    Salvar modelo
                </button>

            </div>

        </form>

    @else

        @forelse ($this->templates as $template)

            <article
                wire:key="quote-template-{{ $template->id }}"
                class="
                    rounded-2xl
                    border border-zinc-200
                    bg-white
                    p-5
                    shadow-sm

                    dark:border-zinc-800
                    dark:bg-zinc-900
                "
            >

                <div
                    class="
                        flex flex-col gap-4

                        sm:flex-row
                        sm:items-start
                        sm:justify-between
                    "
                >

                    <div class="min-w-0">

                        <div class="flex flex-wrap items-center gap-2">

                            <h2 class="font-semibold text-zinc-950 dark:text-white">
                                {{ $template->name }}
                            </h2>

                            <span
                                class="
                                    rounded-full
                                    bg-violet-100
                                    px-2 py-0.5
                                    text-[10px] font-semibold
                                    text-violet-700

                                    dark:bg-violet-950
                                    dark:text-violet-300
                                "
                            >
                                {{ $template->items_count }}
                                {{ $template->items_count === 1 ? 'ITEM' : 'ITENS' }}
                            </span>

                        </div>

                        <p class="mt-1 text-sm font-medium text-zinc-700 dark:text-zinc-200">
                            {{ $template->title }}
                        </p>

                        <p class="mt-2 text-xs text-zinc-500">
                            Validade padrão:
                            {{ $template->validity_days }}
                            {{ $template->validity_days === 1 ? 'dia' : 'dias' }}
                        </p>

                    </div>


                    <div class="flex flex-wrap gap-2">

                        <a
                            href="{{
                                route(
                                    'quotes.create',
                                    [
                                        'modelo' =>
                                            $template->id,
                                    ]
                                )
                            }}"
                            wire:navigate
                            class="
                                rounded-lg
                                border border-emerald-200
                                bg-emerald-50
                                px-3 py-2
                                text-xs font-semibold
                                text-emerald-700

                                dark:border-emerald-900
                                dark:bg-emerald-950/40
                                dark:text-emerald-300
                            "
                        >
                            Usar em proposta
                        </a>

                        <button
                            type="button"
                            wire:click="editTemplate({{ $template->id }})"
                            class="
                                rounded-lg
                                border border-zinc-300
                                px-3 py-2
                                text-xs font-semibold
                                text-zinc-600

                                dark:border-zinc-700
                                dark:text-zinc-300
                            "
                        >
                            Editar
                        </button>

                        <button
                            type="button"
                            wire:click="askDelete({{ $template->id }})"
                            class="
                                rounded-lg
                                border border-red-200
                                px-3 py-2
                                text-xs font-semibold
                                text-red-600

                                dark:border-red-900
                                dark:text-red-400
                            "
                        >
                            Excluir
                        </button>

                    </div>

                </div>


                @if (
                    $deletingTemplateId
                    === $template->id
                )

                    <div
                        class="
                            mt-4
                            flex flex-col gap-3

                            rounded-xl
                            border border-red-200
                            bg-red-50
                            p-4

                            sm:flex-row
                            sm:items-center
                            sm:justify-between

                            dark:border-red-900
                            dark:bg-red-950/30
                        "
                    >

                        <p class="text-sm text-red-700 dark:text-red-300">
                            Excluir definitivamente este modelo?
                        </p>

                        <div class="flex gap-2">

                            <button
                                type="button"
                                wire:click="cancelDelete"
                                class="
                                    rounded-lg
                                    border border-zinc-300
                                    bg-white
                                    px-3 py-2
                                    text-xs font-semibold

                                    dark:border-zinc-700
                                    dark:bg-zinc-900
                                "
                            >
                                Cancelar
                            </button>

                            <button
                                type="button"
                                wire:click="deleteTemplate"
                                class="
                                    rounded-lg
                                    bg-red-600
                                    px-3 py-2
                                    text-xs font-semibold
                                    text-white
                                    hover:bg-red-700
                                "
                            >
                                Confirmar exclusão
                            </button>

                        </div>

                    </div>

                @endif

            </article>

        @empty

            <div
                class="
                    rounded-2xl
                    border border-dashed
                    border-zinc-300
                    bg-white
                    px-6 py-12
                    text-center

                    dark:border-zinc-700
                    dark:bg-zinc-900
                "
            >

                <h2 class="font-semibold text-zinc-950 dark:text-white">
                    Nenhum modelo ainda
                </h2>

                <p class="mt-2 text-sm text-zinc-500">
                    Crie seu primeiro modelo para montar
                    propostas recorrentes em poucos segundos.
                </p>

                <button
                    type="button"
                    wire:click="newTemplate"
                    class="
                        mt-5
                        rounded-lg
                        bg-emerald-600
                        px-4 py-2.5
                        text-sm font-semibold
                        text-white
                        hover:bg-emerald-700
                    "
                >
                    Criar primeiro modelo
                </button>

            </div>

        @endforelse

    @endif

</div>
