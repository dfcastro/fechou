<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Cobrança | Fechou')]
    class extends Component {

    public bool $paymentCollectionEnabled = false;

    public string $pixKey = '';

    public string $paymentInstructions = '';


    public function mount(): void
    {
        $business =
            Auth::user()?->business;

        abort_unless(
            $business,
            403
        );


        $this->paymentCollectionEnabled =
            (bool) $business
                ->payment_collection_enabled;

        $this->pixKey =
            $business->pix_key
            ?? '';

        $this->paymentInstructions =
            $business->payment_instructions
            ?? '';
    }


    public function save(): void
    {
        $business =
            Auth::user()?->business;

        abort_unless(
            $business,
            403
        );


        $validated =
            $this->validate([
                'paymentCollectionEnabled' => [
                    'boolean',
                ],

                'pixKey' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'paymentInstructions' => [
                    'nullable',
                    'string',
                    'max:5000',
                ],
            ]);


        if (
            $validated[
                'paymentCollectionEnabled'
            ]
            && blank(
                $validated['pixKey']
            )
            && blank(
                $validated[
                    'paymentInstructions'
                ]
            )
        ) {
            $this->addError(
                'paymentInstructions',
                'Informe uma chave Pix ou alguma instrução de pagamento.'
            );

            return;
        }


        $business->update([
            'payment_collection_enabled' =>
                $validated[
                    'paymentCollectionEnabled'
                ],

            'pix_key' =>
                blank(
                    $validated['pixKey']
                )
                    ? null
                    : trim(
                        $validated['pixKey']
                    ),

            'payment_instructions' =>
                blank(
                    $validated[
                        'paymentInstructions'
                    ]
                )
                    ? null
                    : trim(
                        $validated[
                            'paymentInstructions'
                        ]
                    ),
        ]);


        session()->flash(
            'success',
            'Configurações de cobrança atualizadas.'
        );
    }
};
?>


<div
    class="
        mx-auto
        w-full
        max-w-6xl
        space-y-5
    "
>

    <div>

        <h1
            class="
                text-2xl
                font-semibold
                tracking-tight

                text-zinc-950
                dark:text-white
            "
        >
            Cobrança
        </h1>


        <p
            class="
                mt-1

                text-sm
                leading-6

                text-zinc-500
                dark:text-zinc-400
            "
        >
            Recurso opcional para compartilhar
            dados de pagamento e enviar lembretes
            após o aceite de uma proposta.
        </p>

    </div>


    @if (session('success'))

        <div
            class="
                max-w-3xl

                rounded-xl

                border
                border-emerald-200

                bg-emerald-50

                px-4
                py-3

                text-sm
                font-medium

                text-emerald-800

                dark:border-emerald-900
                dark:bg-emerald-950/40
                dark:text-emerald-300
            "
        >
            {{ session('success') }}
        </div>

    @endif


    <form
        wire:submit="save"

        class="
            max-w-3xl
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

        <div
            class="
                border-b
                border-zinc-200

                p-5

                dark:border-zinc-800
            "
        >

            <label
                class="
                    flex
                    cursor-pointer
                    items-start
                    gap-3
                "
            >

                <input
                    type="checkbox"

                    wire:model="paymentCollectionEnabled"

                    class="
                        mt-1

                        size-4

                        rounded

                        border-zinc-300

                        text-emerald-600

                        focus:ring-emerald-500
                    "
                />


                <span>

                    <span
                        class="
                            block

                            font-semibold

                            text-zinc-950
                            dark:text-white
                        "
                    >
                        Usar recursos de cobrança
                    </span>


                    <span
                        class="
                            mt-1
                            block

                            text-sm
                            leading-6

                            text-zinc-500
                            dark:text-zinc-400
                        "
                    >
                        Quando ativado, você ainda
                        escolhe individualmente em quais
                        propostas deseja utilizar a cobrança.
                    </span>

                </span>

            </label>

        </div>


        <div
            class="
                space-y-4
                p-5
            "
        >

            <div>

                <label
                    for="payment-pix-key"

                    class="
                        text-sm
                        font-semibold

                        text-zinc-700
                        dark:text-zinc-200
                    "
                >
                    Chave Pix
                </label>


                <input
                    id="payment-pix-key"

                    type="text"

                    wire:model="pixKey"

                    placeholder="
                        CPF, CNPJ, e-mail,
                        telefone ou chave aleatória
                    "

                    class="
                        mt-2
                        w-full

                        rounded-lg

                        border
                        border-zinc-300

                        bg-white

                        px-3
                        py-2.5

                        text-sm

                        text-zinc-900

                        dark:border-zinc-700
                        dark:bg-zinc-950
                        dark:text-white
                    "
                />

                @error('pixKey')

                    <p
                        class="
                            mt-1
                            text-xs
                            text-red-600
                        "
                    >
                        {{ $message }}
                    </p>

                @enderror

            </div>


            <div>

                <label
                    for="payment-instructions"

                    class="
                        text-sm
                        font-semibold

                        text-zinc-700
                        dark:text-zinc-200
                    "
                >
                    Instruções de pagamento
                </label>


                <textarea
                    id="payment-instructions"

                    rows="5"

                    wire:model="paymentInstructions"

                    placeholder="Ex.: Após o pagamento, envie o comprovante pelo WhatsApp."
                    class="
                        mt-2
                        w-full

                        resize-y

                        rounded-lg

                        border
                        border-zinc-300

                        bg-white

                        px-3
                        py-2.5

                        text-sm
                        leading-6

                        text-zinc-900

                        dark:border-zinc-700
                        dark:bg-zinc-950
                        dark:text-white
                    "
                ></textarea>

                @error('paymentInstructions')

                    <p
                        class="
                            mt-1
                            text-xs
                            text-red-600
                        "
                    >
                        {{ $message }}
                    </p>

                @enderror

            </div>


            <div
                class="
                    rounded-xl

                    bg-zinc-50

                    p-4

                    text-xs
                    leading-5

                    text-zinc-500

                    dark:bg-zinc-950/50
                    dark:text-zinc-400
                "
            >
                Desativar este recurso não altera
                pagamentos já registrados e não interfere
                em execução, conclusão ou no Pipeline.
                Você continua podendo marcar propostas
                como pagas normalmente.
            </div>

        </div>


        <div
            class="
                flex
                flex-wrap
                items-center
                justify-between
                gap-3

                border-t
                border-zinc-200

                bg-zinc-50/70

                px-5
                py-3.5

                dark:border-zinc-800
                dark:bg-zinc-950/30
            "
        >

            <a
                href="{{
                    route(
                        'settings.business'
                    )
                }}"

                wire:navigate

                class="
                    text-sm
                    font-semibold

                    text-zinc-500

                    hover:text-zinc-700

                    dark:text-zinc-400
                    dark:hover:text-zinc-200
                "
            >
                Voltar para empresa
            </a>


            <button
                type="submit"

                class="
                    rounded-lg

                    bg-emerald-600

                    px-4
                    py-2.5

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
                Salvar cobrança
            </button>

        </div>

    </form>

</div>
