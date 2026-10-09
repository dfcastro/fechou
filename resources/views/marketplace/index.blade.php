@extends('layouts.marketplace')

@section(
    'title',
    'Buscar empresas | Negozia'
)

@section('content')

<section class="
        border-b border-zinc-200
        bg-white

        dark:border-zinc-800
        dark:bg-zinc-950
    ">
    <div class="
            mx-auto max-w-7xl
            px-5 py-10
            sm:px-6 lg:px-8
        ">

        <p class="
                text-sm font-semibold
                text-emerald-600
            ">
            Busca de serviços
        </p>

        <h1 class="
                mt-1 text-3xl
                font-bold tracking-tight
            ">
            Encontre empresas
        </h1>

        <form
            action="{{ route('marketplace.index') }}"
            method="GET"
            class="
                mt-6 grid gap-3
                rounded-2xl
                border border-zinc-200
                bg-zinc-50
                p-3

                md:grid-cols-[1fr_1fr_auto]

                dark:border-zinc-800
                dark:bg-zinc-900
            "
        >
            <input
                type="search"
                name="servico"
                value="{{ $service }}"
                placeholder="O que você procura?"
                class="
                    rounded-xl
                    border border-zinc-200
                    bg-white
                    px-4 py-3
                    text-sm

                    dark:border-zinc-700
                    dark:bg-zinc-950
                "
            >

            <input
                type="search"
                name="cidade"
                value="{{ $city }}"
                placeholder="Cidade"
                class="
                    rounded-xl
                    border border-zinc-200
                    bg-white
                    px-4 py-3
                    text-sm

                    dark:border-zinc-700
                    dark:bg-zinc-950
                "
            >

            <button
                class="
                    rounded-xl
                    bg-emerald-600
                    px-6 py-3
                    text-sm font-bold
                    text-white
                    hover:bg-emerald-700
                "
            >
                Buscar
            </button>
        </form>

    </div>
</section>


<section>
    <div class="
            mx-auto max-w-7xl
            px-5 py-10
            sm:px-6 lg:px-8
        ">

        <div class="
                flex items-center
                justify-between gap-4
            ">
            <p class="
                    text-sm text-zinc-500
                ">
                <strong class="
                        text-zinc-900
                        dark:text-white
                    ">
                    {{ $businesses->total() }}
                </strong>

                {{ $businesses->total() === 1
                    ? 'empresa encontrada'
                    : 'empresas encontradas' }}
            </p>
        </div>

        @if ($businesses->isEmpty())

            <div class="
                    mt-8 rounded-2xl
                    border border-dashed
                    border-zinc-300
                    bg-white
                    px-6 py-14
                    text-center

                    dark:border-zinc-700
                    dark:bg-zinc-900
                ">
                <h2 class="
                        text-lg font-semibold
                    ">
                    Nenhuma empresa encontrada
                </h2>

                <p class="
                        mx-auto mt-2
                        max-w-lg
                        text-sm leading-6
                        text-zinc-500
                    ">
                    Tente pesquisar um termo mais amplo ou
                    remover a cidade para encontrar mais opções.
                </p>
            </div>

        @else

            <div class="
                    mt-6 grid gap-5

                    lg:grid-cols-2
                ">

                @foreach ($businesses as $business)

                    <article class="
                            group
                            flex h-full flex-col
                            rounded-2xl
                            border border-zinc-200
                            bg-white
                            p-5
                            shadow-sm
                            transition

                            hover:-translate-y-0.5
                            hover:border-emerald-300
                            hover:shadow-md

                            dark:border-zinc-800
                            dark:bg-zinc-900
                            dark:hover:border-emerald-900
                        ">

                        <div class="
                                flex items-start
                                justify-between
                                gap-4
                            ">

                            <div class="
                                    flex min-w-0
                                    items-start gap-4
                                ">

                                <div class="
                                        flex size-14
                                        shrink-0
                                        items-center
                                        justify-center
                                        overflow-hidden
                                        rounded-2xl
                                        bg-emerald-50
                                        ring-1
                                        ring-emerald-100

                                        dark:bg-emerald-950/40
                                        dark:ring-emerald-900/50
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
                                                dark:text-emerald-400
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
                                    <h2 class="
                                            truncate
                                            text-base font-bold
                                            text-zinc-950
                                            dark:text-white
                                        ">
                                        {{ $business->name }}
                                    </h2>

                                    <p class="
                                            mt-1 flex
                                            items-center gap-1.5
                                            text-sm
                                            text-zinc-500
                                        ">
                                        <svg
                                            class="size-3.5"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
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
                                        </span>
                                    </p>
                                </div>
                            </div>

                            <span class="
                                    hidden shrink-0
                                    rounded-full
                                    bg-emerald-50
                                    px-2.5 py-1
                                    text-xs font-semibold
                                    text-emerald-700

                                    sm:inline-flex

                                    dark:bg-emerald-950/40
                                    dark:text-emerald-300
                                ">
                                {{ $business->services->first()?->category?->name ?? 'Empresa' }}
                            </span>
                        </div>

                        @if ($business->public_description)
                            <p class="
                                    mt-5
                                    line-clamp-2
                                    text-sm leading-6
                                    text-zinc-600
                                    dark:text-zinc-300
                                ">
                                {{ $business->public_description }}
                            </p>
                        @endif

                        @if ($business->public_services)
                            <div class="
                                    mt-4 flex
                                    flex-wrap gap-2
                                ">
                                @foreach (
                                    array_slice(
                                        array_filter(
                                            array_map(
                                                'trim',
                                                explode(
                                                    ',',
                                                    $business->public_services
                                                )
                                            )
                                        ),
                                        0,
                                        4
                                    )
                                    as $serviceItem
                                )
                                    <span class="
                                            rounded-full
                                            bg-emerald-50
                                            px-2.5 py-1
                                            text-xs font-semibold
                                            text-emerald-700

                                            dark:bg-emerald-950/35
                                            dark:text-emerald-300
                                        ">
                                        {{ $serviceItem }}
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        <div class="
                                mt-auto
                                flex justify-end
                                pt-5
                            ">
                            <a
                                href="{{ route(
                                    'marketplace.show',
                                    $business->public_slug
                                ) }}"
                                class="
                                    inline-flex
                                    items-center gap-1.5
                                    rounded-lg
                                    px-3 py-2
                                    text-sm font-semibold
                                    text-emerald-600
                                    transition

                                    hover:bg-emerald-50
                                    hover:text-emerald-700

                                    dark:text-emerald-400
                                    dark:hover:bg-emerald-950/30
                                "
                            >
                                Ver empresa

                                <span
                                    aria-hidden="true"
                                    class="
                                        transition
                                        group-hover:translate-x-0.5
                                    "
                                >
                                    →
                                </span>
                            </a>
                        </div>

                    </article>

                @endforeach

            </div>

            <div class="mt-8">
                {{ $businesses->links() }}
            </div>

        @endif

    </div>
</section>

@endsection
