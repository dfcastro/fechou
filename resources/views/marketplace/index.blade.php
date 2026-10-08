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

                    sm:grid-cols-2
                    lg:grid-cols-3
                ">

                @foreach ($businesses as $business)

                    <article class="
                            flex h-full flex-col
                            rounded-2xl
                            border border-zinc-200
                            bg-white
                            p-5
                            shadow-sm

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
                                <h2 class="
                                        truncate font-semibold
                                    ">
                                    {{ $business->name }}
                                </h2>

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

                        @if ($business->public_description)
                            <p class="
                                    mt-4
                                    line-clamp-3
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
                                    flex-wrap gap-1.5
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
                                            bg-zinc-100
                                            px-2.5 py-1
                                            text-xs font-medium
                                            text-zinc-600

                                            dark:bg-zinc-800
                                            dark:text-zinc-300
                                        ">
                                        {{ $serviceItem }}
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        <a
                            href="{{ route(
                                'marketplace.show',
                                $business->public_slug
                            ) }}"
                            class="
                                mt-auto
                                pt-5
                                inline-flex
                                w-full items-center
                                justify-center
                                rounded-xl
                                border border-zinc-200
                                px-4 py-2.5
                                text-sm font-semibold
                                transition

                                hover:border-emerald-300
                                hover:bg-emerald-50
                                hover:text-emerald-700

                                dark:border-zinc-700
                                dark:hover:bg-emerald-950/30
                            "
                        >
                            Ver empresa
                        </a>

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
