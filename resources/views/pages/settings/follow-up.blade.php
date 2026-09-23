<?php

use App\Enums\PlanFeature;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Follow-up | Fechou')] class extends Component
{
    public bool $enabled = true;

    public int $sentAfterDays = 2;
    public int $viewedAfterDays = 2;
    public int $expiryWarningDays = 1;
    public int $cooldownHours = 24;

    #[Computed]
    public function hasAccess(): bool
    {
        $business = Auth::user()->business;

        if (! $business) {
            return false;
        }

        return app(SubscriptionService::class)
            ->hasFeature(
                $business,
                PlanFeature::FOLLOW_UP
            );
    }

    public function mount(): void
    {
        $business = Auth::user()->business;

        abort_unless($business, 403);

        /*
         * Usuários do plano Grátis podem abrir a página para
         * conhecer o recurso, mas não carregam nem alteram
         * configurações de follow-up.
         */
        if (! $this->hasAccess) {
            return;
        }

        $this->enabled =
            (bool) $business->follow_up_enabled;

        $this->sentAfterDays =
            $business->follow_up_sent_after_days ?? 2;

        $this->viewedAfterDays =
            $business->follow_up_viewed_after_days ?? 2;

        $this->expiryWarningDays =
            $business->follow_up_expiry_warning_days ?? 1;

        $this->cooldownHours =
            $business->follow_up_cooldown_hours ?? 24;
    }

    public function save(): void
    {
        $business = Auth::user()->business;

        abort_unless($business, 403);

        if (
            ! app(SubscriptionService::class)->hasFeature(
                $business,
                PlanFeature::FOLLOW_UP
            )
        ) {
            return;
        }

        $validated = $this->validate([
            'enabled' => [
                'boolean',
            ],

            'sentAfterDays' => [
                'required',
                'integer',
                'min:1',
                'max:30',
            ],

            'viewedAfterDays' => [
                'required',
                'integer',
                'min:1',
                'max:30',
            ],

            'expiryWarningDays' => [
                'required',
                'integer',
                'min:0',
                'max:30',
            ],

            'cooldownHours' => [
                'required',
                'integer',
                'min:1',
                'max:168',
            ],
        ]);

        $business->update([
            'follow_up_enabled' =>
                $validated['enabled'],

            'follow_up_sent_after_days' =>
                $validated['sentAfterDays'],

            'follow_up_viewed_after_days' =>
                $validated['viewedAfterDays'],

            'follow_up_expiry_warning_days' =>
                $validated['expiryWarningDays'],

            'follow_up_cooldown_hours' =>
                $validated['cooldownHours'],
        ]);

        session()->flash(
            'success',
            'Configurações de follow-up atualizadas.'
        );
    }
};
?>

<div class="mx-auto w-full max-w-6xl space-y-5">

    {{-- CABEÇALHO --}}

    <div>

        <h1
            class="
                text-2xl font-semibold
                tracking-tight
                text-zinc-950
                dark:text-white
            "
        >
            Follow-up
        </h1>

        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
            Defina quando o Fechou deve destacar propostas que precisam de atenção.
        </p>

    </div>


    @if (! $this->hasAccess)

        <section
            class="
                overflow-hidden
                rounded-2xl
                border border-violet-200
                bg-white
                shadow-sm

                dark:border-violet-900/70
                dark:bg-zinc-900
            "
        >
            <div
                class="
                    bg-gradient-to-br
                    from-violet-50
                    via-white
                    to-emerald-50
                    p-5

                    sm:p-6

                    dark:from-violet-950/40
                    dark:via-zinc-900
                    dark:to-emerald-950/20
                "
            >
                <div
                    class="
                        inline-flex
                        items-center
                        gap-2
                        rounded-full
                        bg-violet-100
                        px-3 py-1
                        text-xs
                        font-bold
                        uppercase
                        tracking-wide
                        text-violet-700

                        dark:bg-violet-950
                        dark:text-violet-300
                    "
                >
                    Fechou Pro
                </div>

                <h2
                    class="
                        mt-5
                        text-xl
                        font-semibold
                        tracking-tight
                        text-zinc-950

                        dark:text-white
                    "
                >
                    Transforme propostas paradas em novas oportunidades
                </h2>

                <p
                    class="
                        mt-2
                        max-w-2xl
                        text-sm
                        leading-6
                        text-zinc-600

                        dark:text-zinc-400
                    "
                >
                    O follow-up inteligente identifica propostas enviadas,
                    visualizadas ou próximas do vencimento e ajuda você a
                    retomar o contato no momento certo.
                </p>

                <div
                    class="
                        mt-5
                        grid
                        gap-2

                        sm:grid-cols-2
                    "
                >
                    @foreach ([
                        'Alertas de propostas sem resposta',
                        'Avisos antes do vencimento',
                        'Follow-up rápido pelo WhatsApp',
                        'Controle de intervalo entre contatos',
                    ] as $feature)

                        <div
                            class="
                                flex
                                items-center
                                gap-2.5
                                px-1 py-1.5
                                text-sm
                                font-medium
                                text-zinc-700

                                dark:text-zinc-300
                            "
                        >
                            <svg
                                class="size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.5"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M5 12l4 4L19 6"
                                />
                            </svg>

                            {{ $feature }}
                        </div>

                    @endforeach
                </div>

                <div
                    class="
                        mt-5
                        flex
                        flex-col
                        gap-2

                        sm:flex-row
                        sm:items-center
                    "
                >
                    <a
                        href="{{ route('settings.subscription') }}"
                        wire:navigate
                        class="
                            inline-flex
                            items-center
                            justify-center
                            rounded-lg
                            bg-violet-600
                            px-4 py-2.5
                            text-sm
                            font-semibold
                            text-white
                            shadow-sm
                            transition
                            hover:bg-violet-700

                            dark:bg-violet-500
                            dark:text-zinc-950
                            dark:hover:bg-violet-400
                        "
                    >
                        Conhecer o Fechou Pro
                    </a>

                    <span class="text-sm text-zinc-500 dark:text-zinc-400">
                        Propostas ilimitadas + recursos avançados
                    </span>
                </div>
            </div>
        </section>

    @else


    @if (session('success'))

        <div
            class="
                rounded-xl
                border border-emerald-200
                bg-emerald-50
                px-4 py-3
                text-sm font-medium
                text-emerald-800

                dark:border-emerald-900
                dark:bg-emerald-950/40
                dark:text-emerald-300
            "
        >
            {{ session('success') }}
        </div>

    @endif


    <form
        wire:submit="save"
        class="space-y-5"
    >

        {{-- ATIVAR --}}

        <section
            class="
                rounded-2xl
                border border-zinc-200
                bg-white
                p-5
                shadow-sm

                dark:border-zinc-800
                dark:bg-zinc-900
            "
        >

            <div class="flex items-start justify-between gap-5">

                <div>

                    <h2 class="font-semibold text-zinc-950 dark:text-white">
                        Follow-up inteligente
                    </h2>

                    <p
                        class="
                            mt-1 max-w-xl
                            text-sm leading-6
                            text-zinc-500
                            dark:text-zinc-400
                        "
                    >
                        Mostra no Dashboard propostas enviadas ou visualizadas
                        que estão há algum tempo sem resposta.
                    </p>

                </div>


                <label class="relative inline-flex cursor-pointer items-center">

                    <input
                        type="checkbox"
                        wire:model="enabled"
                        class="peer sr-only"
                    >

                    <div
                        class="
                            h-6 w-11 rounded-full

                            bg-zinc-300

                            transition

                            after:absolute
                            after:left-[2px]
                            after:top-[2px]
                            after:size-5
                            after:rounded-full
                            after:bg-white
                            after:transition-all
                            after:content-['']

                            peer-checked:bg-emerald-600
                            peer-checked:after:translate-x-full

                            dark:bg-zinc-700
                            dark:peer-checked:bg-emerald-500
                        "
                    ></div>

                </label>

            </div>

        </section>


        {{-- REGRAS --}}

        <section
            class="
                overflow-hidden
                rounded-2xl
                border border-zinc-200
                bg-white
                shadow-sm

                dark:border-zinc-800
                dark:bg-zinc-900
            "
        >

            <div
                class="
                    border-b border-zinc-200
                    px-5 py-4
                    dark:border-zinc-800
                "
            >

                <h2 class="font-semibold text-zinc-950 dark:text-white">
                    Quando chamar minha atenção?
                </h2>

                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    Essas regras alimentam a área “Precisam de atenção” do Dashboard.
                </p>

            </div>


            <div class="divide-y divide-zinc-100 dark:divide-zinc-800">

                {{-- NÃO VISUALIZADA --}}

                <div
                    class="
                        grid gap-4
                        px-5 py-4

                        md:grid-cols-[1fr_190px]
                        md:items-center
                    "
                >

                    <div>

                        <p class="font-medium text-zinc-900 dark:text-zinc-100">
                            Proposta enviada e não visualizada
                        </p>

                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            Lembrar quando o cliente ainda não abriu o orçamento.
                        </p>

                    </div>


                    <select
                        wire:model="sentAfterDays"
                        class="
                            w-full rounded-lg
                            border border-zinc-300
                            bg-white
                            px-3 py-2.5
                            text-sm text-zinc-900

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-white
                        "
                    >
                        <option value="1">Após 1 dia</option>
                        <option value="2">Após 2 dias</option>
                        <option value="3">Após 3 dias</option>
                        <option value="5">Após 5 dias</option>
                        <option value="7">Após 7 dias</option>
                        <option value="14">Após 14 dias</option>
                    </select>

                </div>


                {{-- VISUALIZADA --}}

                <div
                    class="
                        grid gap-4
                        px-5 py-4

                        md:grid-cols-[1fr_190px]
                        md:items-center
                    "
                >

                    <div>

                        <p class="font-medium text-zinc-900 dark:text-zinc-100">
                            Proposta visualizada sem resposta
                        </p>

                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            Lembrar depois que o cliente abriu a proposta e não decidiu.
                        </p>

                    </div>


                    <select
                        wire:model="viewedAfterDays"
                        class="
                            w-full rounded-lg
                            border border-zinc-300
                            bg-white
                            px-3 py-2.5
                            text-sm text-zinc-900

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-white
                        "
                    >
                        <option value="1">Após 1 dia</option>
                        <option value="2">Após 2 dias</option>
                        <option value="3">Após 3 dias</option>
                        <option value="5">Após 5 dias</option>
                        <option value="7">Após 7 dias</option>
                        <option value="14">Após 14 dias</option>
                    </select>

                </div>


                {{-- VENCIMENTO --}}

                <div
                    class="
                        grid gap-4
                        px-5 py-4

                        md:grid-cols-[1fr_190px]
                        md:items-center
                    "
                >

                    <div>

                        <p class="font-medium text-zinc-900 dark:text-zinc-100">
                            Proposta próxima do vencimento
                        </p>

                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            Antecipe o contato antes da validade terminar.
                        </p>

                    </div>


                    <select
                        wire:model="expiryWarningDays"
                        class="
                            w-full rounded-lg
                            border border-zinc-300
                            bg-white
                            px-3 py-2.5
                            text-sm text-zinc-900

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-white
                        "
                    >
                        <option value="0">Somente no dia</option>
                        <option value="1">1 dia antes</option>
                        <option value="2">2 dias antes</option>
                        <option value="3">3 dias antes</option>
                        <option value="5">5 dias antes</option>
                        <option value="7">7 dias antes</option>
                    </select>

                </div>


                {{-- COOLDOWN --}}

                <div
                    class="
                        grid gap-4
                        px-5 py-4

                        md:grid-cols-[1fr_190px]
                        md:items-center
                    "
                >

                    <div>

                        <p class="font-medium text-zinc-900 dark:text-zinc-100">
                            Depois de realizar um follow-up
                        </p>

                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            Tempo para a mesma proposta voltar a aparecer como pendência.
                        </p>

                    </div>


                    <select
                        wire:model="cooldownHours"
                        class="
                            w-full rounded-lg
                            border border-zinc-300
                            bg-white
                            px-3 py-2.5
                            text-sm text-zinc-900

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-white
                        "
                    >
                        <option value="12">12 horas</option>
                        <option value="24">24 horas</option>
                        <option value="48">2 dias</option>
                        <option value="72">3 dias</option>
                        <option value="168">7 dias</option>
                    </select>

                </div>

            </div>

        </section>


        {{-- EXEMPLO --}}

        <section
            class="
                rounded-2xl
                border border-amber-200
                bg-amber-50
                p-4

                dark:border-amber-900/60
                dark:bg-amber-950/20
            "
        >

            <p
                class="
                    text-xs font-semibold uppercase
                    tracking-wide
                    text-amber-700
                    dark:text-amber-400
                "
            >
                Como ficará
            </p>


            <div class="mt-3 space-y-2">

                <p class="text-sm text-zinc-700 dark:text-zinc-300">
                    • Enviada e não aberta:
                    <strong>
                        {{ $sentAfterDays }}
                        {{ $sentAfterDays === 1 ? 'dia' : 'dias' }}
                    </strong>
                </p>

                <p class="text-sm text-zinc-700 dark:text-zinc-300">
                    • Visualizada sem resposta:
                    <strong>
                        {{ $viewedAfterDays }}
                        {{ $viewedAfterDays === 1 ? 'dia' : 'dias' }}
                    </strong>
                </p>

                <p class="text-sm text-zinc-700 dark:text-zinc-300">
                    • Aviso de vencimento:
                    <strong>
                        @if ($expiryWarningDays === 0)
                            no próprio dia
                        @elseif ($expiryWarningDays === 1)
                            1 dia antes
                        @else
                            {{ $expiryWarningDays }} dias antes
                        @endif
                    </strong>
                </p>

            </div>

        </section>


        <div class="flex justify-end">

            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="save"

                class="
                    inline-flex items-center justify-center

                    rounded-lg

                    bg-emerald-600

                    px-4 py-2.5

                    text-sm font-semibold
                    text-white

                    shadow-sm

                    hover:bg-emerald-700

                    disabled:opacity-60

                    dark:bg-emerald-500
                    dark:text-zinc-950
                    dark:hover:bg-emerald-400
                "
            >

                <span
                    wire:loading.remove
                    wire:target="save"
                >
                    Salvar configurações
                </span>

                <span
                    wire:loading
                    wire:target="save"
                >
                    Salvando...
                </span>

            </button>

        </div>

    </form>

    @endif

</div>
