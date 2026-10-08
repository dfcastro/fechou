<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>
        @yield('title', 'Negozia — Encontre empresas e serviços')
    </title>

    <meta
        name="description"
        content="@yield(
            'description',
            'Encontre empresas e profissionais da sua cidade no Negozia.'
        )"
    >

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])

    @fluxAppearance
</head>

<body class="
        min-h-screen
        bg-zinc-50
        text-zinc-950
        antialiased

        dark:bg-zinc-950
        dark:text-white
    ">

    <header class="
            sticky top-0 z-40
            border-b border-zinc-200/80
            bg-white/90
            backdrop-blur-xl

            dark:border-zinc-800
            dark:bg-zinc-950/90
        ">
        <div class="
                mx-auto flex h-16
                max-w-7xl
                items-center
                justify-between
                px-5 sm:px-6 lg:px-8
            ">

            <a
                href="{{ route('home') }}"
                class="flex items-center gap-2.5"
            >
                <x-app-logo-icon class="size-9 shrink-0" />

                <span class="
                        text-lg font-bold
                        tracking-tight
                    ">
                    Negozia
                </span>
            </a>

            <div class="flex items-center gap-2">

                <a
                    href="{{ route('for-businesses') }}"
                    class="
                        hidden rounded-lg
                        px-3 py-2
                        text-sm font-semibold
                        text-zinc-600
                        transition
                        hover:bg-zinc-100
                        hover:text-zinc-950

                        md:inline-flex

                        dark:text-zinc-300
                        dark:hover:bg-zinc-900
                        dark:hover:text-white
                    "
                >
                    Para empresas
                </a>

                @auth
                    <a
                        href="{{ route('dashboard') }}"
                        class="
                            rounded-lg
                            bg-emerald-600
                            px-4 py-2
                            text-sm font-semibold
                            text-white
                            hover:bg-emerald-700
                        "
                    >
                        Meu painel
                    </a>
                @else
                    <a
                        href="{{ route('login') }}"
                        class="
                            hidden rounded-lg
                            px-3 py-2
                            text-sm font-semibold
                            text-zinc-700
                            hover:bg-zinc-100

                            sm:inline-flex

                            dark:text-zinc-200
                            dark:hover:bg-zinc-900
                        "
                    >
                        Entrar
                    </a>

                    @if (Route::has('register'))
                        <a
                            href="{{ route('register') }}"
                            class="
                                rounded-lg
                                bg-emerald-600
                                px-4 py-2
                                text-sm font-semibold
                                text-white
                                hover:bg-emerald-700
                            "
                        >
                            Cadastrar empresa
                        </a>
                    @endif
                @endauth
            </div>

        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="
            mt-16
            border-t border-zinc-200
            bg-white

            dark:border-zinc-800
            dark:bg-zinc-950
        ">
        <div class="
                mx-auto flex
                max-w-7xl
                flex-col gap-4
                px-5 py-8
                text-sm text-zinc-500

                sm:flex-row
                sm:items-center
                sm:justify-between
                sm:px-6

                lg:px-8
            ">
            <div class="flex items-center gap-2">
                <x-app-logo-icon class="size-7" />
                <span class="
                        font-semibold
                        text-zinc-700
                        dark:text-zinc-200
                    ">
                    Negozia
                </span>
            </div>

            <p>
                © {{ now()->year }} Negozia.
                Encontre. Negocie. Feche.
            </p>
        </div>
    </footer>

    @fluxScripts
</body>
</html>
