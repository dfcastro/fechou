<?php

use App\Support\BrazilianInput;
use App\Rules\ValidBrazilianDocument;
use App\Rules\UniqueBusinessDocument;
use App\Enums\PlanFeature;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Empresa | Negozia')]
    class extends Component {
    use WithFileUploads;

    public string $name = '';
    public string $document = '';
    public string $email = '';
    public string $phone = '';
    public string $whatsapp = '';

    public bool $publicProfileEnabled = false;
    public string $publicDescription = '';

    public string $publicServiceCategoryId = '';

    public array $publicServiceIds = [];

    public string $address = '';
    public string $addressNumber = '';
    public string $addressComplement = '';
    public string $province = '';
    public string $city = '';
    public string $state = '';
    public string $postalCode = '';

    public string $pixKey = '';

    public $logo = null;

    #[Computed]
    public function business()
    {
        return Auth::user()?->business;
    }

    #[Computed]
    public function serviceCategories()
    {
        return ServiceCategory::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function availableServices()
    {
        if (
            blank(
                $this->publicServiceCategoryId
            )
        ) {
            return collect();
        }

        return Service::query()
            ->where(
                'service_category_id',
                (int) $this->publicServiceCategoryId
            )
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function updatedPublicServiceCategoryId(): void
    {
        $this->publicServiceIds = [];
    }

    #[Computed]
    public function canUseCustomBranding(): bool
    {
        if (!$this->business) {
            return false;
        }

        return app(SubscriptionService::class)
            ->hasFeature(
                $this->business,
                PlanFeature::CUSTOM_BRANDING
            );
    }

    public function mount(
        SubscriptionService $subscriptionService
    ): void {
        $user = Auth::user();

        $business = $user
            ->business()
            ->firstOrCreate(
                [],
                [
                    'name' => $user->name,
                ]
            );

        /*
         * Atualiza explicitamente a relação em memória.
         *
         * Isso é importante caso "business" tenha sido
         * carregada anteriormente como null.
         */
        $user->setRelation(
            'business',
            $business
        );

        /*
         * Garante que toda empresa tenha ao menos
         * a assinatura padrão do Negozia.
         */
        $subscriptionService
            ->ensureDefaultSubscription(
                $business
            );

        $this->name = $business->name ?? '';
        $this->document = $business->document ?? '';
        $this->email = $business->email ?? '';
        $this->phone = $business->phone ?? '';
        $this->whatsapp = $business->whatsapp ?? '';

        $this->publicProfileEnabled =
            (bool) $business->public_profile_enabled;

        $this->publicDescription =
            $business->public_description ?? '';

        $selectedServices = $business
            ->services()
            ->where(
                'services.active',
                true
            )
            ->orderBy(
                'services.sort_order'
            )
            ->get([
                'services.id',
                'services.service_category_id',
            ]);

        $this->publicServiceIds =
            $selectedServices
                ->pluck('id')
                ->map(
                    fn ($id) => (string) $id
                )
                ->values()
                ->all();

        $selectedCategory =
            $selectedServices->first();

        $this->publicServiceCategoryId =
            $selectedCategory
                ? (string) $selectedCategory
                    ->service_category_id
                : '';

        $this->address = $business->address ?? '';
        $this->addressNumber =
            $business->address_number ?? '';
        $this->addressComplement =
            $business->address_complement ?? '';
        $this->province =
            $business->province ?? '';
        $this->city = $business->city ?? '';
        $this->state = $business->state ?? '';
        $this->postalCode = $business->postal_code ?? '';

        $this->pixKey = $business->pix_key ?? '';
    }

    /*
    |--------------------------------------------------------------------------
    | Logo / identidade visual
    |--------------------------------------------------------------------------
    */

    public function updatedLogo(): void
    {
        /*
         * Proteção de servidor.
         *
         * Não basta esconder o campo no HTML:
         * uma conta sem CUSTOM_BRANDING não pode
         * enviar o arquivo chamando o Livewire diretamente.
         */
        abort_unless(
            $this->canUseCustomBranding,
            403
        );

        $this->validateOnly('logo', [
            'logo' => [
                'nullable',
                'image',
                'mimes:png,jpg,jpeg',
                'max:2048',
            ],
        ], [
            'logo.image' =>
                'Selecione uma imagem válida.',

            'logo.mimes' =>
                'A logo deve ser PNG, JPG ou JPEG.',

            'logo.max' =>
                'A logo deve ter no máximo 2 MB.',
        ]);
    }

    public function removeLogo(): void
    {
        $business = Auth::user()->business;

        abort_unless($business, 403);

        /*
         * CUSTOM_BRANDING é um recurso Pro.
         */
        abort_unless(
            $this->canUseCustomBranding,
            403
        );

        if ($business->logo_path) {
            Storage::disk('public')
                ->delete($business->logo_path);
        }

        $business->update([
            'logo_path' => null,
        ]);

        $this->logo = null;

        session()->flash(
            'success',
            'Logo removida com sucesso.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Salvar empresa
    |--------------------------------------------------------------------------
    */

    public function save(): void
    {
        $business = Auth::user()->business;

        abort_unless($business, 403);

        /*
         * Defesa adicional:
         *
         * mesmo que uma conta Grátis tente injetar
         * um upload no estado do componente, o save()
         * também bloqueia a alteração da identidade visual.
         */
        if (
            $this->logo
            && !$this->canUseCustomBranding
        ) {
            abort(403);
        }

        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'document' => [
                'bail',
                'nullable',
                'string',
                new ValidBrazilianDocument(),
                new UniqueBusinessDocument(
                    $business->id
                ),
                'max:20',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'whatsapp' => [
                'nullable',
                'string',
                'max:30',
            ],

            'publicProfileEnabled' => [
                'boolean',
            ],

            'publicDescription' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'publicServiceCategoryId' => [
                'nullable',
                'integer',
                'exists:service_categories,id',
            ],

            'publicServiceIds' => [
                'array',
            ],

            'publicServiceIds.*' => [
                'integer',
                'exists:services,id',
            ],

            'address' => [
                'nullable',
                'string',
                'max:255',
            ],

            'addressNumber' => [
                'nullable',
                'string',
                'max:20',
            ],

            'addressComplement' => [
                'nullable',
                'string',
                'max:255',
            ],

            'province' => [
                'nullable',
                'string',
                'max:255',
            ],

            'city' => [
                'nullable',
                'string',
                'max:255',
            ],

            'state' => [
                'nullable',
                'string',
                'max:2',
            ],

            'postalCode' => [
                'nullable',
                'string',
                'max:10',
            ],

            'pixKey' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];

        /*
         * Só validamos/processamos upload de logo
         * para planos que possuem CUSTOM_BRANDING.
         */
        if ($this->canUseCustomBranding) {
            $rules['logo'] = [
                'nullable',
                'image',
                'mimes:png,jpg,jpeg',
                'max:2048',
            ];
        }

        $validated = $this->validate(
            $rules,
            [
                'name.required' =>
                    'Informe o nome da empresa.',

                'email.email' =>
                    'Informe um e-mail válido.',

                'state.max' =>
                    'Use apenas a sigla do estado.',

                'logo.image' =>
                    'Selecione uma imagem válida.',

                'logo.mimes' =>
                    'A logo deve ser PNG, JPG ou JPEG.',

                'logo.max' =>
                    'A logo deve ter no máximo 2 MB.',
            ]
        );

        if (
            $validated['publicProfileEnabled']
            && blank($validated['city'])
        ) {
            $this->addError(
                'city',
                'Informe a cidade para publicar sua empresa.'
            );

            return;
        }

        $categoryId = (int) (
            $validated[
                'publicServiceCategoryId'
            ] ?? 0
        );

        $serviceIds = array_values(
            array_unique(
                array_map(
                    'intval',
                    $validated[
                        'publicServiceIds'
                    ] ?? []
                )
            )
        );

        if (
            $validated['publicProfileEnabled']
            && $categoryId <= 0
        ) {
            $this->addError(
                'publicServiceCategoryId',
                'Selecione a categoria da empresa.'
            );

            return;
        }

        if (
            $categoryId > 0
            && !ServiceCategory::query()
                ->whereKey($categoryId)
                ->where('active', true)
                ->exists()
        ) {
            $this->addError(
                'publicServiceCategoryId',
                'Selecione uma categoria válida.'
            );

            return;
        }

        $selectedServices = collect();

        if (
            $categoryId > 0
            && $serviceIds !== []
        ) {
            $selectedServices =
                Service::query()
                    ->whereIn(
                        'id',
                        $serviceIds
                    )
                    ->where(
                        'service_category_id',
                        $categoryId
                    )
                    ->where(
                        'active',
                        true
                    )
                    ->orderBy(
                        'sort_order'
                    )
                    ->get();

            if (
                $selectedServices->count()
                !== count($serviceIds)
            ) {
                $this->addError(
                    'publicServiceIds',
                    'Um ou mais serviços selecionados não pertencem à categoria informada.'
                );

                return;
            }
        }

        if (
            $validated['publicProfileEnabled']
            && $selectedServices->isEmpty()
        ) {
            $this->addError(
                'publicServiceIds',
                'Selecione ao menos um serviço oferecido.'
            );

            return;
        }

        $publicServices =
            $selectedServices
                ->pluck('name')
                ->implode(', ');

        $publicSlug = $business->public_slug;

        if (
            $validated['publicProfileEnabled']
            && blank($publicSlug)
        ) {
            $baseSlug =
                Str::slug(
                    $validated['name']
                ) ?: 'empresa';

            $publicSlug =
                $baseSlug
                . '-'
                . $business->id;
        }

        /*
         * Dados básicos são liberados para qualquer
         * assinatura ativa, inclusive o plano Grátis.
         */
        $data = [
            'name' =>
                trim($validated['name']),

            'document' =>
                BrazilianInput::document(
                    $validated['document']
                ),

            'email' =>
                $validated['email'] ?: null,

            'phone' =>
                BrazilianInput::phone(
                    $validated['phone']
                ),

            'whatsapp' =>
                BrazilianInput::phone(
                    $validated['whatsapp']
                ),

            'public_profile_enabled' =>
                (bool) $validated[
                    'publicProfileEnabled'
                ],

            'public_slug' =>
                $publicSlug,

            'public_description' =>
                trim(
                    $validated[
                        'publicDescription'
                    ]
                ) ?: null,

            'public_services' =>
                $publicServices ?: null,

            'address' =>
                $validated['address'] ?: null,

            'address_number' =>
                $validated['addressNumber'] ?: null,

            'address_complement' =>
                $validated['addressComplement'] ?: null,

            'province' =>
                $validated['province'] ?: null,

            'city' =>
                $validated['city'] ?: null,

            'state' =>
                BrazilianInput::state(
                    $validated['state']
                ),

            'postal_code' =>
                BrazilianInput::cep(
                    $validated['postalCode']
                ),

            'pix_key' =>
                $validated['pixKey'] ?: null,
        ];

        /*
         * Importante:
         *
         * Ao fazer downgrade para o Grátis, não apagamos
         * a logo existente. Ela fica preservada no banco.
         *
         * Apenas contas com CUSTOM_BRANDING podem
         * substituir/remover a logo.
         */
        if ($this->canUseCustomBranding) {
            $logoPath = $business->logo_path;

            if ($this->logo) {

                if ($logoPath) {
                    Storage::disk('public')
                        ->delete($logoPath);
                }

                $logoPath = $this->logo->store(
                    'business-logos',
                    'public'
                );
            }

            $data['logo_path'] = $logoPath;
        }

        $business->update($data);

        $business
            ->services()
            ->sync(
                $selectedServices
                    ->pluck('id')
                    ->all()
            );

        $this->logo = null;

        session()->flash(
            'success',
            'Dados da empresa atualizados com sucesso.'
        );
    }
};
?>

<div class="mx-auto w-full max-w-6xl space-y-5">

    <div>

        <h1 class="text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">
            Minha empresa
        </h1>

        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
            Essas informações aparecem nas suas propostas e PDFs.
        </p>

    </div>


    <form wire:submit="save" class="space-y-5">

        {{-- ===================================================== --}}
        {{-- IDENTIDADE VISUAL / CUSTOM BRANDING --}}
        {{-- ===================================================== --}}

        @if ($this->canUseCustomBranding)

            <section class="
                    rounded-2xl
                    border border-zinc-200
                    bg-white
                    p-5
                    shadow-sm

                    dark:border-zinc-800
                    dark:bg-zinc-900
                ">

                <div class="flex flex-wrap items-start justify-between gap-3">

                    <div>

                        <div class="flex items-center gap-2">

                            <h2 class="font-semibold text-zinc-950 dark:text-white">
                                Identidade da empresa
                            </h2>

                            <span class="
                                    rounded-full
                                    bg-violet-100
                                    px-2 py-0.5
                                    text-[10px] font-bold
                                    text-violet-700

                                    dark:bg-violet-950
                                    dark:text-violet-300
                                ">
                                PRO
                            </span>

                        </div>

                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            Personalize suas propostas e PDFs com a sua marca.
                        </p>

                    </div>

                </div>


                <div class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-center">

                    <div class="
                            flex size-24 shrink-0
                            items-center justify-center
                            overflow-hidden
                            rounded-2xl
                            border border-zinc-200
                            bg-zinc-50

                            dark:border-zinc-700
                            dark:bg-zinc-950
                        ">

                        @if ($logo)

                            <img
                                src="{{ $logo->temporaryUrl() }}"
                                class="h-full w-full object-contain p-2"
                                alt="Nova logo"
                            >

                        @elseif ($this->business?->logo_path)

                            <img
                                src="{{ asset(
                                    'storage/' .
                                    $this->business->logo_path
                                ) }}"
                                class="h-full w-full object-contain p-2"
                                alt="{{ $this->business->name }}"
                            >

                        @else

                            <span class="
                                    text-3xl font-bold
                                    text-emerald-600
                                    dark:text-emerald-400
                                ">
                                {{ mb_strtoupper(
                                    mb_substr(
                                        $name ?: 'F',
                                        0,
                                        1
                                    )
                                ) }}
                            </span>

                        @endif

                    </div>


                    <div class="flex-1">

                        <input
                            type="file"
                            wire:model="logo"
                            accept=".png,.jpg,.jpeg,image/png,image/jpeg"
                            class="
                                block w-full
                                text-sm
                                text-zinc-600

                                file:mr-4
                                file:rounded-lg
                                file:border-0
                                file:bg-zinc-100
                                file:px-4
                                file:py-2.5
                                file:text-sm
                                file:font-semibold
                                file:text-zinc-700

                                hover:file:bg-zinc-200

                                dark:text-zinc-400
                                dark:file:bg-zinc-800
                                dark:file:text-zinc-200
                                dark:hover:file:bg-zinc-700
                            "
                        >

                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                            PNG ou JPG, até 2 MB. Prefira uma imagem quadrada ou horizontal.
                        </p>

                        @error('logo')
                            <p class="mt-2 text-sm text-red-600 dark:text-red-400">
                                {{ $message }}
                            </p>
                        @enderror

                        @if ($this->business?->logo_path)

                            <button
                                type="button"
                                wire:click="removeLogo"
                                wire:confirm="Deseja remover a logo da empresa?"
                                class="
                                    mt-3
                                    text-sm font-medium
                                    text-red-600
                                    hover:text-red-700

                                    dark:text-red-400
                                    dark:hover:text-red-300
                                ">
                                Remover logo
                            </button>

                        @endif

                    </div>

                </div>

            </section>

        @else

            <section class="
                    overflow-hidden
                    rounded-2xl
                    border border-violet-200
                    bg-violet-50
                    shadow-sm

                    dark:border-violet-900/60
                    dark:bg-violet-950/20
                ">

                <div class="
                        flex flex-col gap-4
                        p-5

                        sm:flex-row
                        sm:items-center
                        sm:justify-between
                    ">

                    <div class="flex items-start gap-4">

                        <div class="
                                flex size-11 shrink-0
                                items-center justify-center
                                rounded-xl
                                bg-violet-100
                                text-violet-700

                                dark:bg-violet-950
                                dark:text-violet-300
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
                                    d="M4 16.5V19a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-2.5M8 11l4-4 4 4M12 7v10"
                                />
                            </svg>

                        </div>

                        <div>

                            <div class="flex flex-wrap items-center gap-2">

                                <h2 class="font-semibold text-zinc-950 dark:text-white">
                                    Personalização da marca
                                </h2>

                                <span class="
                                        rounded-full
                                        bg-violet-600
                                        px-2 py-0.5
                                        text-[10px] font-bold
                                        text-white

                                        dark:bg-violet-500
                                        dark:text-zinc-950
                                    ">
                                    PRO
                                </span>

                            </div>

                            <p class="
                                    mt-1 max-w-xl
                                    text-sm leading-6
                                    text-zinc-600

                                    dark:text-zinc-300
                                ">
                                Adicione a logo da sua empresa e deixe suas
                                propostas e PDFs com uma apresentação ainda
                                mais profissional.
                            </p>

                            @if ($this->business?->logo_path)

                                <p class="
                                        mt-2
                                        text-xs font-medium
                                        text-violet-700

                                        dark:text-violet-300
                                    ">
                                    Sua logo atual está preservada e poderá ser
                                    gerenciada novamente ao usar o plano Pro.
                                </p>

                            @endif

                        </div>

                    </div>


                    <a
                        href="{{ route('settings.subscription') }}"
                        wire:navigate
                        class="
                            inline-flex shrink-0
                            items-center justify-center
                            gap-2
                            rounded-lg
                            bg-violet-600
                            px-4 py-2.5
                            text-sm font-semibold
                            text-white
                            shadow-sm
                            transition
                            hover:bg-violet-700

                            dark:bg-violet-500
                            dark:text-zinc-950
                            dark:hover:bg-violet-400
                        ">
                        Conhecer o Negozia Pro

                        <svg
                            class="size-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="m9 18 6-6-6-6"
                            />
                        </svg>
                    </a>

                </div>

            </section>

        @endif


        {{-- DADOS --}}

        <section class="
                rounded-2xl
                border border-zinc-200
                bg-white
                shadow-sm

                dark:border-zinc-800
                dark:bg-zinc-900
            ">

            <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">

                <h2 class="font-semibold text-zinc-950 dark:text-white">
                    Dados da empresa
                </h2>

                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    Informações exibidas nas propostas.
                </p>

            </div>


            <div class="grid gap-4 p-5 md:grid-cols-2">

                <div class="md:col-span-2">

                    <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        Nome da empresa *
                    </label>

                    <input type="text" wire:model="name" class="
                            w-full rounded-lg
                            border border-zinc-300
                            bg-white
                            px-3 py-2.5
                            text-sm text-zinc-900

                            focus:border-emerald-500
                            focus:ring-2
                            focus:ring-emerald-500/20

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-white
                        ">

                    @error('name')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ $message }}
                        </p>
                    @enderror

                </div>


                <div>

                    <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        CPF/CNPJ
                    </label>

                    <input type="text" wire:model="document" class="
                            w-full rounded-lg
                            border border-zinc-300
                            bg-white
                            px-3 py-2.5
                            text-sm text-zinc-900

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-white
                        "
                                    data-negozia-mask="document"
                                    inputmode="text"
                                    maxlength="18"
                                    autocomplete="off"
                                >

                    @error('document')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ $message }}
                        </p>
                    @enderror

                </div>


                <div>

                    <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        E-mail
                    </label>

                    <input type="email" wire:model="email" class="
                            w-full rounded-lg
                            border border-zinc-300
                            bg-white
                            px-3 py-2.5
                            text-sm text-zinc-900

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-white
                        ">

                </div>


                <div>

                    <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        Telefone
                    </label>

                    <input type="text" wire:model="phone" placeholder="(33) 3333-3333" class="
                            w-full rounded-lg
                            border border-zinc-300
                            bg-white
                            px-3 py-2.5
                            text-sm text-zinc-900

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-white
                        "
                                    data-negozia-mask="phone"
                                    inputmode="tel"
                                    maxlength="15"
                                    autocomplete="tel"
                                >

                </div>


                <div>

                    <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        WhatsApp
                    </label>

                    <input type="text" wire:model="whatsapp" placeholder="(33) 99999-9999" class="
                            w-full rounded-lg
                            border border-zinc-300
                            bg-white
                            px-3 py-2.5
                            text-sm text-zinc-900

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-white
                        "
                                    data-negozia-mask="phone"
                                    inputmode="tel"
                                    maxlength="15"
                                    autocomplete="tel"
                                >

                </div>

            </div>

        </section>


        {{-- PERFIL PÚBLICO --}}

        <section class="
                rounded-2xl
                border border-emerald-200
                bg-emerald-50/60
                shadow-sm

                dark:border-emerald-900/70
                dark:bg-emerald-950/20
            ">

            <div class="
                    flex flex-col gap-4
                    border-b border-emerald-200
                    px-5 py-4

                    sm:flex-row
                    sm:items-start
                    sm:justify-between

                    dark:border-emerald-900/60
                ">

                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="
                                font-semibold
                                text-zinc-950
                                dark:text-white
                            ">
                            Perfil público no Negozia
                        </h2>

                        <span class="
                                rounded-full
                                bg-emerald-600
                                px-2 py-0.5
                                text-[10px]
                                font-bold
                                text-white
                            ">
                            VITRINE
                        </span>
                    </div>

                    <p class="
                            mt-1
                            max-w-2xl
                            text-sm
                            leading-6
                            text-zinc-600
                            dark:text-zinc-300
                        ">
                        Ganhe uma vitrine gratuita para sua empresa
                        e seja encontrado por pessoas procurando seus
                        serviços na sua cidade.
                    </p>
                </div>

                @if (
                    $this->business?->public_slug
                    && $publicProfileEnabled
                )
                    <a
                        href="{{ route(
                            'marketplace.show',
                            $this->business->public_slug
                        ) }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="
                            shrink-0
                            text-sm
                            font-semibold
                            text-emerald-700
                            hover:text-emerald-800

                            dark:text-emerald-400
                            dark:hover:text-emerald-300
                        "
                    >
                        Ver perfil público ↗
                    </a>
                @endif
            </div>

            <div class="space-y-5 p-5">

                <div class="
                        grid gap-3

                        sm:grid-cols-3
                    ">

                    <div class="
                            rounded-xl
                            border border-emerald-200
                            bg-white
                            p-4

                            dark:border-emerald-900
                            dark:bg-zinc-900
                        ">
                        <div class="
                                flex size-9
                                items-center
                                justify-center
                                rounded-lg
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
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 21s6-4.35 6-11a6 6 0 1 0-12 0c0 6.65 6 11 6 11Z"
                                />
                                <circle cx="12" cy="10" r="2" />
                            </svg>
                        </div>

                        <p class="
                                mt-3 text-sm
                                font-semibold
                                text-zinc-900
                                dark:text-white
                            ">
                            Apareça na sua cidade
                        </p>

                        <p class="
                                mt-1 text-xs
                                leading-5
                                text-zinc-500
                                dark:text-zinc-400
                            ">
                            Clientes podem encontrar sua empresa
                            pesquisando serviços locais.
                        </p>
                    </div>


                    <div class="
                            rounded-xl
                            border border-emerald-200
                            bg-white
                            p-4

                            dark:border-emerald-900
                            dark:bg-zinc-900
                        ">
                        <div class="
                                flex size-9
                                items-center
                                justify-center
                                rounded-lg
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
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9.5 9.5 0 0 1-4-.9L3 20.5 4.5 16A8.4 8.4 0 1 1 21 11.5Z"
                                />
                            </svg>
                        </div>

                        <p class="
                                mt-3 text-sm
                                font-semibold
                                text-zinc-900
                                dark:text-white
                            ">
                            Receba contatos
                        </p>

                        <p class="
                                mt-1 text-xs
                                leading-5
                                text-zinc-500
                                dark:text-zinc-400
                            ">
                            O cliente pode falar diretamente com
                            você pelo WhatsApp.
                        </p>
                    </div>


                    <div class="
                            rounded-xl
                            border border-emerald-200
                            bg-white
                            p-4

                            dark:border-emerald-900
                            dark:bg-zinc-900
                        ">
                        <div class="
                                flex size-9
                                items-center
                                justify-center
                                rounded-lg
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
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M5 12.5 9 16l10-10"
                                />
                            </svg>
                        </div>

                        <p class="
                                mt-3 text-sm
                                font-semibold
                                text-zinc-900
                                dark:text-white
                            ">
                            Você controla
                        </p>

                        <p class="
                                mt-1 text-xs
                                leading-5
                                text-zinc-500
                                dark:text-zinc-400
                            ">
                            A participação é opcional e pode ser
                            desativada quando quiser.
                        </p>
                    </div>

                </div>


                <label class="
                        flex cursor-pointer
                        items-start gap-3
                    ">
                    <input
                        type="checkbox"
                        wire:model="publicProfileEnabled"
                        class="
                            mt-1 size-4
                            rounded
                            border-zinc-300
                            text-emerald-600
                            focus:ring-emerald-500
                        "
                    >

                    <span>
                        <span class="
                                block text-sm
                                font-semibold
                                text-zinc-900
                                dark:text-white
                            ">
                            Quero divulgar minha empresa
                            no Negozia
                        </span>

                        <span class="
                                mt-1 block
                                text-xs leading-5
                                text-zinc-500
                                dark:text-zinc-400
                            ">
                            Seu nome comercial, logo, serviços,
                            descrição, cidade, UF e contatos poderão
                            aparecer para clientes.

                            CPF/CNPJ, chave Pix e dados privados da
                            conta nunca aparecem no perfil público.
                        </span>
                    </span>
                </label>

                <div>
                    <label class="
                            mb-1.5 block
                            text-sm font-medium
                            text-zinc-700
                            dark:text-zinc-300
                        ">
                        Categoria principal
                    </label>

                    <select
                        wire:model.live="publicServiceCategoryId"
                        class="
                            w-full rounded-lg
                            border border-zinc-300
                            bg-white
                            px-3 py-2.5
                            text-sm text-zinc-900

                            focus:border-emerald-500
                            focus:ring-2
                            focus:ring-emerald-500/20

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-white
                        "
                    >
                        <option value="">
                            Selecione uma categoria
                        </option>

                        @foreach (
                            $this->serviceCategories
                            as $category
                        )
                            <option
                                value="{{ $category->id }}"
                            >
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>

                    <p class="
                            mt-1.5 text-xs
                            text-zinc-500
                            dark:text-zinc-400
                        ">
                        Escolha a área que melhor representa
                        os serviços da sua empresa.
                    </p>

                    @error('publicServiceCategoryId')
                        <p class="
                                mt-1 text-sm
                                text-red-600
                                dark:text-red-400
                            ">
                            {{ $message }}
                        </p>
                    @enderror
                </div>


                @if ($publicServiceCategoryId)
                    <div>
                        <div class="
                                flex flex-wrap
                                items-end
                                justify-between
                                gap-2
                            ">
                            <div>
                                <label class="
                                        block
                                        text-sm font-medium
                                        text-zinc-700
                                        dark:text-zinc-300
                                    ">
                                    Serviços oferecidos
                                </label>

                                <p class="
                                        mt-1 text-xs
                                        text-zinc-500
                                        dark:text-zinc-400
                                    ">
                                    Selecione todos os serviços
                                    que sua empresa realiza.
                                </p>
                            </div>

                            <span class="
                                    text-xs font-medium
                                    text-zinc-400
                                ">
                                {{
                                    count(
                                        $publicServiceIds
                                    )
                                }}
                                selecionado(s)
                            </span>
                        </div>

                        <div class="
                                mt-3
                                grid gap-2

                                sm:grid-cols-2
                                lg:grid-cols-3
                            ">
                            @forelse (
                                $this->availableServices
                                as $service
                            )
                                <label
                                    wire:key="public-service-{{ $service->id }}"
                                    class="
                                        flex cursor-pointer
                                        items-start gap-3
                                        rounded-xl
                                        border border-zinc-200
                                        bg-white
                                        p-3
                                        transition

                                        hover:border-emerald-300
                                        hover:bg-emerald-50/50

                                        dark:border-zinc-700
                                        dark:bg-zinc-950
                                        dark:hover:border-emerald-800
                                        dark:hover:bg-emerald-950/20
                                    "
                                >
                                    <input
                                        type="checkbox"
                                        value="{{ $service->id }}"
                                        wire:model="publicServiceIds"
                                        class="
                                            mt-0.5 size-4
                                            rounded
                                            border-zinc-300
                                            text-emerald-600
                                            focus:ring-emerald-500
                                        "
                                    >

                                    <span class="
                                            text-sm font-medium
                                            text-zinc-800
                                            dark:text-zinc-200
                                        ">
                                        {{ $service->name }}
                                    </span>
                                </label>
                            @empty
                                <p class="
                                        text-sm
                                        text-zinc-500
                                        dark:text-zinc-400
                                    ">
                                    Nenhum serviço cadastrado
                                    nesta categoria.
                                </p>
                            @endforelse
                        </div>

                        @error('publicServiceIds')
                            <p class="
                                    mt-2 text-sm
                                    text-red-600
                                    dark:text-red-400
                                ">
                                {{ $message }}
                            </p>
                        @enderror

                        @error('publicServiceIds.*')
                            <p class="
                                    mt-2 text-sm
                                    text-red-600
                                    dark:text-red-400
                                ">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                @else
                    <div class="
                            rounded-xl
                            border border-dashed
                            border-zinc-300
                            px-4 py-5
                            text-center
                            text-sm
                            text-zinc-500

                            dark:border-zinc-700
                            dark:text-zinc-400
                        ">
                        Selecione uma categoria para ver
                        os serviços disponíveis.
                    </div>
                @endif


                <div>
                    <label class="
                            mb-1.5 block
                            text-sm font-medium
                            text-zinc-700
                            dark:text-zinc-300
                        ">
                        Sobre a empresa
                    </label>

                    <textarea
                        wire:model="publicDescription"
                        rows="4"
                        maxlength="1000"
                        placeholder="Conte rapidamente o que sua empresa faz e por que o cliente deveria entrar em contato."
                        class="
                            w-full rounded-lg
                            border border-zinc-300
                            bg-white
                            px-3 py-2.5
                            text-sm text-zinc-900

                            focus:border-emerald-500
                            focus:ring-2
                            focus:ring-emerald-500/20

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-white
                        "
                    ></textarea>

                    @error('publicDescription')
                        <p class="
                                mt-1 text-sm
                                text-red-600
                                dark:text-red-400
                            ">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

            </div>

        </section>


        {{-- ENDEREÇO --}}

        <section class="
                rounded-2xl
                border border-zinc-200
                bg-white
                shadow-sm

                dark:border-zinc-800
                dark:bg-zinc-900
            ">

            <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">

                <h2 class="font-semibold text-zinc-950 dark:text-white">
                    Endereço
                </h2>

            </div>


            <div class="grid gap-4 p-5 md:grid-cols-6">

                <div class="md:col-span-4">

                    <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        Logradouro
                    </label>

                    <input
                        type="text"
                        wire:model="address"
                        autocomplete="street-address"
                        class="
                            w-full rounded-lg
                            border border-zinc-300
                            bg-white
                            px-3 py-2.5
                            text-sm text-zinc-900

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-white
                        "
                    >

                </div>


                <div class="md:col-span-2">

                    <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        Número
                    </label>

                    <input
                        type="text"
                        wire:model="addressNumber"
                        maxlength="20"
                        class="
                            w-full rounded-lg
                            border border-zinc-300
                            bg-white
                            px-3 py-2.5
                            text-sm text-zinc-900

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-white
                        "
                    >

                </div>


                <div class="md:col-span-3">

                    <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        Complemento
                    </label>

                    <input
                        type="text"
                        wire:model="addressComplement"
                        class="
                            w-full rounded-lg
                            border border-zinc-300
                            bg-white
                            px-3 py-2.5
                            text-sm text-zinc-900

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-white
                        "
                    >

                </div>


                <div class="md:col-span-3">

                    <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        Bairro
                    </label>

                    <input
                        type="text"
                        wire:model="province"
                        class="
                            w-full rounded-lg
                            border border-zinc-300
                            bg-white
                            px-3 py-2.5
                            text-sm text-zinc-900

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-white
                        "
                    >

                </div>


                <div class="md:col-span-3">

                    <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        Cidade
                    </label>

                    <input
                        type="text"
                        wire:model="city"
                        autocomplete="address-level2"
                        class="
                            w-full rounded-lg
                            border border-zinc-300
                            bg-white
                            px-3 py-2.5
                            text-sm text-zinc-900

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-white
                        "
                    >

                </div>


                <div class="md:col-span-1">

                    <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        UF
                    </label>

                    <input
                        type="text"
                        wire:model="state"
                        maxlength="2"
                        autocomplete="address-level1"
                        data-negozia-mask="uf"
                        autocapitalize="characters"
                        class="
                            w-full rounded-lg
                            border border-zinc-300
                            bg-white
                            px-3 py-2.5
                            text-sm uppercase text-zinc-900

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-white
                        "
                    >

                </div>


                <div class="md:col-span-2">

                    <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        CEP
                    </label>

                    <input
                        type="text"
                        wire:model="postalCode"
                        data-negozia-mask="cep"
                        inputmode="numeric"
                        maxlength="9"
                        autocomplete="postal-code"
                        class="
                            w-full rounded-lg
                            border border-zinc-300
                            bg-white
                            px-3 py-2.5
                            text-sm text-zinc-900

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-white
                        "
                    >

                </div>

            </div>

        </section>


        {{-- COBRANÇA / DADOS DE PAGAMENTO --}}

        <div
            class="
                flex
                flex-col
                gap-2

                px-1

                sm:flex-row
                sm:items-center
                sm:justify-between
            "
        >
            <div>
                <p
                    class="
                        text-sm
                        font-medium
                        text-zinc-700
                        dark:text-zinc-300
                    "
                >
                    Dados de pagamento
                </p>

                <p
                    class="
                        mt-0.5
                        text-xs
                        text-zinc-500
                        dark:text-zinc-400
                    "
                >
                    Chave Pix e instruções de pagamento são
                    gerenciadas na área de Cobrança.
                </p>
            </div>

            <a
                href="{{ route('settings.payment') }}"
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
                Configurar cobrança →
            </a>
        </div>


        <div class="
                flex flex-col gap-3

                sm:flex-row
                sm:items-center
                sm:justify-between
            ">

            <div>
                @if (session('success'))
                    <div class="
                            inline-flex
                            items-center gap-2
                            rounded-lg
                            border border-emerald-200
                            bg-emerald-50
                            px-3 py-2
                            text-sm font-medium
                            text-emerald-700

                            dark:border-emerald-900
                            dark:bg-emerald-950/40
                            dark:text-emerald-300
                        ">
                        <svg
                            class="size-4 shrink-0"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M5 12.5 9 16l10-10"
                            />
                        </svg>

                        {{ session('success') }}
                    </div>
                @endif
            </div>

            <button type="submit" wire:loading.attr="disabled" class="
                    inline-flex items-center justify-center
                    rounded-lg
                    bg-emerald-600
                    px-4 py-2.5
                    text-sm font-semibold
                    text-white
                    shadow-sm

                    hover:bg-emerald-700

                    disabled:opacity-60

                    dark:bg-emerald-500
                    dark:text-zinc-950
                    dark:hover:bg-emerald-400
                ">

                <span wire:loading.remove wire:target="save">
                    Salvar empresa
                </span>

                <span wire:loading wire:target="save">
                    Salvando...
                </span>

            </button>

        </div>

    </form>

</div>