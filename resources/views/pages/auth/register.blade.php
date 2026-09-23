<x-layouts::auth :title="'Criar conta | Fechou'">
    <div class="flex flex-col gap-6">

        <div class="text-center">
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">
                Crie sua conta grátis
            </h1>

            <p class="mt-2 text-sm leading-6 text-zinc-500 dark:text-zinc-400">
                Comece a organizar clientes e enviar propostas profissionais em poucos minutos.
            </p>
        </div>

        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 dark:border-emerald-900/70 dark:bg-emerald-950/30">
            <div class="flex items-start gap-3">

                <span class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                    <svg viewBox="0 0 20 20" class="size-4" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/>
                    </svg>
                </span>

                <div>
                    <p class="text-sm font-semibold text-emerald-900 dark:text-emerald-200">
                        Grátis para começar
                    </p>

                    <p class="mt-0.5 text-xs leading-5 text-emerald-800/80 dark:text-emerald-300/80">
                        5 propostas por mês, sem cartão e sem compromisso.
                    </p>
                </div>

            </div>
        </div>

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-5">
            @csrf

            <flux:input
                name="name"
                label="Nome"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                placeholder="Seu nome completo"
            />

            <flux:input
                name="email"
                label="E-mail"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="voce@empresa.com.br"
            />

            <flux:input
                name="password"
                label="Senha"
                type="password"
                required
                autocomplete="new-password"
                placeholder="Crie uma senha"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <flux:input
                name="password_confirmation"
                label="Confirmar senha"
                type="password"
                required
                autocomplete="new-password"
                placeholder="Digite a senha novamente"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <flux:button
                type="submit"
                variant="primary"
                class="mt-1 w-full !bg-emerald-500 !text-white hover:!bg-emerald-600 dark:!bg-emerald-500 dark:!text-white dark:hover:!bg-emerald-400"
                data-test="register-user-button"
            >
                Criar conta grátis
            </flux:button>
        </form>

        <p class="text-center text-xs leading-5 text-zinc-400">
            Você poderá configurar sua empresa antes de criar a primeira proposta.
        </p>

        <div class="text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>Já tem uma conta?</span>

            <flux:link :href="route('login')" wire:navigate>
                Entrar
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
