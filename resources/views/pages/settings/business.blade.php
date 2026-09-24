<?php

use App\Support\BrazilianInput;
use App\Rules\ValidBrazilianDocument;
use App\Enums\PlanFeature;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Empresa | Fechou')]
    class extends Component {
    use WithFileUploads;

    public string $name = '';
    public string $document = '';
    public string $email = '';
    public string $phone = '';
    public string $whatsapp = '';

    public string $address = '';
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
         * a assinatura padrão do Fechou.
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

        $this->address = $business->address ?? '';
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
                'nullable',
                'string',
                new ValidBrazilianDocument(),
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

            'address' => [
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

            'address' =>
                $validated['address'] ?: null,

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


    @if (session('success'))

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
                        Conhecer o Fechou Pro

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
                                    data-fechou-mask="document"
                                    inputmode="text"
                                    maxlength="18"
                                    autocomplete="off"
                                >

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
                                    data-fechou-mask="phone"
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
                                    data-fechou-mask="phone"
                                    inputmode="tel"
                                    maxlength="15"
                                    autocomplete="tel"
                                >

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

                <div class="md:col-span-6">

                    <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        Endereço
                    </label>

                    <input type="text" wire:model="address" class="
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


                <div class="md:col-span-3">

                    <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        Cidade
                    </label>

                    <input type="text" wire:model="city" class="
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


                <div class="md:col-span-1">

                    <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        UF
                    </label>

                    <input type="text" wire:model="state" maxlength="2" class="
                            w-full rounded-lg
                            border border-zinc-300
                            bg-white
                            px-3 py-2.5
                            text-sm uppercase text-zinc-900

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-white
                        "
                                    data-fechou-mask="uf"
                                    autocapitalize="characters"
                                >

                </div>


                <div class="md:col-span-2">

                    <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        CEP
                    </label>

                    <input type="text" wire:model="postalCode" class="
                            w-full rounded-lg
                            border border-zinc-300
                            bg-white
                            px-3 py-2.5
                            text-sm text-zinc-900

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-white
                        "
                                    data-fechou-mask="cep"
                                    inputmode="numeric"
                                    maxlength="9"
                                    autocomplete="postal-code"
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


        <div class="flex justify-end">

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