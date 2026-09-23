<x-layouts::auth :title="'Entrar | Fechou'">
    <div class="flex flex-col gap-6">

        <div class="text-center">
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">
                Bem-vindo de volta
            </h1>

            <p class="mt-2 text-sm leading-6 text-zinc-500 dark:text-zinc-400">
                Entre para acompanhar suas propostas, clientes e próximos follow-ups.
            </p>
        </div>

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5">
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

            <div class="relative">
                <flux:input
                    name="password"
                    label="Senha"
                    type="password"
                    required
                    autocomplete="current-password"
                    placeholder="Sua senha"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link
                        class="absolute top-0 text-sm end-0"
                        :href="route('password.request')"
                        wire:navigate
                    >
                        Esqueci minha senha
                    </flux:link>
                @endif
            </div>

            <flux:checkbox
                name="remember"
                label="Lembrar de mim"
                :checked="old('remember')"
            />

            <flux:button
                variant="primary"
                type="submit"
                class="w-full !bg-emerald-500 !text-white hover:!bg-emerald-600 dark:!bg-emerald-500 dark:!text-white dark:hover:!bg-emerald-400"
                data-test="login-button"
            >
                Entrar no Fechou
            </flux:button>
        </form>

        <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-center dark:border-zinc-800 dark:bg-zinc-900/60">
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                Ainda não usa o Fechou?

                <flux:link :href="route('register')" wire:navigate>
                    Crie sua conta grátis
                </flux:link>
            </p>

            <p class="mt-1 text-xs text-zinc-400">
                Sem cartão · 5 propostas por mês
            </p>
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
