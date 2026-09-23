<x-layouts::auth :title="'Nova senha | Fechou'">
    <div class="flex flex-col gap-6">

        <div class="text-center">
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">
                Defina uma nova senha
            </h1>

            <p class="mt-2 text-sm leading-6 text-zinc-500 dark:text-zinc-400">
                Crie uma nova senha para voltar a acessar sua conta do Fechou.
            </p>
        </div>

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-5">
            @csrf

            <input type="hidden" name="token" value="{{ request()->route('token') }}">

            <flux:input
                name="email"
                value="{{ request('email') }}"
                label="E-mail"
                type="email"
                required
                autocomplete="email"
            />

            <flux:input
                name="password"
                label="Nova senha"
                type="password"
                required
                autocomplete="new-password"
                placeholder="Crie uma nova senha"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <flux:input
                name="password_confirmation"
                label="Confirmar nova senha"
                type="password"
                required
                autocomplete="new-password"
                placeholder="Digite a nova senha novamente"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <flux:button
                type="submit"
                variant="primary"
                class="w-full !bg-emerald-500 !text-white hover:!bg-emerald-600 dark:!bg-emerald-500 dark:!text-white dark:hover:!bg-emerald-400"
                data-test="reset-password-button"
            >
                Salvar nova senha
            </flux:button>
        </form>

        <div class="text-center">
            <a
                href="{{ route('login') }}"
                class="text-xs font-medium text-zinc-400 transition hover:text-zinc-600 dark:hover:text-zinc-300"
                wire:navigate
            >
                ← Voltar para o login
            </a>
        </div>

    </div>
</x-layouts::auth>
