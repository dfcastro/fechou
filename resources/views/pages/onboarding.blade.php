<?php

use App\Support\BrazilianInput;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Primeiros passos | Fechou')]
    class extends Component {

    public string $name = '';
    public string $document = '';
    public string $whatsapp = '';
    public string $pixKey = '';

    public function mount(
        SubscriptionService $subscriptionService
    ): void {
        $user = Auth::user();

        /*
         * Fallback para contas antigas/inconsistentes.
         * Novos cadastros já criam a empresa automaticamente.
         */
        $business = $user
            ->business()
            ->firstOrCreate(
                [],
                [
                    'name' => $user->name,
                    'email' => $user->email,
                ]
            );

        $user->setRelation(
            'business',
            $business
        );

        $subscriptionService
            ->ensureDefaultSubscription(
                $business
            );

        /*
         * Quem já concluiu não precisa rever o onboarding
         * a cada login.
         */
        if ($business->onboarding_completed_at) {
            $this->redirectRoute(
                'dashboard',
                navigate: true
            );

            return;
        }

        $this->name =
            $business->name ?: $user->name;

        $this->document =
            $business->document ?? '';

        $this->whatsapp =
            $business->whatsapp
            ?: $business->phone
            ?: '';

        $this->pixKey =
            $business->pix_key ?? '';
    }

    public function save(): void
    {
        /* FECHOU: NORMALIZAÇÃO DE CAMPOS */
        $this->document =
            BrazilianInput::document(
                $this->document
            ) ?? '';

        $this->whatsapp =
            BrazilianInput::phone(
                $this->whatsapp
            ) ?? '';

        $user = Auth::user();
        $business = $user->business;

        abort_unless(
            $business,
            403
        );

        $validated = $this->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'document' => [
                'required',
                'string',
                'max:20',
            ],

            'whatsapp' => [
                'required',
                'string',
                'max:30',
            ],

            'pixKey' => [
                'nullable',
                'string',
                'max:255',
            ],
        ], [
            'name.required' =>
                'Informe o nome da sua empresa ou atividade.',

            'document.required' =>
                'Informe seu CPF ou CNPJ.',

            'whatsapp.required' =>
                'Informe um WhatsApp para contato.',
        ]);

        $business->update([
            'name' =>
                trim($validated['name']),

            'document' =>
                trim($validated['document']),

            'email' =>
                $business->email
                ?: $user->email,

            'whatsapp' =>
                trim($validated['whatsapp']),

            'pix_key' =>
                $validated['pixKey']
                ? trim($validated['pixKey'])
                : null,

            'onboarding_completed_at' =>
                now(),
        ]);

        $this->redirectRoute(
            'dashboard',
            navigate: true
        );
    }
};
?>

<div class="mx-auto max-w-3xl space-y-6">

    <div class="
        overflow-hidden
        rounded-2xl
        border border-zinc-200
        bg-white
        shadow-sm

        dark:border-zinc-800
        dark:bg-zinc-900
    ">

        <div class="h-1.5 bg-emerald-500"></div>

        <div class="p-6 sm:p-8">

            <div class="flex items-start gap-4">

                <div class="
                    flex size-11 shrink-0
                    items-center justify-center

                    rounded-xl

                    bg-emerald-100
                    text-lg font-bold
                    text-emerald-700

                    dark:bg-emerald-500/10
                    dark:text-emerald-400
                ">
                    F
                </div>

                <div class="min-w-0">

                    <p class="
                        text-xs font-semibold
                        uppercase tracking-[0.18em]
                        text-emerald-600

                        dark:text-emerald-400
                    ">
                        Primeiros passos
                    </p>

                    <h1 class="
                        mt-1
                        text-2xl font-semibold
                        tracking-tight
                        text-zinc-950

                        dark:text-white
                    ">
                        Vamos preparar suas propostas
                    </h1>

                    <p class="
                        mt-2
                        max-w-2xl
                        text-sm leading-6
                        text-zinc-500

                        dark:text-zinc-400
                    ">
                        Preencha os dados básicos que identificam você
                        nos orçamentos. Depois disso, o Fechou já estará
                        pronto para criar sua primeira proposta.
                    </p>

                </div>

            </div>


            <div class="
                mt-6
                rounded-xl
                border border-zinc-200
                bg-zinc-50
                p-4

                dark:border-zinc-800
                dark:bg-zinc-950/50
            ">

                <div class="
                    flex items-center
                    justify-between gap-4
                ">

                    <div>

                        <p class="
                            text-sm font-semibold
                            text-zinc-800

                            dark:text-zinc-200
                        ">
                            Configuração inicial
                        </p>

                        <p class="
                            mt-0.5
                            text-xs
                            text-zinc-500

                            dark:text-zinc-400
                        ">
                            Leva menos de 1 minuto.
                        </p>

                    </div>

                    <span class="
                        rounded-full
                        bg-emerald-100
                        px-2.5 py-1

                        text-xs font-semibold
                        text-emerald-700

                        dark:bg-emerald-500/10
                        dark:text-emerald-400
                    ">
                        1 de 1
                    </span>

                </div>

            </div>


            <form
                wire:submit="save"
                class="mt-7 space-y-5"
            >

                <div>

                    <label
                        for="onboarding-name"
                        class="
                            block
                            text-sm font-medium
                            text-zinc-700

                            dark:text-zinc-300
                        "
                    >
                        Nome da empresa ou atividade
                    </label>

                    <input
                        id="onboarding-name"
                        type="text"
                        wire:model="name"
                        autocomplete="organization"

                        placeholder="Ex.: ClimaTech Refrigeração"

                        class="
                            mt-2 block w-full
                            rounded-xl
                            border border-zinc-300
                            bg-white
                            px-3.5 py-3

                            text-sm text-zinc-900
                            shadow-sm outline-none

                            placeholder:text-zinc-400

                            focus:border-emerald-500
                            focus:ring-2
                            focus:ring-emerald-500/20

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-zinc-100
                        "
                    >

                    @error('name')
                        <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">
                            {{ $message }}
                        </p>
                    @enderror

                </div>


                <div class="grid gap-5 sm:grid-cols-2">

                    <div>

                        <label
                            for="onboarding-document"
                            class="
                                block
                                text-sm font-medium
                                text-zinc-700

                                dark:text-zinc-300
                            "
                        >
                            CPF ou CNPJ
                        </label>

                        <input
                            id="onboarding-document"
                            type="text"
                            wire:model="document"
                            inputmode="numeric"

                            placeholder="CPF ou CNPJ"

                            class="
                                mt-2 block w-full
                                rounded-xl
                                border border-zinc-300
                                bg-white
                                px-3.5 py-3

                                text-sm text-zinc-900
                                shadow-sm outline-none

                                placeholder:text-zinc-400

                                focus:border-emerald-500
                                focus:ring-2
                                focus:ring-emerald-500/20

                                dark:border-zinc-700
                                dark:bg-zinc-950
                                dark:text-zinc-100
                            "

                                    data-fechou-mask="document"
                                    maxlength="18"
                                    autocomplete="off"
                                >

                        @error('document')
                            <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">
                                {{ $message }}
                            </p>
                        @enderror

                    </div>


                    <div>

                        <label
                            for="onboarding-whatsapp"
                            class="
                                block
                                text-sm font-medium
                                text-zinc-700

                                dark:text-zinc-300
                            "
                        >
                            WhatsApp
                        </label>

                        <input
                            id="onboarding-whatsapp"
                            type="text"
                            wire:model="whatsapp"
                            inputmode="tel"
                            autocomplete="tel"

                            placeholder="(33) 99999-9999"

                            class="
                                mt-2 block w-full
                                rounded-xl
                                border border-zinc-300
                                bg-white
                                px-3.5 py-3

                                text-sm text-zinc-900
                                shadow-sm outline-none

                                placeholder:text-zinc-400

                                focus:border-emerald-500
                                focus:ring-2
                                focus:ring-emerald-500/20

                                dark:border-zinc-700
                                dark:bg-zinc-950
                                dark:text-zinc-100
                            "

                                    data-fechou-mask="phone"
                                    maxlength="15"
                                >

                        @error('whatsapp')
                            <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">
                                {{ $message }}
                            </p>
                        @enderror

                    </div>

                </div>


                <div>

                    <div class="
                        flex items-center
                        justify-between gap-3
                    ">

                        <label
                            for="onboarding-pix"
                            class="
                                block
                                text-sm font-medium
                                text-zinc-700

                                dark:text-zinc-300
                            "
                        >
                            Chave PIX
                        </label>

                        <span class="
                            text-xs
                            text-zinc-400
                            dark:text-zinc-500
                        ">
                            Opcional
                        </span>

                    </div>

                    <input
                        id="onboarding-pix"
                        type="text"
                        wire:model="pixKey"

                        placeholder="CPF, CNPJ, e-mail, telefone ou chave aleatória"

                        class="
                            mt-2 block w-full
                            rounded-xl
                            border border-zinc-300
                            bg-white
                            px-3.5 py-3

                            text-sm text-zinc-900
                            shadow-sm outline-none

                            placeholder:text-zinc-400

                            focus:border-emerald-500
                            focus:ring-2
                            focus:ring-emerald-500/20

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-zinc-100
                        "
                    >

                    @error('pixKey')
                        <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">
                            {{ $message }}
                        </p>
                    @enderror

                    <p class="
                        mt-2
                        text-xs leading-5
                        text-zinc-500

                        dark:text-zinc-400
                    ">
                        Você poderá alterar esses dados depois em
                        <strong>Minha empresa</strong>.
                    </p>

                </div>


                <div class="
                    flex flex-col gap-3
                    border-t border-zinc-200
                    pt-6

                    sm:flex-row
                    sm:items-center
                    sm:justify-between

                    dark:border-zinc-800
                ">

                    <p class="
                        text-xs leading-5
                        text-zinc-500

                        dark:text-zinc-400
                    ">
                        Seu plano Free já está ativo com
                        <strong>5 propostas por mês</strong>.
                    </p>

                    <button
                        type="submit"
                        wire:loading.attr="disabled"

                        class="
                            inline-flex
                            items-center justify-center
                            gap-2

                            rounded-xl
                            bg-emerald-600

                            px-5 py-3

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
                        "
                    >
                        <span wire:loading.remove wire:target="save">
                            Salvar e começar
                        </span>

                        <span wire:loading wire:target="save">
                            Salvando...
                        </span>
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>
