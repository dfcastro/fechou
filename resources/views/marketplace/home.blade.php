@extends('layouts.marketplace')

@section(
    'title',
    'Negozia — Encontre empresas e serviços na sua cidade'
)

@section(
    'description',
    'Pesquise empresas e profissionais da sua cidade e fale diretamente com quem pode resolver o que você precisa.'
)

@section('content')

<section class="
        relative overflow-hidden
        border-b border-zinc-200
        bg-white

        dark:border-zinc-800
        dark:bg-zinc-950
    ">

    <div class="
            pointer-events-none
            absolute inset-x-0 top-0
            -z-0
            h-full
            bg-gradient-to-b
            from-emerald-50
            via-white
            to-white

            dark:from-emerald-950/25
            dark:via-zinc-950
            dark:to-zinc-950
        ">
    </div>

    <div class="
            relative z-10
            mx-auto
            max-w-7xl
            px-5 py-14
            text-center

            sm:px-6
            sm:py-16

            lg:px-8
            lg:py-20
        ">

        <div class="
                mx-auto
                inline-flex
                items-center gap-2
                rounded-full
                border border-emerald-200
                bg-emerald-50
                px-3 py-1.5
                text-xs font-semibold
                text-emerald-700

                dark:border-emerald-900
                dark:bg-emerald-950/50
                dark:text-emerald-300
            ">
            <span class="
                    size-2 rounded-full
                    bg-emerald-500
                ">
            </span>

            Empresas e profissionais perto de você
        </div>

        <h1 class="
                mx-auto mt-6
                max-w-4xl
                text-4xl font-bold
                tracking-tight

                sm:text-5xl
                lg:text-6xl
            ">
            Encontre quem faz
            <span class="text-emerald-600">
                o que você precisa.
            </span>
        </h1>

        <p class="
                mx-auto mt-5
                max-w-2xl
                text-base leading-7
                text-zinc-600

                sm:text-lg

                dark:text-zinc-300
            ">
            Pesquise serviços na sua cidade, conheça empresas
            e fale diretamente com quem pode atender você.
        </p>

        <form
            action="{{ route('marketplace.index') }}"
            method="GET"
            class="
                mx-auto mt-9
                grid max-w-4xl gap-3
                rounded-2xl
                border border-zinc-200
                bg-white
                p-3
                text-left
                shadow-xl
                shadow-zinc-950/5

                md:grid-cols-[1fr_1fr_auto]

                dark:border-zinc-800
                dark:bg-zinc-900
            "
        >
            <div>
                <label
                    for="home-service"
                    class="
                        mb-1.5 block
                        px-1
                        text-xs font-semibold
                        text-zinc-500
                    "
                >
                    O que você procura?
                </label>

                <input
                    id="home-service"
                    type="search"
                    name="servico"
                    placeholder="Ex.: eletricista, advogado, fotografia"
                    class="
                        w-full rounded-xl
                        border border-zinc-200
                        bg-zinc-50
                        px-4 py-3
                        text-sm

                        focus:border-emerald-500
                        focus:ring-2
                        focus:ring-emerald-500/20

                        dark:border-zinc-700
                        dark:bg-zinc-950
                    "
                >
            </div>

            <div>
                <label
                    for="home-city"
                    class="
                        mb-1.5 block
                        px-1
                        text-xs font-semibold
                        text-zinc-500
                    "
                >
                    Em qual cidade?
                </label>

                <input
                    id="home-city"
                    type="search"
                    name="cidade"
                    placeholder="Ex.: Almenara"
                    class="
                        w-full rounded-xl
                        border border-zinc-200
                        bg-zinc-50
                        px-4 py-3
                        text-sm

                        focus:border-emerald-500
                        focus:ring-2
                        focus:ring-emerald-500/20

                        dark:border-zinc-700
                        dark:bg-zinc-950
                    "
                >
            </div>

            <button
                type="submit"
                class="
                    self-end
                    rounded-xl
                    bg-emerald-600
                    px-6 py-3
                    text-sm font-bold
                    text-white
                    shadow-sm
                    transition
                    hover:bg-emerald-700
                "
            >
                Buscar empresas
            </button>
        </form>

        <div class="
                mt-5 flex
                flex-wrap
                justify-center
                gap-2
                text-xs
                text-zinc-500
            ">
            <span>Buscas populares:</span>

            @foreach ([
                'Eletricista',
                'Fotografia',
                'Contabilidade',
                'Manutenção',
                'Design',
            ] as $suggestion)
                <a
                    href="{{ route(
                        'marketplace.index',
                        ['servico' => $suggestion]
                    ) }}"
                    class="
                        font-semibold
                        text-zinc-700
                        hover:text-emerald-600

                        dark:text-zinc-300
                    "
                >
                    {{ $suggestion }}
                </a>
            @endforeach
        </div>

    </div>
</section>



<section class="
        border-b border-zinc-200
        bg-white

        dark:border-zinc-800
        dark:bg-zinc-950
    ">
    <div class="
            mx-auto max-w-7xl
            px-5 py-14

            sm:px-6
            lg:px-8
        ">

        <div class="text-center">
            <p class="
                    text-sm font-semibold
                    text-emerald-600
                ">
                Explore por categoria
            </p>

            <h2 class="
                    mt-1
                    text-2xl font-bold
                    tracking-tight

                    sm:text-3xl
                ">
                O que você precisa hoje?
            </h2>
        </div>

        <div class="
                mx-auto mt-8
                grid max-w-5xl
                grid-cols-2 gap-3

                sm:grid-cols-4
            ">

            @foreach ([
                ['Eletricista', 'electric'],
                ['Manutenção', 'maintenance'],
                ['Tecnologia', 'technology'],
                ['Fotografia', 'photography'],
                ['Contabilidade', 'accounting'],
                ['Design', 'design'],
                ['Construção', 'construction'],
                ['Automotivo', 'automotive'],
            ] as [$category, $icon])

                <a
                    href="{{ route(
                        'marketplace.index',
                        ['servico' => $category]
                    ) }}"
                    class="
                        group
                        flex items-center gap-3
                        rounded-2xl
                        border border-zinc-200
                        bg-zinc-50
                        p-4
                        text-left
                        transition

                        hover:-translate-y-0.5
                        hover:border-emerald-300
                        hover:bg-emerald-50
                        hover:shadow-sm

                        dark:border-zinc-800
                        dark:bg-zinc-900
                        dark:hover:border-emerald-900
                        dark:hover:bg-emerald-950/20
                    "
                >
                    <span class="
                            flex size-10
                            shrink-0
                            items-center
                            justify-center
                            rounded-xl
                            bg-white
                            text-zinc-500
                            shadow-sm
                            ring-1
                            ring-zinc-200
                            transition

                            group-hover:text-emerald-600
                            group-hover:ring-emerald-200

                            dark:bg-zinc-950
                            dark:text-zinc-400
                            dark:ring-zinc-800
                            dark:group-hover:text-emerald-400
                            dark:group-hover:ring-emerald-900
                        ">

                        @switch($icon)

                            @case('electric')
                                <svg
                                    viewBox="0 0 24 24"
                                    class="size-5"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="m13 2-7 12h6l-1 8 7-12h-6l1-8Z"
                                    />
                                </svg>
                                @break

                            @case('maintenance')
                                <svg
                                    viewBox="0 0 24 24"
                                    class="size-5"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M14.5 6.5a4 4 0 0 0-5-5l2.2 2.2-2.8 2.8-2.2-2.2a4 4 0 0 0 5 5L19 16.6a2 2 0 0 1-2.8 2.8l-7.3-7.3"
                                    />
                                </svg>
                                @break

                            @case('technology')
                                <svg
                                    viewBox="0 0 24 24"
                                    class="size-5"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <rect
                                        x="4"
                                        y="4"
                                        width="16"
                                        height="12"
                                        rx="2"
                                    />
                                    <path
                                        stroke-linecap="round"
                                        d="M2 20h20M9 16v4M15 16v4"
                                    />
                                </svg>
                                @break

                            @case('photography')
                                <svg
                                    viewBox="0 0 24 24"
                                    class="size-5"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M4 7h3l1.5-2h7L17 7h3a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Z"
                                    />
                                    <circle cx="12" cy="13" r="4" />
                                </svg>
                                @break

                            @case('accounting')
                                <svg
                                    viewBox="0 0 24 24"
                                    class="size-5"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <rect
                                        x="5"
                                        y="3"
                                        width="14"
                                        height="18"
                                        rx="2"
                                    />
                                    <path
                                        stroke-linecap="round"
                                        d="M8 7h8M8 11h2M14 11h2M8 15h2M14 15h2"
                                    />
                                </svg>
                                @break

                            @case('design')
                                <svg
                                    viewBox="0 0 24 24"
                                    class="size-5"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 3a9 9 0 1 0 0 18h1.5a2 2 0 0 0 0-4H12a1.5 1.5 0 0 1 0-3h2a7 7 0 0 0-2-11Z"
                                    />
                                    <circle cx="7.5" cy="10" r=".8" fill="currentColor" stroke="none" />
                                    <circle cx="10" cy="6.5" r=".8" fill="currentColor" stroke="none" />
                                    <circle cx="15" cy="7.5" r=".8" fill="currentColor" stroke="none" />
                                </svg>
                                @break

                            @case('construction')
                                <svg
                                    viewBox="0 0 24 24"
                                    class="size-5"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="m3 11 9-8 9 8M5 10v10h14V10M9 20v-6h6v6"
                                    />
                                </svg>
                                @break

                            @case('automotive')
                                <svg
                                    viewBox="0 0 24 24"
                                    class="size-5"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="m5 16-1-4 2-5h12l2 5-1 4M4 16h16v3H4v-3Z"
                                    />
                                    <circle cx="7" cy="16" r="1" />
                                    <circle cx="17" cy="16" r="1" />
                                </svg>
                                @break

                        @endswitch

                    </span>

                    <span class="
                            min-w-0
                            text-sm font-semibold
                            text-zinc-800
                            transition
                            group-hover:text-emerald-700

                            dark:text-zinc-200
                            dark:group-hover:text-emerald-300
                        ">
                        {{ $category }}
                    </span>
                </a>

            @endforeach

        </div>


        <div class="
                mx-auto mt-14
                max-w-2xl
                text-center
            ">
            <p class="
                    text-sm font-semibold
                    text-emerald-600
                    dark:text-emerald-400
                ">
                Simples do começo ao fim
            </p>

            <h2 class="
                    mt-1
                    text-2xl font-bold
                    tracking-tight

                    sm:text-3xl
                ">
                Encontrar um serviço é simples
            </h2>

            <p class="
                    mt-2
                    text-sm leading-6
                    text-zinc-500
                    dark:text-zinc-400
                ">
                Pesquise, compare as opções disponíveis e
                fale diretamente com quem pode atender você.
            </p>
        </div>


        <div class="
                mx-auto mt-7
                grid max-w-5xl gap-4

                md:grid-cols-3
            ">

            @foreach ([
                [
                    '01',
                    'Pesquise',
                    'Digite o serviço que precisa e informe sua cidade.',
                ],
                [
                    '02',
                    'Encontre',
                    'Veja empresas e profissionais que podem atender você.',
                ],
                [
                    '03',
                    'Converse',
                    'Entre em contato diretamente e combine o atendimento.',
                ],
            ] as [$number, $title, $description])

                <article class="
                        rounded-2xl
                        border border-zinc-200
                        bg-white
                        p-5

                        dark:border-zinc-800
                        dark:bg-zinc-900
                    ">

                    <span class="
                            text-xs font-bold
                            text-emerald-600
                        ">
                        {{ $number }}
                    </span>

                    <h3 class="
                            mt-2 font-semibold
                        ">
                        {{ $title }}
                    </h3>

                    <p class="
                            mt-1.5
                            text-sm leading-6
                            text-zinc-500
                        ">
                        {{ $description }}
                    </p>

                </article>

            @endforeach

        </div>

    </div>
</section>


<section class="
        border-y border-zinc-200
        bg-zinc-950
        text-white

        dark:border-zinc-800
    ">
    <div class="
            mx-auto grid
            max-w-6xl
            grid-cols-1
            divide-y divide-zinc-800
            px-5

            sm:grid-cols-3
            sm:divide-x
            sm:divide-y-0

            sm:px-6
            lg:px-8
        ">

        <div class="
                flex items-center
                justify-center gap-4
                px-6 py-7
            ">
            <div class="
                    flex size-10 shrink-0
                    items-center justify-center
                    rounded-xl
                    bg-emerald-500/10
                    text-emerald-400
                ">
                <svg
                    viewBox="0 0 24 24"
                    class="size-5"
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

            <div>
                <p class="font-bold">
                    Busca local
                </p>

                <p class="
                        mt-0.5 text-xs
                        text-zinc-400
                    ">
                    Encontre serviços na sua cidade
                </p>
            </div>
        </div>


        <div class="
                flex items-center
                justify-center gap-4
                px-6 py-7
            ">
            <div class="
                    flex size-10 shrink-0
                    items-center justify-center
                    rounded-xl
                    bg-emerald-500/10
                    text-emerald-400
                ">
                <svg
                    viewBox="0 0 24 24"
                    class="size-5"
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

            <div>
                <p class="font-bold">
                    Contato direto
                </p>

                <p class="
                        mt-0.5 text-xs
                        text-zinc-400
                    ">
                    Converse diretamente com a empresa
                </p>
            </div>
        </div>


        <div class="
                flex items-center
                justify-center gap-4
                px-6 py-7
            ">
            <div class="
                    flex size-10 shrink-0
                    items-center justify-center
                    rounded-xl
                    bg-emerald-500/10
                    text-emerald-400
                ">
                <svg
                    viewBox="0 0 24 24"
                    class="size-5"
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

            <div>
                <p class="font-bold">
                    Para empresas
                </p>

                <p class="
                        mt-0.5 text-xs
                        text-zinc-400
                    ">
                    Divulgue seus serviços no Negozia
                </p>
            </div>
        </div>

    </div>
</section>

@if ($featuredBusinesses->isNotEmpty())

<section>
    <div class="
            mx-auto max-w-7xl
            px-5 py-16

            sm:px-6
            lg:px-8
        ">

        <div class="
                flex flex-col gap-3

                sm:flex-row
                sm:items-end
                sm:justify-between
            ">
            <div>
                <p class="
                        text-sm font-semibold
                        text-emerald-600
                    ">
                    Descubra empresas
                </p>

                <h2 class="
                        mt-1 text-2xl
                        font-bold tracking-tight

                        sm:text-3xl
                    ">
                    Empresas no Negozia
                </h2>
            </div>

            <a
                href="{{ route('marketplace.index') }}"
                class="
                    text-sm font-semibold
                    text-emerald-600
                    hover:text-emerald-700
                "
            >
                Ver todas →
            </a>
        </div>

        <div class="
                mt-8 grid gap-5

                sm:grid-cols-2
                lg:grid-cols-3
            ">

            @foreach ($featuredBusinesses as $business)
                <article class="
                        rounded-2xl
                        border border-zinc-200
                        bg-white
                        p-5
                        shadow-sm
                        transition
                        hover:-translate-y-0.5
                        hover:shadow-md

                        dark:border-zinc-800
                        dark:bg-zinc-900
                    ">

                    <div class="
                            flex items-start
                            gap-4
                        ">
                        <div class="
                                flex size-14
                                shrink-0
                                items-center
                                justify-center
                                overflow-hidden
                                rounded-xl
                                bg-emerald-50

                                dark:bg-emerald-950/50
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
                                        object-contain
                                        p-2
                                    "
                                >
                            @else
                                <span class="
                                        text-xl font-bold
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

                        <div class="min-w-0">
                            <h3 class="
                                    truncate font-semibold
                                ">
                                {{ $business->name }}
                            </h3>

                            <p class="
                                    mt-1 text-sm
                                    text-zinc-500
                                ">
                                {{ $business->city }}
                                @if ($business->state)
                                    /{{ $business->state }}
                                @endif
                            </p>
                        </div>
                    </div>

                    @if ($business->public_services)
                        <p class="
                                mt-4
                                line-clamp-2
                                text-sm
                                leading-6
                                text-zinc-600

                                dark:text-zinc-300
                            ">
                            {{ $business->public_services }}
                        </p>
                    @endif

                    <a
                        href="{{ route(
                            'marketplace.show',
                            $business->public_slug
                        ) }}"
                        class="
                            mt-5 inline-flex
                            text-sm font-semibold
                            text-emerald-600
                            hover:text-emerald-700
                        "
                    >
                        Conhecer empresa →
                    </a>

                </article>
            @endforeach

        </div>
    </div>
</section>

@endif


<section class="
        border-y border-zinc-200
        bg-white

        dark:border-zinc-800
        dark:bg-zinc-900/40
    ">
    <div class="
            mx-auto max-w-7xl
            px-5 py-16

            sm:px-6
            lg:px-8
        ">

        <div class="
                overflow-hidden
                rounded-3xl
                bg-emerald-600
                px-6 py-10
                text-white

                sm:px-10

                lg:flex
                lg:items-center
                lg:justify-between
                lg:gap-12
            ">

            <div class="max-w-2xl">
                <p class="
                        text-sm font-bold
                        text-emerald-100
                    ">
                    Você presta serviços?
                </p>

                <h2 class="
                        mt-2
                        text-3xl font-bold
                        tracking-tight
                    ">
                    Sua empresa também pode ser encontrada aqui.
                </h2>

                <p class="
                        mt-3
                        leading-7
                        text-emerald-50
                    ">
                    Cadastre sua empresa, crie propostas
                    profissionais e ganhe uma nova vitrine para
                    clientes encontrarem seus serviços.
                </p>
            </div>

            <div class="
                    mt-6 flex
                    shrink-0
                    flex-col gap-2

                    sm:flex-row
                    lg:mt-0
                ">

                <a
                    href="{{ route('for-businesses') }}"
                    class="
                        inline-flex
                        items-center
                        justify-center
                        rounded-xl
                        border border-white/40
                        px-5 py-3
                        text-sm font-bold
                        text-white
                        transition
                        hover:bg-white/10
                    "
                >
                    Conhecer o Negozia
                </a>

                <a
                    href="{{ route('register') }}"
                    class="
                        inline-flex
                        items-center
                        justify-center
                        rounded-xl
                        bg-white
                        px-5 py-3
                        text-sm font-bold
                        text-emerald-700
                        shadow-sm
                        transition
                        hover:bg-emerald-50
                    "
                >
                    Cadastrar grátis
                </a>

            </div>

        </div>
    </div>
</section>

@endsection
