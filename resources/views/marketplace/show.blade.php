@extends('layouts.marketplace')

@section(
    'title',
    $business->name .
    ' em ' .
    $business->city .
    ' | Negozia'
)

@section(
    'description',
    $business->public_description
        ?: 'Conheça ' .
            $business->name .
            ' no Negozia.'
)

@section('content')

<section class="
        border-b border-zinc-200
        bg-white

        dark:border-zinc-800
        dark:bg-zinc-950
    ">
    <div class="
            mx-auto max-w-5xl
            px-5 py-12
            sm:px-6 lg:px-8
        ">

        <a
            href="{{ route('marketplace.index') }}"
            class="
                text-sm font-semibold
                text-zinc-500
                hover:text-emerald-600
            "
        >
            ← Voltar para a busca
        </a>

        <div class="
                mt-7 flex
                flex-col gap-6

                sm:flex-row
                sm:items-start
            ">

            <div class="
                    flex size-24
                    shrink-0
                    items-center
                    justify-center
                    overflow-hidden
                    rounded-2xl
                    border border-zinc-200
                    bg-zinc-50

                    dark:border-zinc-800
                    dark:bg-zinc-900
                ">

                @if ($business->logo_path)
                    <img
                        src="{{ asset(
                            'storage/' .
                            $business->logo_path
                        ) }}"
                        alt="{{ $business->name }}"
                        class="
                            h-full w-full
                            object-contain p-3
                        "
                    >
                @else
                    <span class="
                            text-4xl font-bold
                            text-emerald-600
                        ">
                        {{ mb_strtoupper(
                            mb_substr(
                                $business->name,
                                0,
                                1
                            )
                        ) }}
                    </span>
                @endif

            </div>

            <div class="min-w-0 flex-1">
                <p class="
                        text-sm font-semibold
                        text-emerald-600
                    ">
                    Empresa no Negozia
                </p>

                <h1 class="
                        mt-1
                        text-3xl font-bold
                        tracking-tight

                        sm:text-4xl
                    ">
                    {{ $business->name }}
                </h1>

                <p class="
                        mt-2
                        flex items-center gap-1.5
                        text-zinc-500
                    ">
                    <svg
                        class="size-4 shrink-0"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 21s6-4.35 6-11a6 6 0 1 0-12 0c0 6.65 6 11 6 11Z"
                        />
                        <circle cx="12" cy="10" r="2" />
                    </svg>

                    <span>
                        {{ $business->city }}

                        @if ($business->state)
                            /{{ $business->state }}
                        @endif

                        @if ($business->province)
                            · {{ $business->province }}
                        @endif
                    </span>
                </p>
            </div>

        </div>

    </div>
</section>


<section>
    <div class="
            mx-auto grid
            max-w-5xl gap-6
            px-5 py-10

            sm:px-6

            lg:grid-cols-[1fr_320px]
            lg:px-8
        ">

        <div class="space-y-6">

            @if ($business->public_description)
                <article class="
                        rounded-2xl
                        border border-zinc-200
                        bg-white
                        p-6

                        dark:border-zinc-800
                        dark:bg-zinc-900
                    ">
                    <h2 class="
                            text-lg font-semibold
                        ">
                        Sobre
                    </h2>

                    <p class="
                            mt-3 whitespace-pre-line
                            leading-7
                            text-zinc-600

                            dark:text-zinc-300
                        ">{{ $business->public_description }}</p>
                </article>
            @endif


            @if ($business->public_services)
                <article class="
                        rounded-2xl
                        border border-zinc-200
                        bg-white
                        p-6

                        dark:border-zinc-800
                        dark:bg-zinc-900
                    ">
                    <h2 class="
                            text-lg font-semibold
                        ">
                        Serviços
                    </h2>

                    <div class="
                            mt-4 flex
                            flex-wrap gap-2
                        ">
                        @foreach (
                            array_filter(
                                array_map(
                                    'trim',
                                    explode(
                                        ',',
                                        $business->public_services
                                    )
                                )
                            )
                            as $serviceItem
                        )
                            <span class="
                                    rounded-full
                                    bg-emerald-50
                                    px-3 py-1.5
                                    text-sm font-medium
                                    text-emerald-700

                                    dark:bg-emerald-950/40
                                    dark:text-emerald-300
                                ">
                                {{ $serviceItem }}
                            </span>
                        @endforeach
                    </div>
                </article>
            @endif

        </div>


        <aside>
            <div class="
                    sticky top-24
                    rounded-2xl
                    border border-zinc-200
                    bg-white
                    p-5
                    shadow-sm

                    dark:border-zinc-800
                    dark:bg-zinc-900
                ">

                <h2 class="font-semibold">
                    Fale com a empresa
                </h2>

                <p class="
                        mt-1
                        text-sm leading-6
                        text-zinc-500
                    ">
                    Entre em contato diretamente com
                    {{ $business->name }}.
                </p>

                @if ($business->publicWhatsAppUrl())
                    <a
                        href="{{ $business->publicWhatsAppUrl() }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="
                            mt-5 inline-flex
                            w-full
                            items-center
                            justify-center
                            rounded-xl
                            bg-emerald-600
                            px-4 py-3
                            text-sm font-bold
                            text-white

                            hover:bg-emerald-700
                        "
                    >
                        Chamar no WhatsApp
                    </a>
                @endif

                @if ($business->phone)
                    <a
                        href="tel:{{ preg_replace(
                            '/\D+/',
                            '',
                            $business->phone
                        ) }}"
                        class="
                            mt-2 inline-flex
                            w-full
                            items-center
                            justify-center
                            rounded-xl
                            border border-zinc-200
                            px-4 py-3
                            text-sm font-semibold

                            hover:bg-zinc-50

                            dark:border-zinc-700
                            dark:hover:bg-zinc-800
                        "
                    >
                        {{ $business->publicPhoneDisplay() }}
                    </a>
                @endif

                @if ($business->email)
                    <a
                        href="mailto:{{ $business->email }}"
                        class="
                            mt-2 block
                            break-all
                            text-center
                            text-sm
                            text-zinc-500
                            hover:text-emerald-600
                        "
                    >
                        {{ $business->email }}
                    </a>
                @endif

                <div class="
                        mt-5
                        border-t border-zinc-200
                        pt-4
                        text-xs leading-5
                        text-zinc-400

                        dark:border-zinc-800
                    ">
                    Perfil publicado pela própria empresa
                    no Negozia.
                </div>

            </div>
        </aside>

    </div>
</section>

@endsection
