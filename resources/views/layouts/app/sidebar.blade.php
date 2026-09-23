<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="
        min-h-screen
        bg-zinc-50
        text-zinc-900

        dark:bg-zinc-950
        dark:text-zinc-100
    ">

    {{-- ========================================================= --}}
    {{-- SIDEBAR --}}
    {{-- ========================================================= --}}

    <flux:sidebar sticky collapsible="mobile" class="
            border-r border-zinc-200
            bg-white

            dark:border-zinc-800
            dark:bg-zinc-950
        ">

        {{-- ===================================================== --}}
        {{-- FECHAR SIDEBAR NO MOBILE --}}
        {{-- ===================================================== --}}

        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />


        {{-- ===================================================== --}}
        {{-- LOGO / MARCA --}}
        {{-- ===================================================== --}}

        <div class="px-2 pb-6 pt-2">

            <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3">

                <div class="
                        flex size-9
                        items-center
                        justify-center

                        rounded-xl

                        bg-emerald-600

                        text-lg
                        font-bold
                        text-white

                        shadow-sm

                        dark:bg-emerald-500
                        dark:text-zinc-950
                    ">
                    F
                </div>


                <div class="min-w-0">

                    <div class="
                            text-lg
                            font-bold
                            tracking-tight

                            text-zinc-950
                            dark:text-white
                        ">
                        Fechou
                    </div>

                    <div class="
                            text-[11px]
                            font-medium

                            text-zinc-400
                            dark:text-zinc-500
                        ">
                        Propostas que vendem
                    </div>

                </div>

            </a>

        </div>


        {{-- ===================================================== --}}
        {{-- NAVEGAÇÃO --}}
        {{-- ===================================================== --}}

        <flux:navlist variant="outline">

            {{-- ================================================= --}}
            {{-- GESTÃO --}}
            {{-- ================================================= --}}

            <flux:navlist.group heading="Gestão" class="grid">

                <flux:navlist.item icon="home" href="{{ route('dashboard') }}"
                    :current="request()->routeIs('dashboard')" wire:navigate>
                    Dashboard
                </flux:navlist.item>


                <flux:navlist.item icon="document-text" href="{{ route('quotes.index') }}"
                    :current="request()->routeIs(
                        'quotes.index',
                        'quotes.create',
                        'quotes.edit',
                        'quotes.show'
                    )" wire:navigate>
                    Propostas
                </flux:navlist.item>


                <flux:navlist.item icon="chart-bar" href="{{ route('quotes.pipeline') }}"
                    :current="request()->routeIs('quotes.pipeline')" wire:navigate>
                    Pipeline
                </flux:navlist.item>


                <flux:navlist.item icon="chart-bar" href="{{ route('reports.commercial') }}"
                    :current="request()->routeIs('reports.*')" wire:navigate>
                    Relatórios
                </flux:navlist.item>


                <flux:navlist.item icon="document-duplicate" href="{{ route('quote-templates.index') }}"
                    :current="request()->routeIs('quote-templates.*')" wire:navigate>
                    Modelos
                </flux:navlist.item>


                <flux:navlist.item icon="users" href="{{ route('clients.index') }}"
                    :current="request()->routeIs('clients.*')" wire:navigate>
                    Clientes
                </flux:navlist.item>

            </flux:navlist.group>


            {{-- ================================================= --}}
            {{-- ADMINISTRAÇÃO --}}
            {{-- ================================================= --}}

            @if (Auth::user()->is_admin)

                <flux:navlist.group heading="Administração" class="mt-4">

                    <flux:navlist.item icon="chart-bar" href="{{ route('admin.metrics') }}"
                        :current="request()->routeIs('admin.metrics')" wire:navigate>
                        Métricas
                    </flux:navlist.item>

                </flux:navlist.group>

            @endif


            {{-- ================================================= --}}
            {{-- CONFIGURAÇÕES --}}
            {{-- ================================================= --}}

            <flux:navlist.group heading="Configurações" expandable :expanded="request()->routeIs('settings.*')"
                class="mt-4">

                <flux:navlist.item icon="building-office" href="{{ route('settings.business') }}"
                    :current="request()->routeIs('settings.business')" wire:navigate>
                    Empresa
                </flux:navlist.item>


                <flux:navlist.item icon="banknotes" href="{{ route('settings.payment') }}"
                    :current="request()->routeIs('settings.payment')" wire:navigate>
                    Cobrança
                </flux:navlist.item>


                <flux:navlist.item icon="credit-card" href="{{ route('settings.subscription') }}"
                    :current="request()->routeIs('settings.subscription')" wire:navigate>
                    Plano e assinatura
                </flux:navlist.item>


                @if (Route::has('settings.follow-up'))

                    <flux:navlist.item icon="clock" href="{{ route('settings.follow-up') }}"
                        :current="request()->routeIs('settings.follow-up')" wire:navigate>
                        Follow-up
                    </flux:navlist.item>

                @endif

            </flux:navlist.group>

        </flux:navlist>


        {{-- ===================================================== --}}
        {{-- EMPURRA CONTA PARA BAIXO --}}
        {{-- ===================================================== --}}

        <flux:spacer />


        {{-- ===================================================== --}}
        {{-- CONTA --}}
        {{-- ===================================================== --}}

        <div class="
                border-t border-zinc-200
                pt-4

                dark:border-zinc-800
            ">

            <flux:dropdown position="top" align="start" class="w-full">

                <flux:button variant="ghost" class="w-full justify-start">

                    <div class="flex min-w-0 items-center gap-3">

                        {{-- AVATAR --}}

                        <div class="
                                flex size-8
                                shrink-0
                                items-center
                                justify-center

                                rounded-full

                                bg-zinc-200

                                text-xs
                                font-bold
                                text-zinc-700

                                dark:bg-zinc-800
                                dark:text-zinc-200
                            ">
                            {{ mb_strtoupper(
    mb_substr(
        Auth::user()->name,
        0,
        1
    )
) }}
                        </div>


                        {{-- USUÁRIO --}}

                        <div class="min-w-0 text-left">

                            <p class="
                                    truncate

                                    text-sm
                                    font-semibold

                                    text-zinc-800
                                    dark:text-zinc-200
                                ">
                                {{ Auth::user()->name }}
                            </p>


                            <p class="
                                    truncate

                                    text-xs

                                    text-zinc-400
                                    dark:text-zinc-500
                                ">
                                {{ Auth::user()->email }}
                            </p>

                        </div>

                    </div>

                </flux:button>


                {{-- ================================================= --}}
                {{-- MENU DA CONTA --}}
                {{-- ================================================= --}}

                <flux:menu>

                    <flux:menu.item icon="building-office" href="{{ route('settings.business') }}" wire:navigate>
                        Minha empresa
                    </flux:menu.item>


                    <flux:menu.item icon="credit-card" href="{{ route('settings.subscription') }}" wire:navigate>
                        Plano e assinatura
                    </flux:menu.item>


                    @if (Route::has('settings.follow-up'))

                        <flux:menu.item icon="clock" href="{{ route('settings.follow-up') }}" wire:navigate>
                            Configurar follow-up
                        </flux:menu.item>

                    @endif


                    <flux:menu.separator />


                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf

                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            Sair
                        </flux:menu.item>

                    </form>

                </flux:menu>

            </flux:dropdown>

        </div>

    </flux:sidebar>


    {{-- ========================================================= --}}
    {{-- HEADER MOBILE --}}
    {{-- ========================================================= --}}

    <flux:header class="
            border-b border-zinc-200
            bg-white
            pr-14

            lg:hidden

            dark:border-zinc-800
            dark:bg-zinc-950
        ">

        <flux:sidebar.toggle icon="bars-2" inset="left" />


        <div class="ml-2 flex items-center gap-2">

            <div class="
                    flex size-7
                    items-center
                    justify-center

                    rounded-lg

                    bg-emerald-600

                    text-sm
                    font-bold
                    text-white

                    dark:bg-emerald-500
                    dark:text-zinc-950
                ">
                F
            </div>


            <span class="
                    font-semibold
                    text-zinc-950

                    dark:text-white
                ">
                Fechou
            </span>

        </div>


        <flux:spacer />

    </flux:header>


    {{-- ========================================================= --}}
    {{-- CONTEÚDO PRINCIPAL --}}
    {{-- ÚNICO FLUX:MAIN DO LAYOUT --}}
    {{-- ========================================================= --}}

    <flux:main class="min-w-0">

        {{-- Notificação é fixa e fica dentro do main para não criar
        um item extra no grid principal do Flux. --}}
        <livewire:notifications-bell />


    {{-- ========================================================= --}}
    {{-- VERIFICAÇÃO DE E-MAIL — AVISO GLOBAL --}}
    {{-- ========================================================= --}}

    @if (Auth::check() && ! Auth::user()->hasVerifiedEmail())

        <div class="mx-auto w-full max-w-7xl px-4 pt-4 sm:px-6 lg:px-8">

            <div class="
                    flex flex-col gap-4
                    rounded-2xl
                    border border-amber-200
                    bg-amber-50
                    px-4 py-4
                    sm:flex-row
                    sm:items-center
                    sm:justify-between

                    dark:border-amber-900/70
                    dark:bg-amber-950/30
                ">

                <div class="flex min-w-0 items-start gap-3">

                    <div class="
                            mt-0.5 flex size-9 shrink-0
                            items-center justify-center
                            rounded-xl
                            bg-amber-100
                            text-amber-700

                            dark:bg-amber-500/10
                            dark:text-amber-300
                        ">
                        <svg
                            viewBox="0 0 24 24"
                            class="size-5"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.9"
                            aria-hidden="true"
                        >
                            <path d="M4 6.5h16v11H4z" stroke-linejoin="round"/>
                            <path d="m5 8 7 5 7-5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>

                    <div class="min-w-0">
                        <p class="
                                text-sm font-semibold
                                text-amber-950
                                dark:text-amber-100
                            ">
                            Confirme seu e-mail
                        </p>

                        <p class="
                                mt-1 text-sm leading-5
                                text-amber-800
                                dark:text-amber-200/80
                            ">
                            Verifique seu endereço para compartilhar propostas
                            e contratar o Fechou Pro.
                        </p>

                        @if (session('status') === 'verification-link-sent')
                            <p class="
                                    mt-2 text-xs font-semibold
                                    text-emerald-700
                                    dark:text-emerald-300
                                ">
                                Novo link de verificação enviado para
                                {{ Auth::user()->email }}.
                            </p>
                        @endif
                    </div>

                </div>

                <form
                    method="POST"
                    action="{{ route('verification.send') }}"
                    class="shrink-0"
                >
                    @csrf

                    <button
                        type="submit"
                        class="
                            inline-flex w-full items-center justify-center gap-2
                            rounded-lg
                            bg-amber-600
                            px-4 py-2.5
                            text-sm font-semibold
                            text-white
                            shadow-sm
                            transition
                            hover:bg-amber-700

                            sm:w-auto

                            dark:bg-amber-500
                            dark:text-zinc-950
                            dark:hover:bg-amber-400
                        "
                    >
                        Reenviar e-mail
                    </button>
                </form>

            </div>

        </div>

    @endif


    {{ $slot }}

    </flux:main>


    {{-- ========================================================= --}}
    {{-- TOAST --}}
    {{-- ========================================================= --}}

    @persist('toast')

    <flux:toast.group>
        <flux:toast />
    </flux:toast.group>

    @endpersist


    {{-- ========================================================= --}}
    {{-- SCRIPTS --}}
    {{-- ========================================================= --}}

    @fluxScripts

    @include('partials.form-masks')

</body>

</html>