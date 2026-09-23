<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Fechou — Propostas profissionais do envio ao aceite</title>
    <meta
        name="description"
        content="Crie propostas profissionais, compartilhe pelo WhatsApp e acompanhe visualizações, follow-ups, versões e respostas em um só lugar."
    >

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>

<body class="min-h-screen bg-white text-zinc-950 antialiased dark:bg-zinc-950 dark:text-white">

    {{-- Glow de fundo --}}
    <div class="pointer-events-none fixed inset-x-0 top-0 -z-10 overflow-hidden">
        <div class="mx-auto h-[420px] w-[900px] max-w-full rounded-full bg-emerald-400/10 blur-3xl dark:bg-emerald-500/10"></div>
    </div>

    {{-- HEADER --}}
    <header class="sticky top-0 z-40 border-b border-zinc-200/80 bg-white/85 backdrop-blur-xl dark:border-zinc-800/80 dark:bg-zinc-950/85">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-5 sm:px-6 lg:px-8">

            <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                <span class="flex size-9 items-center justify-center rounded-xl bg-emerald-500 text-sm font-black text-white shadow-sm shadow-emerald-500/20">
                    F
                </span>

                <span class="text-lg font-bold tracking-tight">
                    Fechou
                </span>
            </a>

            <nav class="hidden items-center gap-7 text-sm font-medium text-zinc-600 md:flex dark:text-zinc-300">
                <a href="#como-funciona" class="transition hover:text-zinc-950 dark:hover:text-white">
                    Como funciona
                </a>

                <a href="#recursos" class="transition hover:text-zinc-950 dark:hover:text-white">
                    Recursos
                </a>

                <a href="#planos" class="transition hover:text-zinc-950 dark:hover:text-white">
                    Planos
                </a>
            </nav>

            <div class="flex items-center gap-2">
                @auth
                    <a
                        href="{{ route('dashboard') }}"
                        class="inline-flex items-center justify-center rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-600"
                    >
                        Ir para o painel
                    </a>
                @else
                    <a
                        href="{{ route('login') }}"
                        class="hidden rounded-lg px-3 py-2 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-100 sm:inline-flex dark:text-zinc-200 dark:hover:bg-zinc-900"
                    >
                        Entrar
                    </a>

                    @if (Route::has('register'))
                        <a
                            href="{{ route('register') }}"
                            class="inline-flex items-center justify-center rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-emerald-500/20 transition hover:bg-emerald-600"
                        >
                            Começar grátis
                        </a>
                    @endif
                @endauth
            </div>
        </div>
    </header>

    <main>

        {{-- HERO --}}
        <section class="relative overflow-hidden">
            <div class="mx-auto grid max-w-7xl gap-12 px-5 py-16 sm:px-6 sm:py-20 lg:grid-cols-[1.02fr_.98fr] lg:items-center lg:px-8 lg:py-28">

                <div>
                    <div class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-950/40 dark:text-emerald-300">
                        <span class="size-1.5 rounded-full bg-emerald-500"></span>
                        Do orçamento ao aceite, sem perder o acompanhamento
                    </div>

                    <h1 class="mt-6 max-w-3xl text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl">
                        Envie propostas profissionais.
                        <span class="text-emerald-500">Acompanhe até o cliente dizer: fechou.</span>
                    </h1>

                    <p class="mt-6 max-w-2xl text-base leading-7 text-zinc-600 sm:text-lg dark:text-zinc-300">
                        Crie propostas, compartilhe pelo WhatsApp, saiba quando o cliente visualizou
                        e organize seus follow-ups sem depender de planilhas ou mensagens perdidas.
                    </p>

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                        @auth
                            <a
                                href="{{ route('dashboard') }}"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-500 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-500/15 transition hover:bg-emerald-600"
                            >
                                Abrir meu painel
                                <span aria-hidden="true">→</span>
                            </a>
                        @else
                            <a
                                href="{{ route('register') }}"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-500 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-500/15 transition hover:bg-emerald-600"
                            >
                                Criar conta grátis
                                <span aria-hidden="true">→</span>
                            </a>

                            <a
                                href="#como-funciona"
                                class="inline-flex items-center justify-center rounded-xl border border-zinc-200 bg-white px-5 py-3 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
                            >
                                Ver como funciona
                            </a>
                        @endauth
                    </div>

                    <div class="mt-6 flex flex-wrap gap-x-5 gap-y-2 text-xs font-medium text-zinc-500 dark:text-zinc-400">
                        <span class="flex items-center gap-1.5">
                            <svg viewBox="0 0 20 20" class="size-4 text-emerald-500" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/>
                            </svg>
                            Sem cartão para começar
                        </span>

                        <span class="flex items-center gap-1.5">
                            <svg viewBox="0 0 20 20" class="size-4 text-emerald-500" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/>
                            </svg>
                            5 propostas por mês no Grátis
                        </span>

                        <span class="flex items-center gap-1.5">
                            <svg viewBox="0 0 20 20" class="size-4 text-emerald-500" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/>
                            </svg>
                            Sem compromisso
                        </span>
                    </div>
                </div>

                {{-- MOCKUP --}}
                <div class="relative">
                    <div class="absolute -inset-4 -z-10 rounded-[2rem] bg-gradient-to-br from-emerald-500/15 via-transparent to-violet-500/10 blur-2xl"></div>

                    <div class="overflow-hidden rounded-3xl border border-zinc-200 bg-white shadow-2xl shadow-zinc-950/10 dark:border-zinc-800 dark:bg-zinc-900 dark:shadow-black/30">
                        <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                            <div>
                                <p class="text-xs font-medium text-zinc-400">Proposta #00042</p>
                                <p class="mt-1 font-semibold">Instalação de ar-condicionado</p>
                            </div>

                            <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                                Visualizada
                            </span>
                        </div>

                        <div class="space-y-5 p-5 sm:p-6">
                            <div class="grid grid-cols-3 gap-3">
                                <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-950/70">
                                    <p class="text-[11px] uppercase tracking-wide text-zinc-400">Cliente</p>
                                    <p class="mt-1 truncate text-sm font-semibold">Mariana Souza</p>
                                </div>

                                <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-950/70">
                                    <p class="text-[11px] uppercase tracking-wide text-zinc-400">Valor</p>
                                    <p class="mt-1 text-sm font-semibold">R$ 2.480,00</p>
                                </div>

                                <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-950/70">
                                    <p class="text-[11px] uppercase tracking-wide text-zinc-400">Validade</p>
                                    <p class="mt-1 text-sm font-semibold">7 dias</p>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-semibold">Histórico da proposta</p>
                                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Você sabe o que aconteceu depois do envio.</p>
                                    </div>
                                </div>

                                <div class="mt-5 space-y-4">
                                    <div class="flex gap-3">
                                        <span class="mt-1 size-2.5 shrink-0 rounded-full bg-blue-500 ring-4 ring-blue-500/10"></span>
                                        <div>
                                            <p class="text-sm font-medium">Proposta enviada</p>
                                            <p class="text-xs text-zinc-400">Hoje, 09:14</p>
                                        </div>
                                    </div>

                                    <div class="flex gap-3">
                                        <span class="mt-1 size-2.5 shrink-0 rounded-full bg-amber-500 ring-4 ring-amber-500/10"></span>
                                        <div>
                                            <p class="text-sm font-medium">Cliente visualizou</p>
                                            <p class="text-xs text-zinc-400">Hoje, 10:02</p>
                                        </div>
                                    </div>

                                    <div class="flex gap-3">
                                        <span class="mt-1 size-2.5 shrink-0 rounded-full bg-cyan-500 ring-4 ring-cyan-500/10"></span>
                                        <div>
                                            <p class="text-sm font-medium">Follow-up sugerido</p>
                                            <p class="text-xs text-zinc-400">Amanhã, 10:00</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between rounded-2xl bg-emerald-50 p-4 dark:bg-emerald-500/10">
                                <div>
                                    <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-200">
                                        Próximo passo claro
                                    </p>
                                    <p class="mt-1 text-xs text-emerald-700/80 dark:text-emerald-300/80">
                                        O Fechou ajuda você a não deixar oportunidades esfriarem.
                                    </p>
                                </div>

                                <span class="ml-4 flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-500 text-white">
                                    <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M8 12h8M13 9l3 3-3 3" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- COMO FUNCIONA --}}
        <section id="como-funciona" class="border-y border-zinc-200 bg-zinc-50/70 dark:border-zinc-800 dark:bg-zinc-900/40">
            <div class="mx-auto max-w-7xl px-5 py-20 sm:px-6 lg:px-8">
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                        Simples do começo ao fim
                    </p>

                    <h2 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                        Menos improviso. Mais acompanhamento.
                    </h2>

                    <p class="mt-4 text-zinc-600 dark:text-zinc-300">
                        Organize o processo comercial sem transformar sua rotina em um CRM complicado.
                    </p>
                </div>

                <div class="mt-10 grid gap-5 md:grid-cols-3">
                    @foreach ([
                        ['01', 'Crie a proposta', 'Cadastre o cliente, serviços, materiais, valores e condições em poucos minutos.'],
                        ['02', 'Envie do seu jeito', 'Compartilhe o link da proposta pelo WhatsApp e também gere PDF quando precisar.'],
                        ['03', 'Acompanhe a resposta', 'Veja visualizações, organize follow-ups e registre aceite ou recusa sem perder o histórico.'],
                    ] as [$number, $title, $description])
                        <article class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <span class="text-sm font-bold text-emerald-500">{{ $number }}</span>
                            <h3 class="mt-4 text-lg font-semibold">{{ $title }}</h3>
                            <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                                {{ $description }}
                            </p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- RECURSOS --}}
        <section id="recursos">
            <div class="mx-auto max-w-7xl px-5 py-20 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                        Feito para quem presta serviços
                    </p>

                    <h2 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                        O essencial para transformar orçamento em oportunidade acompanhada
                    </h2>
                </div>

                <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        ['Clientes organizados', 'Tenha os dados dos seus clientes sempre vinculados às propostas.', 'users'],
                        ['Link público e PDF', 'Envie uma apresentação profissional sem depender de arquivos soltos.', 'link'],
                        ['Versionamento', 'Ajuste a negociação sem perder o histórico das versões anteriores.', 'version'],
                        ['Follow-up', 'Saiba quais propostas precisam de atenção antes de esfriarem.', 'followup'],
                        ['Notificações', 'Centralize sinais importantes da jornada de cada proposta.', 'bell'],
                        ['Marca própria', 'Use logo e identidade da sua empresa nas propostas do plano Pro.', 'brand'],
                        ['Aceite e recusa', 'Registre a decisão do cliente diretamente pela proposta pública.', 'check'],
                        ['Compartilhamento', 'Leve a proposta para o WhatsApp em poucos cliques.', 'send'],
                    ] as [$title, $description, $icon])
                        <article class="rounded-2xl border border-zinc-200 p-5 dark:border-zinc-800">
                            <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <circle cx="12" cy="12" r="8"/>
                                    <path d="M8.5 12 11 14.5 15.5 9.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>

                            <h3 class="mt-4 font-semibold">{{ $title }}</h3>
                            <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                                {{ $description }}
                            </p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- PLANOS --}}
        <section id="planos" class="border-y border-zinc-200 bg-zinc-50/70 dark:border-zinc-800 dark:bg-zinc-900/40">
            <div class="mx-auto max-w-5xl px-5 py-20 sm:px-6 lg:px-8">

                <div class="mx-auto max-w-2xl text-center">
                    <p class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                        Planos simples
                    </p>

                    <h2 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                        Comece grátis. Evolua quando precisar.
                    </h2>

                    <p class="mt-4 text-zinc-600 dark:text-zinc-300">
                        Sem complicação para testar o fluxo real do seu negócio.
                    </p>
                </div>

                <div class="mt-12 grid gap-6 lg:grid-cols-2">

                    {{-- FREE --}}
                    <article class="rounded-3xl border border-zinc-200 bg-white p-7 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <p class="text-sm font-semibold text-zinc-500 dark:text-zinc-400">Grátis</p>

                        <div class="mt-3 flex items-end gap-1">
                            <span class="text-4xl font-bold tracking-tight">R$ 0</span>
                            <span class="pb-1 text-sm text-zinc-500">/mês</span>
                        </div>

                        <p class="mt-4 text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                            Para começar a organizar clientes e enviar propostas profissionais.
                        </p>

                        <div class="my-6 h-px bg-zinc-200 dark:bg-zinc-800"></div>

                        <ul class="space-y-3 text-sm">
                            @foreach ([
                                'Até 5 novas propostas por mês',
                                'Gestão de clientes',
                                'Link público da proposta',
                                'Exportação em PDF',
                                'Compartilhamento pelo WhatsApp',
                                'Aceite e recusa',
                            ] as $item)
                                <li class="flex items-start gap-2.5">
                                    <span class="mt-0.5 text-emerald-500">✓</span>
                                    <span>{{ $item }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <a
                            href="{{ route('register') }}"
                            class="mt-7 inline-flex w-full items-center justify-center rounded-xl border border-zinc-200 px-4 py-3 text-sm font-semibold transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                        >
                            Começar grátis
                        </a>
                    </article>

                    {{-- PRO --}}
                    <article class="relative overflow-hidden rounded-3xl border border-violet-300 bg-white p-7 shadow-sm ring-1 ring-violet-200 dark:border-violet-800 dark:bg-zinc-900 dark:ring-violet-900/60">
                        <div class="absolute right-0 top-0 rounded-bl-xl bg-violet-600 px-3 py-1.5 text-[10px] font-bold tracking-wide text-white dark:bg-violet-500 dark:text-zinc-950">
                            MAIS COMPLETO
                        </div>

                        <p class="text-sm font-semibold text-violet-600 dark:text-violet-300">Pro</p>

                        <div class="mt-3 flex items-end gap-1">
                            <span class="text-sm font-semibold text-zinc-500">R$</span>
                            <span class="text-4xl font-bold tracking-tight">29,90</span>
                            <span class="pb-1 text-sm text-zinc-500">/mês</span>
                        </div>

                        <p class="mt-4 text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                            Para quem quer acompanhar cada proposta e manter a negociação organizada.
                        </p>

                        <div class="my-6 h-px bg-violet-100 dark:bg-violet-900/50"></div>

                        <ul class="space-y-3 text-sm">
                            @foreach ([
                                'Propostas ilimitadas',
                                'Tudo do plano Grátis',
                                'Versionamento de propostas',
                                'Follow-up inteligente',
                                'Central de notificações',
                                'Logo e identidade da empresa',
                            ] as $item)
                                <li class="flex items-start gap-2.5">
                                    <span class="mt-0.5 text-violet-500">✓</span>
                                    <span>{{ $item }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <a
                            href="{{ route('register') }}"
                            class="mt-7 inline-flex w-full items-center justify-center rounded-xl bg-violet-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-violet-700 dark:bg-violet-500 dark:text-zinc-950 dark:hover:bg-violet-400"
                        >
                            Criar conta e conhecer o Pro
                        </a>
                    </article>
                </div>
            </div>
        </section>

        {{-- CTA FINAL --}}
        <section>
            <div class="mx-auto max-w-7xl px-5 py-20 sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-3xl bg-zinc-950 px-6 py-12 text-center text-white shadow-xl sm:px-10 dark:border dark:border-zinc-800">
                    <div class="mx-auto max-w-2xl">
                        <p class="text-sm font-semibold text-emerald-400">
                            Sua próxima proposta pode começar aqui
                        </p>

                        <h2 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                            Menos proposta esquecida. Mais oportunidade acompanhada.
                        </h2>

                        <p class="mt-4 text-sm leading-6 text-zinc-300 sm:text-base">
                            Crie sua conta gratuita e organize o caminho entre enviar um orçamento e receber a resposta do cliente.
                        </p>

                        <a
                            href="{{ route('register') }}"
                            class="mt-7 inline-flex items-center justify-center rounded-xl bg-emerald-500 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-400"
                        >
                            Começar grátis
                        </a>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <footer class="border-t border-zinc-200 dark:border-zinc-800">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-5 py-8 text-sm text-zinc-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8 dark:text-zinc-400">
            <div class="flex items-center gap-2">
                <span class="flex size-7 items-center justify-center rounded-lg bg-emerald-500 text-xs font-black text-white">
                    F
                </span>
                <span class="font-semibold text-zinc-700 dark:text-zinc-200">Fechou</span>
            </div>

            <p>
                © {{ now()->year }} Fechou. Propostas profissionais sem complicação.
            </p>
        </div>
    </footer>

    @fluxScripts
</body>
</html>
