<x-layouts::auth :title="'Verificar e-mail | Fechou'">
    <div class="flex flex-col gap-6">

        <div class="text-center">
            <div
                class="mx-auto mb-4 flex size-11 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-500">
                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.9"
                    aria-hidden="true">
                    <path d="M4 6.5h16v11H4z" stroke-linejoin="round" />
                    <path d="m5 8 7 5 7-5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </div>

            <h1 class="text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">
                Verifique seu e-mail
            </h1>

            <p class="mt-2 text-sm leading-6 text-zinc-500 dark:text-zinc-400">
                Enviamos um link de verificação para o e-mail informado no cadastro.
                Confirme seu endereço para liberar o compartilhamento de propostas
                e a contratação do Fechou Pro.
            </p>
        </div>

        @if (session('status') == 'verification-link-sent')
            <div
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/70 dark:bg-emerald-950/30 dark:text-emerald-300">
                Um novo link de verificação foi enviado para o seu e-mail.
            </div>
        @endif

        <div class="flex flex-col gap-3">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf

                <flux:button type="submit" variant="primary"
                    class="w-full !bg-emerald-500 !text-white hover:!bg-emerald-600 dark:!bg-emerald-500 dark:!text-white dark:hover:!bg-emerald-400">
                    Reenviar e-mail de verificação
                </flux:button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf

                <flux:button variant="ghost" type="submit" class="w-full cursor-pointer text-sm"
                    data-test="logout-button">
                    Sair da conta
                </flux:button>
            </form>
        </div>

        <p class="text-center text-xs leading-5 text-zinc-400">
            Não encontrou o e-mail? Confira também a pasta de spam ou lixo eletrônico.
        </p>

    </div>
</x-layouts::auth>