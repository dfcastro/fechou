<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body
    class="
        min-h-screen
        bg-zinc-50
        text-zinc-900

        dark:bg-zinc-950
        dark:text-zinc-100
    ">

    {{-- ========================================================= --}}
    {{-- SIDEBAR --}}
    {{-- ========================================================= --}}

    <flux:sidebar
        sticky
        stashable
        class="
            border-r border-zinc-200
            bg-white

            dark:border-zinc-800
            dark:bg-zinc-950
        ">

        {{-- ===================================================== --}}
        {{-- FECHAR SIDEBAR NO MOBILE --}}
        {{-- ===================================================== --}}

        <flux:sidebar.toggle
            class="lg:hidden"
            icon="x-mark" />


        {{-- ===================================================== --}}
        {{-- LOGO / MARCA --}}
        {{-- ===================================================== --}}

        <div class="px-2 pb-6 pt-2">

            <a
                href="{{ route('dashboard') }}"
                wire:navigate
                class="flex items-center gap-3">

                <div
                    class="
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

                    <div
                        class="
                            text-lg
                            font-bold
                            tracking-tight

                            text-zinc-950
                            dark:text-white
                        ">
                        Fechou
                    </div>

                    <div
                        class="
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

            <flux:navlist.group
                heading="Gestão"
                class="grid">

                {{-- DASHBOARD --}}

                <flux:navlist.item
                    icon="home"
                    href="{{ route('dashboard') }}"
                    :current="request()->routeIs('dashboard')"
                    wire:navigate>
                    Dashboard
                </flux:navlist.item>


                {{-- ORÇAMENTOS --}}

                <flux:navlist.item
                    icon="document-text"
                    href="{{ route('quotes.index') }}"
                    :current="request()->routeIs('quotes.*')"
                    wire:navigate>
                    Orçamentos
                </flux:navlist.item>


                {{-- CLIENTES --}}

                <flux:navlist.item
                    icon="users"
                    href="{{ route('clients.index') }}"
                    :current="request()->routeIs('clients.*')"
                    wire:navigate>
                    Clientes
                </flux:navlist.item>

            </flux:navlist.group>


            {{-- ================================================= --}}
            {{-- CONFIGURAÇÕES --}}
            {{-- ================================================= --}}

            <flux:navlist.group
                heading="Configurações"
                expandable
                :expanded="request()->routeIs('settings.*')"
                class="mt-4">

                {{-- EMPRESA --}}

                <flux:navlist.item
                    icon="building-office"
                    href="{{ route('settings.business') }}"
                    :current="request()->routeIs('settings.business')"
                    wire:navigate>
                    Empresa
                </flux:navlist.item>


                {{-- FOLLOW-UP --}}
                {{-- Só aparece depois que criarmos a rota. --}}

                @if (Route::has('settings.follow-up'))

                <flux:navlist.item
                    icon="clock"
                    href="{{ route('settings.follow-up') }}"
                    :current="request()->routeIs('settings.follow-up')"
                    wire:navigate>
                    Follow-up
                </flux:navlist.item>

                @endif

            </flux:navlist.group>

        </flux:navlist>


        {{-- ===================================================== --}}
        {{-- EMPURRA A CONTA PARA BAIXO --}}
        {{-- ===================================================== --}}

        <flux:spacer />


        {{-- ===================================================== --}}
        {{-- CONTA --}}
        {{-- ===================================================== --}}

        <div
            class="
                border-t border-zinc-200
                pt-4

                dark:border-zinc-800
            ">

            <flux:dropdown
                position="top"
                align="start"
                class="w-full">

                <flux:button
                    variant="ghost"
                    class="w-full justify-start">

                    <div class="flex min-w-0 items-center gap-3">

                        {{-- AVATAR --}}

                        <div
                            class="
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

                            <p
                                class="
                                    truncate

                                    text-sm
                                    font-semibold

                                    text-zinc-800
                                    dark:text-zinc-200
                                ">
                                {{ Auth::user()->name }}
                            </p>


                            <p
                                class="
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

                    {{-- EMPRESA --}}

                    <flux:menu.item
                        icon="building-office"
                        href="{{ route('settings.business') }}"
                        wire:navigate>
                        Minha empresa
                    </flux:menu.item>


                    {{-- FOLLOW-UP --}}

                    @if (Route::has('settings.follow-up'))

                    <flux:menu.item
                        icon="clock"
                        href="{{ route('settings.follow-up') }}"
                        wire:navigate>
                        Configurar follow-up
                    </flux:menu.item>

                    @endif


                    <flux:menu.separator />


                    {{-- LOGOUT --}}

                    <form
                        method="POST"
                        action="{{ route('logout') }}"
                        class="w-full">
                        @csrf

                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full">
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

    <flux:header
        class="
            border-b border-zinc-200
            bg-white

            lg:hidden

            dark:border-zinc-800
            dark:bg-zinc-950
        ">

        {{-- ABRIR SIDEBAR --}}

        <flux:sidebar.toggle
            icon="bars-2"
            inset="left" />


        {{-- MARCA MOBILE --}}

        <div class="ml-2 flex items-center gap-2">

            <div
                class="
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


            <span
                class="
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
    {{-- ========================================================= --}}

    {{ $slot }}


    {{-- ========================================================= --}}
    {{-- TOAST --}}
    {{-- ========================================================= --}}

    @persist('toast')

    <flux:toast.group>
        <flux:toast />
    </flux:toast.group>

    @endpersist


    @fluxScripts

</body>

</html>