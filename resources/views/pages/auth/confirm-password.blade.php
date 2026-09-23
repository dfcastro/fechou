<x-layouts::auth :title="'Confirmar senha | Fechou'">
    <div class="flex flex-col gap-6">

        <div class="text-center">
            <div class="mx-auto mb-4 flex size-11 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500">
                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                    <rect x="5" y="10" width="14" height="10" rx="2"/>
                    <path d="M8 10V7a4 4 0 0 1 8 0v3" stroke-linecap="round"/>
                </svg>
            </div>

            <h1 class="text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">
                Confirme sua senha
            </h1>

            <p class="mt-2 text-sm leading-6 text-zinc-500 dark:text-zinc-400">
                Por segurança, confirme sua senha antes de continuar nesta área da sua conta.
            </p>
        </div>

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.confirm.store') }}" class="flex flex-col gap-5">
            @csrf

            <flux:input
                name="password"
                label="Senha"
                type="password"
                required
                autofocus
                autocomplete="current-password"
                placeholder="Sua senha"
                viewable
            />

            <flux:button
                variant="primary"
                type="submit"
                class="w-full !bg-emerald-500 !text-white hover:!bg-emerald-600 dark:!bg-emerald-500 dark:!text-white dark:hover:!bg-emerald-400"
                data-test="confirm-password-button"
            >
                Confirmar e continuar
            </flux:button>
        </form>

    </div>
</x-layouts::auth>
