<x-layouts::app :title="'Assinar Negozia Pro | Negozia'">

    <script src="https://sdk.mercadopago.com/js/v2"></script>

    <style>
        .mp-field {
            height: 46px;
            min-height: 46px;
            overflow: hidden;
        }

        .mp-field iframe {
            display: block !important;
            width: 100% !important;
            height: 46px !important;
            min-height: 46px !important;
            border: 0 !important;
        }

        .subscription-checkout-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 24px;
            align-items: start;
        }

        .subscription-summary-card {
            position: relative;
        }

        .subscription-activation-note {
            border: 1px solid rgba(139, 92, 246, 0.24);
            background:
                linear-gradient(
                    135deg,
                    rgba(139, 92, 246, 0.12),
                    rgba(99, 102, 241, 0.07)
                );
            color: #ddd6fe;
        }

        @media (min-width: 900px) {
            .subscription-checkout-grid {
                grid-template-columns:
                    minmax(0, 1fr)
                    340px;
            }

            .subscription-summary-card {
                position: sticky;
                top: 24px;
            }
        }
    </style>


    <div class="
        mx-auto
        w-full max-w-5xl
        pb-10
    ">

        <a
            href="{{ route('settings.subscription') }}"
            class="
                inline-flex items-center gap-2
                text-sm font-medium
                text-zinc-500
                transition
                hover:text-zinc-900

                dark:text-zinc-400
                dark:hover:text-white
            "
        >
            <span>←</span>
            Voltar para plano e assinatura
        </a>


        <div class="
            mt-6
            flex flex-col gap-4
            sm:flex-row
            sm:items-start
            sm:justify-between
        ">

            <div>
                <p class="
                    text-sm font-semibold
                    text-violet-600
                    dark:text-violet-400
                ">
                    Negozia Pro
                </p>

                <h1 class="
                    mt-1
                    text-2xl font-bold
                    tracking-tight
                    text-zinc-950
                    sm:text-3xl

                    dark:text-white
                ">
                    Finalize sua assinatura
                </h1>

                <p class="
                    mt-2
                    max-w-xl
                    text-sm leading-6
                    text-zinc-500

                    dark:text-zinc-400
                ">
                    Cadastre seu cartão para ativar
                    a cobrança mensal recorrente.
                </p>
            </div>


            @if ($isTest)
                <span class="
                    inline-flex w-fit
                    items-center
                    rounded-full
                    bg-amber-50
                    px-3 py-1.5
                    text-xs font-semibold
                    text-amber-700
                    ring-1 ring-inset
                    ring-amber-600/20

                    dark:bg-amber-500/10
                    dark:text-amber-300
                ">
                    Ambiente de teste
                </span>
            @endif

        </div>


        <div class="
            subscription-checkout-grid
            mt-5
        ">

            {{-- FORMULÁRIO --}}
            <div>

                @if (session('billing_error'))
                    <div class="
                        mb-5
                        rounded-xl
                        border border-red-200
                        bg-red-50
                        px-4 py-3
                        text-sm font-medium
                        text-red-800

                        dark:border-red-900
                        dark:bg-red-950/40
                        dark:text-red-300
                    ">
                        {{ session('billing_error') }}
                    </div>
                @endif


                <div class="
                    rounded-2xl
                    border border-zinc-200
                    bg-white
                    shadow-sm

                    dark:border-zinc-800
                    dark:bg-zinc-900
                ">

                    <div class="
                        border-b border-zinc-200
                        px-6 py-5

                        dark:border-zinc-800
                    ">
                        <div class="
                            flex items-center gap-3
                        ">
                            <div class="
                                flex size-10
                                items-center justify-center
                                rounded-xl
                                bg-violet-50
                                text-violet-600

                                dark:bg-violet-500/10
                                dark:text-violet-300
                            ">
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    class="size-5"
                                >
                                    <rect
                                        x="3"
                                        y="5"
                                        width="18"
                                        height="14"
                                        rx="2"
                                    />
                                    <path d="M3 10h18" />
                                </svg>
                            </div>

                            <div>
                                <h2 class="
                                    text-sm font-semibold
                                    text-zinc-950
                                    dark:text-white
                                ">
                                    Dados do cartão
                                </h2>

                                <p class="
                                    mt-0.5
                                    text-xs
                                    text-zinc-500
                                    dark:text-zinc-400
                                ">
                                    Processado com segurança pelo Mercado Pago
                                </p>
                            </div>
                        </div>
                    </div>


                    <form
                        id="form-checkout"
                        method="POST"
                        action="{{
                            route(
                                'settings.subscription.checkout.mercadopago'
                            )
                        }}"
                        class="space-y-5 p-6"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="card_token_id"
                            id="card_token_id"
                        >


                        <div>
                            <label class="
                                block
                                text-sm font-medium
                                text-zinc-700
                                dark:text-zinc-300
                            ">
                                Número do cartão
                            </label>

                            <input
                                id="form-checkout__cardNumber"
                                type="text"
                                inputmode="numeric"
                                autocomplete="cc-number"
                                placeholder="Número do cartão"
                                class="
                                    mt-2 block w-full
                                    rounded-xl
                                    border border-zinc-300
                                    bg-white
                                    px-3.5 py-3
                                    text-sm text-zinc-900
                                    shadow-sm outline-none

                                    focus:border-violet-500
                                    focus:ring-2
                                    focus:ring-violet-500/20

                                    dark:border-zinc-700
                                    dark:bg-zinc-950
                                    dark:text-zinc-100
                                "
                            >
                        </div>


                        <div class="
                            grid gap-4
                            sm:grid-cols-2
                        ">

                            <div>
                                <label class="
                                    block
                                    text-sm font-medium
                                    text-zinc-700
                                    dark:text-zinc-300
                                ">
                                    Validade
                                </label>

                                <div class="
                                    mt-2
                                    grid grid-cols-2 gap-2
                                ">
                                    <input
                                        id="form-checkout__cardExpirationMonth"
                                        type="text"
                                        inputmode="numeric"
                                        autocomplete="cc-exp-month"
                                        maxlength="2"
                                        placeholder="MM"
                                        class="
                                            block w-full
                                            rounded-xl
                                            border border-zinc-300
                                            bg-white
                                            px-3.5 py-3
                                            text-sm text-zinc-900
                                            shadow-sm outline-none

                                            focus:border-violet-500
                                            focus:ring-2
                                            focus:ring-violet-500/20

                                            dark:border-zinc-700
                                            dark:bg-zinc-950
                                            dark:text-zinc-100
                                        "
                                    >

                                    <input
                                        id="form-checkout__cardExpirationYear"
                                        type="text"
                                        inputmode="numeric"
                                        autocomplete="cc-exp-year"
                                        maxlength="2"
                                        placeholder="AA"
                                        class="
                                            block w-full
                                            rounded-xl
                                            border border-zinc-300
                                            bg-white
                                            px-3.5 py-3
                                            text-sm text-zinc-900
                                            shadow-sm outline-none

                                            focus:border-violet-500
                                            focus:ring-2
                                            focus:ring-violet-500/20

                                            dark:border-zinc-700
                                            dark:bg-zinc-950
                                            dark:text-zinc-100
                                        "
                                    >
                                </div>
                            </div>


                            <div>
                                <label class="
                                    block
                                    text-sm font-medium
                                    text-zinc-700
                                    dark:text-zinc-300
                                ">
                                    Código de segurança
                                </label>

                                <input
                                    id="form-checkout__securityCode"
                                    type="text"
                                    inputmode="numeric"
                                    autocomplete="cc-csc"
                                    maxlength="4"
                                    placeholder="CVV"
                                    class="
                                        mt-2 block w-full
                                        rounded-xl
                                        border border-zinc-300
                                        bg-white
                                        px-3.5 py-3
                                        text-sm text-zinc-900
                                        shadow-sm outline-none

                                        focus:border-violet-500
                                        focus:ring-2
                                        focus:ring-violet-500/20

                                        dark:border-zinc-700
                                        dark:bg-zinc-950
                                        dark:text-zinc-100
                                    "
                                >
                            </div>

                        </div>


                        <div>
                            <label
                                for="form-checkout__cardholderName"
                                class="
                                    block
                                    text-sm font-medium
                                    text-zinc-700
                                    dark:text-zinc-300
                                "
                            >
                                Nome impresso no cartão
                            </label>

                            <input
                                id="form-checkout__cardholderName"
                                type="text"
                                autocomplete="cc-name"
                                required
                                placeholder="Nome do titular"
                                class="
                                    mt-2 block w-full
                                    rounded-xl
                                    border border-zinc-300
                                    bg-white
                                    px-3.5 py-3
                                    text-sm text-zinc-900
                                    shadow-sm outline-none

                                    focus:border-violet-500
                                    focus:ring-2
                                    focus:ring-violet-500/20

                                    dark:border-zinc-700
                                    dark:bg-zinc-950
                                    dark:text-zinc-100
                                "
                            >
                        </div>


                        <div class="
                            grid gap-4
                            sm:grid-cols-[140px_minmax(0,1fr)]
                        ">

                            <div>
                                <label
                                    for="form-checkout__identificationType"
                                    class="
                                        block
                                        text-sm font-medium
                                        text-zinc-700
                                        dark:text-zinc-300
                                    "
                                >
                                    Documento
                                </label>

                                <select
                                    id="form-checkout__identificationType"
                                    required
                                    class="
                                        mt-2 block w-full
                                        rounded-xl
                                        border border-zinc-300
                                        bg-white
                                        px-3.5 py-3
                                        text-sm text-zinc-900
                                        shadow-sm outline-none

                                        focus:border-violet-500
                                        focus:ring-2
                                        focus:ring-violet-500/20

                                        dark:border-zinc-700
                                        dark:bg-zinc-950
                                        dark:text-zinc-100
                                    "
                                ></select>
                            </div>


                            <div>
                                <label
                                    for="form-checkout__identificationNumber"
                                    class="
                                        block
                                        text-sm font-medium
                                        text-zinc-700
                                        dark:text-zinc-300
                                    "
                                >
                                    Número do documento
                                </label>

                                <input
                                    id="form-checkout__identificationNumber"
                                    type="text"
                                    inputmode="numeric"
                                    required
                                    placeholder="CPF"
                                    class="
                                        mt-2 block w-full
                                        rounded-xl
                                        border border-zinc-300
                                        bg-white
                                        px-3.5 py-3
                                        text-sm text-zinc-900
                                        shadow-sm outline-none

                                        focus:border-violet-500
                                        focus:ring-2
                                        focus:ring-violet-500/20

                                        dark:border-zinc-700
                                        dark:bg-zinc-950
                                        dark:text-zinc-100
                                    "
                                >
                            </div>

                        </div>


                        <div>
                            <label
                                for="form-checkout__cardholderEmail"
                                class="
                                    block
                                    text-sm font-medium
                                    text-zinc-700
                                    dark:text-zinc-300
                                "
                            >
                                E-mail
                            </label>

                            <input
                                id="form-checkout__cardholderEmail"
                                type="email"
                                value="{{ $payerEmail }}"
                                required
                                class="
                                    mt-2 block w-full
                                    rounded-xl
                                    border border-zinc-300
                                    bg-white
                                    px-3.5 py-3
                                    text-sm text-zinc-900
                                    shadow-sm outline-none

                                    focus:border-violet-500
                                    focus:ring-2
                                    focus:ring-violet-500/20

                                    dark:border-zinc-700
                                    dark:bg-zinc-950
                                    dark:text-zinc-100
                                "
                            >
                        </div>


                        <div class="hidden">
                            <select
                                id="form-checkout__issuer"
                            ></select>

                            <select
                                id="form-checkout__installments"
                            ></select>
                        </div>


                        <div
                            id="card-error"
                            class="
                                hidden
                                rounded-xl
                                border border-red-200
                                bg-red-50
                                px-4 py-3
                                text-sm
                                text-red-800

                                dark:border-red-900
                                dark:bg-red-950/40
                                dark:text-red-300
                            "
                        ></div>


                        <button
                            type="submit"
                            id="form-checkout__submit"
                            class="
                                inline-flex w-full
                                items-center justify-center
                                rounded-xl
                                bg-violet-600
                                px-5 py-3.5
                                text-sm font-semibold
                                text-white
                                shadow-sm
                                transition

                                hover:bg-violet-700

                                disabled:cursor-not-allowed
                                disabled:opacity-60

                                dark:bg-violet-500
                                dark:text-zinc-950
                                dark:hover:bg-violet-400
                            "
                        >
                            Assinar por R$ {{
                                number_format(
                                    (float) $amount,
                                    2,
                                    ',',
                                    '.'
                                )
                            }}/mês
                        </button>


                        <div class="
                            flex items-start gap-2
                            rounded-xl
                            bg-zinc-50
                            px-4 py-3

                            dark:bg-zinc-950/60
                        ">
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                class="
                                    mt-0.5 size-4 shrink-0
                                    text-zinc-400
                                "
                            >
                                <rect
                                    x="5"
                                    y="10"
                                    width="14"
                                    height="10"
                                    rx="2"
                                />
                                <path
                                    d="M8 10V7a4 4 0 0 1 8 0v3"
                                />
                            </svg>

                            <p class="
                                text-xs leading-5
                                text-zinc-500
                                dark:text-zinc-400
                            ">
                                Seus dados de cartão são enviados
                                diretamente ao Mercado Pago.
                                O Negozia não armazena número
                                do cartão nem CVV.
                            </p>
                        </div>

                    </form>

                </div>

            </div>


            {{-- RESUMO --}}
            <aside class="
                subscription-summary-card
                rounded-2xl
                border border-zinc-200
                bg-white
                p-6
                shadow-sm

                dark:border-zinc-800
                dark:bg-zinc-900
            ">

                <p class="
                    text-xs font-semibold uppercase
                    tracking-wider
                    text-zinc-400
                ">
                    Sua assinatura
                </p>

                <div class="mt-4">
                    <h2 class="
                        text-lg font-bold
                        text-zinc-950
                        dark:text-white
                    ">
                        Negozia Pro
                    </h2>

                    <div class="
                        mt-2
                        flex items-end gap-1
                    ">
                        <span class="
                            text-3xl font-bold
                            tracking-tight
                            text-zinc-950
                            dark:text-white
                        ">
                            R$ {{
                                number_format(
                                    (float) $amount,
                                    2,
                                    ',',
                                    '.'
                                )
                            }}
                        </span>

                        <span class="
                            pb-1
                            text-sm
                            text-zinc-500
                            dark:text-zinc-400
                        ">
                            /mês
                        </span>
                    </div>
                </div>


                <div class="
                    my-5
                    h-px
                    bg-zinc-200
                    dark:bg-zinc-800
                "></div>


                <ul class="
                    space-y-3
                    text-sm
                    text-zinc-600

                    dark:text-zinc-300
                ">

                    <li class="
                        flex items-start gap-2.5
                    ">
                        <span class="
                            mt-0.5
                            flex size-5 shrink-0
                            items-center justify-center
                            rounded-full
                            bg-emerald-50
                            text-xs font-bold
                            text-emerald-600

                            dark:bg-emerald-500/10
                            dark:text-emerald-300
                        ">
                            ✓
                        </span>

                        Cobrança automática mensal
                    </li>


                    <li class="
                        flex items-start gap-2.5
                    ">
                        <span class="
                            mt-0.5
                            flex size-5 shrink-0
                            items-center justify-center
                            rounded-full
                            bg-emerald-50
                            text-xs font-bold
                            text-emerald-600

                            dark:bg-emerald-500/10
                            dark:text-emerald-300
                        ">
                            ✓
                        </span>

                        Cancele quando quiser
                    </li>


                    <li class="
                        flex items-start gap-2.5
                    ">
                        <span class="
                            mt-0.5
                            flex size-5 shrink-0
                            items-center justify-center
                            rounded-full
                            bg-emerald-50
                            text-xs font-bold
                            text-emerald-600

                            dark:bg-emerald-500/10
                            dark:text-emerald-300
                        ">
                            ✓
                        </span>

                        Acesso aos recursos Pro
                    </li>


                    <li class="
                        flex items-start gap-2.5
                    ">
                        <span class="
                            mt-0.5
                            flex size-5 shrink-0
                            items-center justify-center
                            rounded-full
                            bg-emerald-50
                            text-xs font-bold
                            text-emerald-600

                            dark:bg-emerald-500/10
                            dark:text-emerald-300
                        ">
                            ✓
                        </span>

                        Pagamento pelo Mercado Pago
                    </li>

                </ul>


                <div class="
                    subscription-activation-note
                    mt-6
                    rounded-xl
                    p-4
                ">
                    <p class="
                        text-xs
                        font-medium
                        leading-5
                    ">
                        Sua assinatura só será ativada
                        depois da confirmação da primeira cobrança.
                    </p>
                </div>

            </aside>

        </div>

    </div>


    <script>
        document.addEventListener(
            'DOMContentLoaded',
            () => {
                const errorBox =
                    document.getElementById(
                        'card-error'
                    );

                const submitButton =
                    document.getElementById(
                        'form-checkout__submit'
                    );

                const form =
                    document.getElementById(
                        'form-checkout'
                    );

                const showError = (
                    message
                ) => {
                    errorBox.textContent =
                        message;

                    errorBox.classList.remove(
                        'hidden'
                    );

                    submitButton.disabled =
                        false;

                    submitButton.textContent =
                        'Tentar novamente';
                };

                try {
                    const mp =
                        new MercadoPago(
                            @json($publicKey),
                            {
                                locale: 'pt-BR'
                            }
                        );

                    const cardForm =
                        mp.cardForm({
                            amount:
                                @json($amount),

                            autoMount: true,

                            form: {
                                id:
                                    'form-checkout',

                                cardNumber: {
                                    id:
                                        'form-checkout__cardNumber',
                                    placeholder:
                                        'Número do cartão'
                                },

                                cardExpirationMonth: {
                                    id:
                                        'form-checkout__cardExpirationMonth',
                                    placeholder:
                                        'MM'
                                },

                                cardExpirationYear: {
                                    id:
                                        'form-checkout__cardExpirationYear',
                                    placeholder:
                                        'AA'
                                },

                                securityCode: {
                                    id:
                                        'form-checkout__securityCode',
                                    placeholder:
                                        'CVV'
                                },

                                cardholderName: {
                                    id:
                                        'form-checkout__cardholderName',
                                    placeholder:
                                        'Nome do titular'
                                },

                                issuer: {
                                    id:
                                        'form-checkout__issuer'
                                },

                                installments: {
                                    id:
                                        'form-checkout__installments'
                                },

                                identificationType: {
                                    id:
                                        'form-checkout__identificationType'
                                },

                                identificationNumber: {
                                    id:
                                        'form-checkout__identificationNumber',
                                    placeholder:
                                        'CPF'
                                },

                                cardholderEmail: {
                                    id:
                                        'form-checkout__cardholderEmail',
                                    placeholder:
                                        'E-mail'
                                }
                            },

                            callbacks: {
                                onFormMounted: (
                                    error
                                ) => {
                                    if (error) {
                                        showError(
                                            'Não foi possível carregar o formulário do Mercado Pago.'
                                        );
                                    }
                                },

                                onSubmit: (
                                    event
                                ) => {
                                    event.preventDefault();

                                    errorBox.classList.add(
                                        'hidden'
                                    );

                                    submitButton.disabled =
                                        true;

                                    submitButton.textContent =
                                        'Processando...';

                                    const data =
                                        cardForm
                                            .getCardFormData();

                                    if (! data.token) {
                                        showError(
                                            'Confira os dados do cartão e tente novamente.'
                                        );

                                        return;
                                    }

                                    document
                                        .getElementById(
                                            'card_token_id'
                                        )
                                        .value =
                                            data.token;

                                    [
                                        'form-checkout__cardNumber',
                                        'form-checkout__cardExpirationMonth',
                                        'form-checkout__cardExpirationYear',
                                        'form-checkout__securityCode'
                                    ].forEach(
                                        (id) => {
                                            const field =
                                                document.getElementById(id);

                                            if (field) {
                                                field.disabled = true;
                                            }
                                        }
                                    );

                                    form.submit();
                                }
                            }
                        });

                } catch (error) {
                    showError(
                        'Não foi possível iniciar o Mercado Pago.'
                    );
                }
            }
        );
    </script>

</x-layouts::app>
