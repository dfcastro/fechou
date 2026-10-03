<x-layouts::app :title="'Pagamento via Pix | Negozia'">

    @php
        $orderStatus =
            data_get($order, 'status');

        $orderDetail =
            data_get($order, 'status_detail');

        $isPaid =
            $orderStatus === 'processed'
            && $orderDetail === 'accredited';

        $qrCode =
            data_get(
                $paymentMethod,
                'qr_code'
            );

        $qrBase64 =
            data_get(
                $paymentMethod,
                'qr_code_base64'
            );

        $ticketUrl =
            data_get(
                $paymentMethod,
                'ticket_url'
            );

        $amount =
            number_format(
                (float) data_get(
                    $order,
                    'total_amount',
                    29.90
                ),
                2,
                ',',
                '.'
            );
    @endphp

    <div class="
        mx-auto
        w-full max-w-3xl
        space-y-5
    ">

        <div>
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
                ← Voltar para plano e assinatura
            </a>

            <h1 class="
                mt-4
                text-2xl font-semibold
                tracking-tight
                text-zinc-950
                dark:text-white
            ">
                Pagamento via Pix
            </h1>

            <p class="
                mt-1
                text-sm
                text-zinc-500
                dark:text-zinc-400
            ">
                Negozia Pro por 30 dias.
                Pagamento único de R$ {{ $amount }}.
            </p>
        </div>


        @if (session('billing_error'))

            <div class="
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


        @if (session('billing_info'))

            <div class="
                rounded-xl
                border border-blue-200
                bg-blue-50
                px-4 py-3
                text-sm font-medium
                text-blue-800

                dark:border-blue-900
                dark:bg-blue-950/40
                dark:text-blue-300
            ">
                {{ session('billing_info') }}
            </div>

        @endif


        <section class="
            overflow-hidden
            rounded-2xl
            border border-zinc-200
            bg-white
            shadow-sm

            dark:border-zinc-800
            dark:bg-zinc-900
        ">

            <div class="
                flex items-center
                justify-between gap-4
                border-b border-zinc-200
                px-5 py-4

                dark:border-zinc-800
            ">

                <div>
                    <p class="
                        text-xs font-medium
                        text-zinc-500
                        dark:text-zinc-400
                    ">
                        Negozia Pro
                    </p>

                    <p class="
                        mt-0.5
                        text-2xl font-bold
                        tracking-tight
                        text-zinc-950
                        dark:text-white
                    ">
                        R$ {{ $amount }}
                    </p>
                </div>

                @if ($isPaid)

                    <span class="
                        rounded-full
                        bg-emerald-50
                        px-3 py-1
                        text-xs font-semibold
                        text-emerald-700
                        ring-1 ring-inset
                        ring-emerald-600/20

                        dark:bg-emerald-500/10
                        dark:text-emerald-300
                    ">
                        Pix confirmado
                    </span>

                @else

                    <span class="
                        rounded-full
                        bg-amber-50
                        px-3 py-1
                        text-xs font-semibold
                        text-amber-700
                        ring-1 ring-inset
                        ring-amber-600/20

                        dark:bg-amber-500/10
                        dark:text-amber-300
                    ">
                        Aguardando Pix
                    </span>

                @endif

            </div>


            @if ($isPaid)

                <div class="p-6">

                    <div class="
                        rounded-xl
                        border border-emerald-200
                        bg-emerald-50
                        p-5

                        dark:border-emerald-900
                        dark:bg-emerald-950/30
                    ">

                        <div class="
                            flex items-start gap-3
                        ">

                            <div class="
                                flex size-10 shrink-0
                                items-center justify-center
                                rounded-full
                                bg-emerald-100
                                text-emerald-700

                                dark:bg-emerald-500/10
                                dark:text-emerald-300
                            ">
                                <flux:icon.check class="size-5" />
                            </div>

                            <div>
                                <h2 class="
                                    text-sm font-semibold
                                    text-emerald-950
                                    dark:text-emerald-100
                                ">
                                    Pagamento confirmado
                                </h2>

                                <p class="
                                    mt-1
                                    text-sm leading-6
                                    text-emerald-800
                                    dark:text-emerald-200
                                ">
                                    O Mercado Pago já confirmou
                                    este Pix.
                                </p>
                            </div>

                        </div>

                    </div>

                </div>

            @else

                <div class="
                    grid gap-6
                    p-5
                    md:grid-cols-[220px_minmax(0,1fr)]
                    md:items-start
                ">

                    <div>

                        @if ($qrBase64)

                            <div class="
                                mx-auto
                                flex size-[220px]
                                items-center justify-center
                                rounded-2xl
                                border border-zinc-200
                                bg-white
                                p-3
                            ">
                                <img
                                    src="{{
                                        str_starts_with(
                                            $qrBase64,
                                            'data:'
                                        )
                                            ? $qrBase64
                                            : 'data:image/png;base64,'
                                                . $qrBase64
                                    }}"
                                    alt="QR Code Pix"
                                    class="
                                        size-full
                                        object-contain
                                    "
                                >
                            </div>

                        @elseif ($ticketUrl)

                            <div class="
                                flex min-h-[220px]
                                items-center justify-center
                                rounded-2xl
                                border border-emerald-200
                                bg-emerald-50
                                p-5
                                text-center

                                dark:border-emerald-900
                                dark:bg-emerald-950/30
                            ">

                                <div>

                                    <p class="
                                        text-sm
                                        text-emerald-800
                                        dark:text-emerald-200
                                    ">
                                        Abra o QR Code no
                                        Mercado Pago.
                                    </p>

                                    <a
                                        href="{{ $ticketUrl }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="
                                            mt-3
                                            inline-flex
                                            rounded-lg
                                            bg-emerald-600
                                            px-4 py-2.5
                                            text-sm font-semibold
                                            text-white
                                            hover:bg-emerald-700
                                        "
                                    >
                                        Abrir QR Code
                                    </a>

                                </div>

                            </div>

                        @endif

                    </div>


                    <div class="min-w-0">

                        <h2 class="
                            text-base font-semibold
                            text-zinc-950
                            dark:text-white
                        ">
                            Pague com Pix
                        </h2>

                        <ol class="
                            mt-3
                            space-y-2
                            text-sm leading-6
                            text-zinc-500
                            dark:text-zinc-400
                        ">
                            <li>
                                1. Escaneie o QR Code
                                com o aplicativo do seu banco.
                            </li>

                            <li>
                                2. Ou use o código
                                Pix Copia e Cola abaixo.
                            </li>

                            <li>
                                3. Após pagar, a confirmação
                                será processada pelo Negozia.
                            </li>
                        </ol>


                        @if ($qrCode)

                            <div
                                x-data="{
                                    copied: false,

                                    copyPix() {
                                        navigator.clipboard
                                            .writeText(
                                                this.$refs.pixCode.value
                                            );

                                        this.copied = true;

                                        setTimeout(
                                            () => this.copied = false,
                                            2000
                                        );
                                    }
                                }"
                                class="mt-5"
                            >

                                <label class="
                                    text-sm font-semibold
                                    text-zinc-800
                                    dark:text-zinc-200
                                ">
                                    Pix Copia e Cola
                                </label>

                                <textarea
                                    x-ref="pixCode"
                                    readonly
                                    rows="3"
                                    class="
                                        mt-2
                                        block w-full
                                        resize-none
                                        rounded-xl
                                        border border-zinc-300
                                        bg-zinc-50
                                        px-3 py-2.5
                                        text-xs
                                        text-zinc-700

                                        dark:border-zinc-700
                                        dark:bg-zinc-950
                                        dark:text-zinc-300
                                    "
                                >{{ $qrCode }}</textarea>

                                <button
                                    type="button"
                                    x-on:click="copyPix()"
                                    class="
                                        mt-2.5
                                        inline-flex w-full
                                        items-center justify-center
                                        rounded-lg
                                        border border-zinc-200
                                        px-4 py-2.5
                                        text-sm font-semibold
                                        text-zinc-700
                                        transition
                                        hover:bg-zinc-50

                                        dark:border-zinc-700
                                        dark:text-zinc-200
                                        dark:hover:bg-zinc-800
                                    "
                                >
                                    <span
                                        x-text="
                                            copied
                                                ? 'Código copiado!'
                                                : 'Copiar código Pix'
                                        "
                                    ></span>
                                </button>

                            </div>

                        @endif


                        <p class="
                            mt-4
                            text-xs leading-5
                            text-zinc-400
                        ">
                            O Pix libera 30 dias de Negozia Pro
                            e não possui renovação automática.
                        </p>

                    </div>

                </div>

            @endif

        </section>


        <form
            method="POST"
            action="{{
                route(
                    'settings.subscription.pix.check'
                )
            }}"
        >
            @csrf

            <button
                type="submit"
                class="
                    inline-flex w-full
                    items-center justify-center
                    rounded-xl
                    bg-violet-600
                    px-5 py-3
                    text-sm font-semibold
                    text-white
                    shadow-sm
                    transition
                    hover:bg-violet-700

                    dark:bg-violet-500
                    dark:text-zinc-950
                    dark:hover:bg-violet-400
                "
            >
                {{
                    $isPaid
                        ? 'Ativar Negozia Pro'
                        : 'Já paguei, verificar pagamento'
                }}
            </button>
        </form>


        <p class="
            text-center
            text-xs text-zinc-400
        ">
            Pagamento processado com segurança pelo Mercado Pago.
        </p>

    </div>

</x-layouts::app>
