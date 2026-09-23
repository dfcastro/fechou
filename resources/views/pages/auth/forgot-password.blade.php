<x-layouts::auth :title="'Recuperar senha | Fechou'">
    <div class="flex flex-col gap-6">

        <div class="text-center">
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">
                Esqueceu sua senha?
            </h1>

            <p class="mt-2 text-sm leading-6 text-zinc-500 dark:text-zinc-400">
                Informe o e-mail da sua conta e enviaremos um link para você criar uma nova senha.
            </p>
        </div>

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-5">
            @csrf

            <flux:input
                name="email"
                label="E-mail"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="voce@empresa.com.br"
            />

            <flux:button
                variant="primary"
                type="submit"
                class="w-full !bg-emerald-500 !text-white hover:!bg-emerald-600 dark:!bg-emerald-500 dark:!text-white dark:hover:!bg-emerald-400"
                data-test="email-password-reset-link-button"
            >
                Enviar link de recuperação
            </flux:button>
        </form>

        <div class="text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>Lembrou sua senha?</span>

            <flux:link :href="route('login')" wire:navigate>
                Voltar para o login
            </flux:link>
        </div>

        <div class="text-center">
            <a
                href="{{ route('home') }}"
                class="text-xs font-medium text-zinc-400 transition hover:text-zinc-600 dark:hover:text-zinc-300"
            >
                ← Voltar para a página inicial
            </a>
        </div>

    </div>
</x-layouts::auth>
