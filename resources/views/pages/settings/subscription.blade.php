<?php

use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Plano e assinatura | Fechou')]
    class extends Component {

    #[Computed]
    public function business(): ?Business
    {
        return Auth::user()->business;
    }

    #[Computed]
    public function subscription(): ?Subscription
    {
        if (!$this->business) {
            return null;
        }

        return app(SubscriptionService::class)
            ->currentSubscription($this->business);
    }

    #[Computed]
    public function accessPlan(): ?Plan
    {
        if (!$this->business || !$this->subscription) {
            return null;
        }

        return app(SubscriptionService::class)
            ->accessPlan($this->business);
    }

    #[Computed]
    public function quotesUsed(): int
    {
        if (!$this->business || !$this->subscription) {
            return 0;
        }

        return app(SubscriptionService::class)
            ->quotesUsed($this->business);
    }

    #[Computed]
    public function quotesRemaining(): ?int
    {
        if (!$this->business || !$this->subscription) {
            return 0;
        }

        return app(SubscriptionService::class)
            ->quotesRemaining($this->business);
    }

    #[Computed]
    public function usagePercentage(): int
    {
        $limit = $this->accessPlan?->quote_limit;

        if (!$limit) {
            return 0;
        }

        return (int) min(
            100,
            round(
                ($this->quotesUsed / $limit) * 100
            )
        );
    }

    #[Computed]
    public function freePlan(): ?Plan
    {
        return Plan::query()
            ->where('slug', 'free')
            ->where('is_active', true)
            ->first();
    }

    #[Computed]
    public function proPlan(): ?Plan
    {
        return Plan::query()
            ->where('slug', 'pro')
            ->where('is_active', true)
            ->first();
    }

    #[Computed]
    public function isFree(): bool
    {
        return $this->subscription?->plan?->slug === 'free';
    }

    #[Computed]
    public function isPro(): bool
    {
        return $this->subscription?->plan?->slug === 'pro';
    }

    #[Computed]
    public function hasProAccess(): bool
    {
        return $this->accessPlan?->slug === 'pro';
    }

    #[Computed]
    public function isInGracePeriod(): bool
    {
        return $this->subscription?->isInGracePeriod() === true;
    }

    #[Computed]
    public function isAccessSuspended(): bool
    {
        if (!$this->isPro || !$this->subscription) {
            return false;
        }

        if ($this->subscription->access_suspended_at) {
            return true;
        }

        return $this->subscription->status === 'past_due'
            && !$this->isInGracePeriod;
    }

    #[Computed]
    public function isCanceling(): bool
    {
        if (!$this->subscription) {
            return false;
        }

        return $this->subscription->billing_status === 'canceling'
            && $this->subscription->ends_at?->isFuture();
    }

    public function statusLabel(): string
    {
        if ($this->isCanceling) {
            return 'Cancelamento agendado';
        }

        if ($this->isAccessSuspended) {
            return 'Acesso Pro suspenso';
        }

        if ($this->isInGracePeriod) {
            return 'Pagamento pendente';
        }

        return match ($this->subscription?->status) {
            'trialing' => 'Período de teste',
            'active' => 'Ativo',
            'past_due' => 'Pagamento pendente',
            'canceled' => 'Cancelado',
            'expired' => 'Expirado',
            default => 'Indisponível',
        };
    }

    public function statusDescription(): string
    {
        if ($this->isCanceling) {
            return 'Seu Pro permanece disponível até '
                . $this->formatDate(
                    $this->subscription?->ends_at
                )
                . '.';
        }

        if ($this->isAccessSuspended) {
            return 'Os recursos Pro estão suspensos. '
                . 'Sua conta continua com os recursos do plano Grátis.';
        }

        if ($this->isInGracePeriod) {
            return 'Há uma cobrança pendente. '
                . 'Seus recursos Pro seguem disponíveis até '
                . $this->formatDate(
                    $this->subscription?->grace_ends_at
                )
                . '.';
        }

        if ($this->isFree) {
            return 'Seu plano Grátis está ativo.';
        }

        return match ($this->subscription?->status) {
            'trialing' =>
                'Seu período de avaliação está em andamento.',

            'active' =>
                'Sua assinatura está ativa e disponível para uso.',

            'past_due' =>
                'Existe uma pendência relacionada à assinatura.',

            'canceled' =>
                'Esta assinatura foi cancelada.',

            'expired' =>
                'O período desta assinatura terminou.',

            default =>
                'Não foi possível identificar a situação da assinatura.',
        };
    }

    public function statusClasses(): string
    {
        if ($this->isCanceling) {
            return 'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-500/10 dark:text-blue-400';
        }

        if ($this->isAccessSuspended) {
            return 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-400';
        }

        if ($this->isInGracePeriod) {
            return 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400';
        }

        return match ($this->subscription?->status) {
            'active' =>
                'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400',

            'trialing' =>
                'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-500/10 dark:text-blue-400',

            'past_due' =>
                'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400',

            'canceled',
            'expired' =>
                'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-400',

            default =>
                'bg-zinc-100 text-zinc-600 ring-zinc-500/20 dark:bg-zinc-800 dark:text-zinc-300',
        };
    }

    public function billingAlertTitle(): string
    {
        if ($this->isAccessSuspended) {
            return match ($this->subscription?->billing_status) {
                'refunded' => 'Pagamento estornado',
                'chargeback' => 'Pagamento contestado',
                'reversed' => 'Pagamento revertido',
                default => 'Acesso Pro suspenso',
            };
        }

        if ($this->isInGracePeriod) {
            return 'Pagamento pendente';
        }

        if ($this->isCanceling) {
            return 'Cancelamento agendado';
        }

        return '';
    }

    public function billingAlertDescription(): string
    {
        if ($this->isAccessSuspended) {
            return 'Regularize a situação da cobrança para recuperar '
                . 'os recursos Pro. Seus dados e configurações Pro '
                . 'continuam preservados.';
        }

        if ($this->isInGracePeriod) {
            return 'Regularize a cobrança até '
                . $this->formatDate(
                    $this->subscription?->grace_ends_at
                )
                . '. Até essa data, o Fechou Pro continua funcionando normalmente.';
        }

        if ($this->isCanceling) {
            return 'Sua renovação foi cancelada, mas o Fechou Pro '
                . 'continua disponível até '
                . $this->formatDate(
                    $this->subscription?->ends_at
                )
                . '.';
        }

        return '';
    }

    public function planPriceLabel(): string
    {
        if (!$this->subscription) {
            return '—';
        }

        $plan = $this->subscription->plan;

        if ($plan->isFree()) {
            return 'Sem custo';
        }

        return 'R$ '
            . number_format(
                (float) $plan->price,
                2,
                ',',
                '.'
            )
            . ' / mês';
    }

    public function proPriceLabel(): string
    {
        $price = $this->proPlan?->price ?? 29.90;

        return number_format(
            (float) $price,
            2,
            ',',
            '.'
        );
    }

    public function featureLabel(string $feature): string
    {
        return match ($feature) {
            'client_management' =>
                'Gestão de clientes',

            'public_quote_link' =>
                'Link público para propostas',

            'pdf_export' =>
                'Exportação em PDF',

            'whatsapp_sharing' =>
                'Compartilhamento pelo WhatsApp',

            'quote_versioning' =>
                'Versionamento de propostas',

            'follow_up' =>
                'Follow-up inteligente',

            'notifications' =>
                'Central de notificações',

            'custom_branding' =>
                'Logo e personalização da marca',

            'advanced_reports' =>
                'Relatórios avançados',

            'team_members' =>
                'Usuários e equipe',

            default =>
                Str::headline($feature),
        };
    }

    public function formatDate($date): string
    {
        return $date
            ? $date->format('d/m/Y')
            : '—';
    }

    public function nextCycleDate(): string
    {
        $date = $this->subscription
                ?->current_period_ends_at;

        if (!$date) {
            return '—';
        }

        return $date
            ->copy()
            ->addDay()
            ->format('d/m/Y');
    }
};
?>

<div class="mx-auto w-full max-w-6xl space-y-5">

    {{-- ========================================================= --}}
    {{-- CABEÇALHO --}}
    {{-- ========================================================= --}}

    <div class="
            flex flex-col gap-4
            sm:flex-row
            sm:items-end
            sm:justify-between
        ">

        <div>

            <h1 class="
                    text-2xl
                    font-semibold
                    tracking-tight
                    text-zinc-950
                    dark:text-white
                ">
                Plano e assinatura
            </h1>

            <p class="
                    mt-1
                    text-sm
                    text-zinc-500
                    dark:text-zinc-400
                ">
                Acompanhe seu uso e compare os planos do Fechou.
            </p>

        </div>

        @if ($this->subscription)

            <span class="
                        inline-flex w-fit
                        items-center gap-1.5
                        rounded-full
                        px-3 py-1.5
                        text-xs font-semibold
                        ring-1 ring-inset
                        {{ $this->statusClasses() }}
                    ">

                <span class="size-1.5 rounded-full bg-current"></span>

                {{ $this->statusLabel() }}

            </span>

        @endif

    </div>


    {{-- ========================================================= --}}
    {{-- RETORNO / AVISOS DO CHECKOUT --}}
    {{-- ========================================================= --}}

    @if (session('billing_error'))

        <div class="
                    rounded-xl
                    border border-red-200
                    bg-red-50
                    px-4 py-3
                    text-sm font-medium
                    text-red-800

                    dark:border-red-900
                    dark:bg-red-950/40
                    dark:text-red-300
                ">
            {{ session('billing_error') }}
        </div>

    @endif

    @if (session('billing_info'))

        <div class="
                    rounded-xl
                    border border-blue-200
                    bg-blue-50
                    px-4 py-3
                    text-sm font-medium
                    text-blue-800

                    dark:border-blue-900
                    dark:bg-blue-950/40
                    dark:text-blue-300
                ">
            {{ session('billing_info') }}
        </div>

    @endif

    @if (request()->query('checkout') === 'success')

        <div class="
                    rounded-xl
                    border border-emerald-200
                    bg-emerald-50
                    px-4 py-3
                    text-sm
                    text-emerald-800

                    dark:border-emerald-900
                    dark:bg-emerald-950/40
                    dark:text-emerald-300
                ">
            Checkout concluído. Estamos aguardando a confirmação
            financeira do Asaas para liberar o Fechou Pro.
        </div>

    @elseif (request()->query('checkout') === 'canceled')

        <div class="
                    rounded-xl
                    border border-zinc-200
                    bg-zinc-50
                    px-4 py-3
                    text-sm
                    text-zinc-700

                    dark:border-zinc-800
                    dark:bg-zinc-900
                    dark:text-zinc-300
                ">
            O checkout foi cancelado. Nenhuma alteração foi feita
            no seu plano.
        </div>

    @elseif (request()->query('checkout') === 'expired')

        <div class="
                    rounded-xl
                    border border-amber-200
                    bg-amber-50
                    px-4 py-3
                    text-sm
                    text-amber-800

                    dark:border-amber-900
                    dark:bg-amber-950/40
                    dark:text-amber-300
                ">
            Este checkout expirou. Você pode iniciar uma nova
            tentativa de assinatura.
        </div>

    @endif


    {{-- ========================================================= --}}
    {{-- SITUAÇÃO FINANCEIRA / CICLO DE COBRANÇA --}}
    {{-- ========================================================= --}}

    @if (
            $this->subscription
            && (
                $this->isInGracePeriod
                || $this->isAccessSuspended
                || $this->isCanceling
            )
        )

        <section class="
                rounded-2xl border px-5 py-4
                {{
            $this->isAccessSuspended
            ? 'border-red-200 bg-red-50 dark:border-red-900/70 dark:bg-red-950/30'
            : (
                $this->isInGracePeriod
                ? 'border-amber-200 bg-amber-50 dark:border-amber-900/70 dark:bg-amber-950/30'
                : 'border-blue-200 bg-blue-50 dark:!border-blue-800/80 dark:!bg-zinc-900 dark:ring-1 dark:ring-inset dark:ring-blue-500/10'
            )
                }}
            ">

            <div class="flex items-start gap-4">

                <div class="
                        mt-0.5 flex size-10 shrink-0
                        items-center justify-center
                        rounded-xl
                        {{
            $this->isAccessSuspended
            ? 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400'
            : (
                $this->isInGracePeriod
                ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400'
                : 'bg-blue-100 text-blue-700 dark:!bg-blue-500/15 dark:!text-blue-300'
            )
                        }}
                    ">

                    @if ($this->isAccessSuspended)
                        <flux:icon.x-circle class="size-5" />
                    @elseif ($this->isInGracePeriod)
                        <flux:icon.exclamation-triangle class="size-5" />
                    @else
                        <flux:icon.clock class="size-5" />
                    @endif

                </div>

                <div class="min-w-0">

                    <h2 class="
                            text-sm font-semibold
                            {{
            $this->isAccessSuspended
            ? 'text-red-900 dark:text-red-200'
            : (
                $this->isInGracePeriod
                ? 'text-amber-900 dark:text-amber-200'
                : 'text-blue-900 dark:!text-blue-200'
            )
                            }}
                        ">
                        {{ $this->billingAlertTitle() }}
                    </h2>

                    <p class="
                            mt-1 text-sm leading-6
                            {{
            $this->isAccessSuspended
            ? 'text-red-800 dark:text-red-300'
            : (
                $this->isInGracePeriod
                ? 'text-amber-800 dark:text-amber-300'
                : 'text-blue-800 dark:!text-zinc-300'
            )
                            }}
                        ">
                        {{ $this->billingAlertDescription() }}
                    </p>

                    @if ($this->isAccessSuspended)

                        <p class="
                                    mt-2 text-xs font-medium
                                    text-red-700/80
                                    dark:text-red-300/80
                                ">
                            Enquanto a pendência permanecer, sua conta
                            utiliza os recursos disponíveis no plano Grátis.
                        </p>

                    @endif

                </div>

            </div>

        </section>

    @endif


    {{-- ========================================================= --}}
    {{-- SEM EMPRESA --}}
    {{-- ========================================================= --}}

    @if (!$this->business)

        <section class="
                    rounded-2xl
                    border border-zinc-200
                    bg-white
                    p-5
                    shadow-sm

                    dark:border-zinc-800
                    dark:bg-zinc-900
                ">

            <div class="flex items-start gap-4">

                <div class="
                            flex size-11 shrink-0
                            items-center justify-center
                            rounded-xl
                            bg-zinc-100
                            text-zinc-600

                            dark:bg-zinc-800
                            dark:text-zinc-300
                        ">
                    <flux:icon.building-office class="size-5" />
                </div>

                <div>

                    <h2 class="font-semibold text-zinc-950 dark:text-white">
                        Configure sua empresa
                    </h2>

                    <p class="
                                mt-1
                                text-sm leading-6
                                text-zinc-500
                                dark:text-zinc-400
                            ">
                        Antes de consultar sua assinatura,
                        configure os dados da sua empresa.
                    </p>

                    <flux:button class="mt-4" variant="primary" href="{{ route('settings.business') }}" wire:navigate>
                        Configurar empresa
                    </flux:button>

                </div>

            </div>

        </section>


        {{-- ========================================================= --}}
        {{-- SEM ASSINATURA --}}
        {{-- ========================================================= --}}

    @elseif (!$this->subscription)

        <section class="
                    rounded-2xl
                    border border-zinc-200
                    bg-white
                    p-5
                    shadow-sm

                    dark:border-zinc-800
                    dark:bg-zinc-900
                ">

            <div class="flex items-start gap-4">

                <div class="
                            flex size-11 shrink-0
                            items-center justify-center
                            rounded-xl
                            bg-amber-50
                            text-amber-600

                            dark:bg-amber-500/10
                            dark:text-amber-400
                        ">
                    <flux:icon.exclamation-triangle class="size-5" />
                </div>

                <div>

                    <h2 class="font-semibold text-zinc-950 dark:text-white">
                        Assinatura não encontrada
                    </h2>

                    <p class="
                                mt-1
                                text-sm leading-6
                                text-zinc-500
                                dark:text-zinc-400
                            ">
                        Não foi possível identificar uma assinatura
                        atual para sua empresa.
                    </p>

                </div>

            </div>

        </section>


        {{-- ========================================================= --}}
        {{-- COM ASSINATURA --}}
        {{-- ========================================================= --}}

    @else

        {{-- ===================================================== --}}
        {{-- PLANO ATUAL / USO --}}
        {{-- ===================================================== --}}

        <section class="
                    overflow-hidden
                    rounded-2xl
                    border border-zinc-200
                    bg-white
                    shadow-sm

                    dark:border-zinc-800
                    dark:bg-zinc-900
                ">

            <div class="
                        flex flex-col gap-5
                        border-b border-zinc-200
                        p-5

                        lg:flex-row
                        lg:items-center
                        lg:justify-between

                        dark:border-zinc-800
                    ">

                <div>

                    <p class="
                                text-xs font-semibold
                                uppercase tracking-wide
                                text-zinc-400
                            ">
                        Seu plano atual
                    </p>

                    <div class="mt-2 flex flex-wrap items-center gap-3">

                        <h2 class="
                                    text-2xl font-bold
                                    tracking-tight
                                    text-zinc-950
                                    dark:text-white
                                ">
                            {{ $this->subscription->plan->name }}
                        </h2>

                        @if ($this->isPro)

                            <span class="
                                            rounded-full
                                            bg-violet-100
                                            px-2.5 py-1
                                            text-[11px] font-bold
                                            text-violet-700

                                            dark:bg-violet-950
                                            dark:text-violet-300
                                        ">
                                PRO
                            </span>

                        @endif

                    </div>

                    <p class="
                                mt-1
                                text-sm
                                text-zinc-500
                                dark:text-zinc-400
                            ">
                        {{ $this->planPriceLabel() }}
                    </p>

                </div>


                <div class="
                            grid gap-4
                            sm:grid-cols-2
                            lg:min-w-[430px]
                        ">

                    <div class="
                                rounded-xl
                                bg-zinc-50
                                p-4

                                dark:bg-zinc-950/70
                            ">

                        <p class="
                                    text-xs font-medium
                                    uppercase tracking-wide
                                    text-zinc-400
                                ">
                            Propostas no ciclo
                        </p>

                        <div class="mt-2 flex items-baseline gap-2">

                            <span class="
                                        text-xl font-semibold
                                        text-zinc-950
                                        dark:text-white
                                    ">
                                {{ $this->quotesUsed }}
                            </span>

                            <span class="
                                        text-sm
                                        text-zinc-500
                                        dark:text-zinc-400
                                    ">
                                utilizadas
                            </span>

                        </div>

                        @if (
                                $this->accessPlan
                                        ?->hasUnlimitedQuotes()
                            )

                            <p class="
                                            mt-2
                                            text-xs font-semibold
                                            text-emerald-600
                                            dark:text-emerald-400
                                        ">
                                Sem limite mensal
                            </p>

                        @else

                            <div class="mt-3">

                                <div class="
                                                mb-2
                                                flex items-center justify-between
                                                gap-3
                                                text-xs
                                            ">

                                    <span class="
                                                    text-zinc-500
                                                    dark:text-zinc-400
                                                ">
                                        {{ $this->quotesRemaining }}
                                        restante(s)
                                    </span>

                                    <span class="
                                                    font-medium
                                                    text-zinc-600
                                                    dark:text-zinc-300
                                                ">
                                        {{ $this->accessPlan?->quote_limit ?? 0 }}
                                        no total
                                    </span>

                                </div>

                                <div class="
                                                h-1.5
                                                overflow-hidden
                                                rounded-full
                                                bg-zinc-200

                                                dark:bg-zinc-800
                                            ">

                                    <div class="
                                                    h-full
                                                    rounded-full
                                                    bg-emerald-500
                                                " style="width: {{ $this->usagePercentage }}%;"></div>

                                </div>

                            </div>

                        @endif

                    </div>


                    <div class="
                                rounded-xl
                                bg-zinc-50
                                p-4

                                dark:bg-zinc-950/70
                            ">

                        <p class="
                                    text-xs font-medium
                                    uppercase tracking-wide
                                    text-zinc-400
                                ">
                            Ciclo atual
                        </p>

                        <p class="
                                    mt-2
                                    text-sm font-semibold
                                    text-zinc-950
                                    dark:text-white
                                ">
                            {{
            $this->formatDate(
                $this->subscription
                    ->current_period_starts_at
            )
                                }}
                        </p>

                        <p class="
                                    mt-1
                                    text-sm
                                    text-zinc-500
                                    dark:text-zinc-400
                                ">
                            até
                            {{
            $this->formatDate(
                $this->subscription
                    ->current_period_ends_at
            )
                                }}
                        </p>

                    </div>

                </div>

            </div>


            <div class="
                        grid gap-px
                        bg-zinc-200

                        sm:grid-cols-3

                        dark:bg-zinc-800
                    ">

                <div class="
                            bg-white px-5 py-3.5
                            dark:bg-zinc-900
                        ">

                    <p class="
                                text-xs font-medium
                                uppercase tracking-wide
                                text-zinc-400
                            ">
                        Situação
                    </p>

                    <p class="
                                mt-1
                                text-sm font-medium
                                text-zinc-700
                                dark:text-zinc-200
                            ">
                        {{ $this->statusDescription() }}
                    </p>

                </div>

                <div class="
                            bg-white px-5 py-3.5
                            dark:bg-zinc-900
                        ">

                    <p class="
                                text-xs font-medium
                                uppercase tracking-wide
                                text-zinc-400
                            ">
                        Início da assinatura
                    </p>

                    <p class="
                                mt-1
                                text-sm font-medium
                                text-zinc-700
                                dark:text-zinc-200
                            ">
                        {{
            $this->formatDate(
                $this->subscription->starts_at
            )
                            }}
                    </p>

                </div>

                <div class="
                            bg-white px-5 py-3.5
                            dark:bg-zinc-900
                        ">

                    <p class="
                                text-xs font-medium
                                uppercase tracking-wide
                                text-zinc-400
                            ">
                        Próximo ciclo
                    </p>

                    <p class="
                                mt-1
                                text-sm font-medium
                                text-zinc-700
                                dark:text-zinc-200
                            ">
                        {{ $this->nextCycleDate() }}
                    </p>

                </div>

            </div>

        </section>


        {{-- ===================================================== --}}
        {{-- CHAMADA PRO PARA QUEM ESTÁ NO FREE --}}
        {{-- ===================================================== --}}

        @if ($this->isFree)

            <section class="
                            overflow-hidden
                            rounded-2xl
                            border border-violet-200
                            bg-gradient-to-br
                            from-violet-50
                            to-white
                            shadow-sm

                            dark:border-violet-900/70
                            dark:from-violet-950/30
                            dark:to-zinc-900
                        ">

                <div class="
                                grid gap-6
                                p-5

                                lg:grid-cols-[1fr_auto]
                                lg:items-center
                            ">

                    <div>

                        <span class="
                                        inline-flex
                                        rounded-full
                                        bg-violet-600
                                        px-2.5 py-1
                                        text-[11px] font-bold
                                        text-white

                                        dark:bg-violet-500
                                        dark:text-zinc-950
                                    ">
                            FECHOU PRO
                        </span>

                        <h2 class="
                                        mt-4
                                        text-xl font-bold
                                        tracking-tight
                                        text-zinc-950

                                        dark:text-white
                                    ">
                            Pare de contar propostas.
                            Comece a acompanhar oportunidades.
                        </h2>

                        <p class="
                                        mt-2 max-w-2xl
                                        text-sm leading-6
                                        text-zinc-600

                                        dark:text-zinc-300
                                    ">
                            No Pro você cria propostas sem limite,
                            acompanha clientes que visualizaram,
                            faz follow-up e mantém sua marca em cada envio.
                        </p>

                        <div class="
                                        mt-5
                                        grid gap-2
                                        text-sm
                                        text-zinc-700

                                        sm:grid-cols-2

                                        dark:text-zinc-200
                                    ">

                            @foreach ([
                                    'Propostas ilimitadas',
                                    'Versionamento de propostas',
                                    'Follow-up inteligente',
                                    'Central de notificações',
                                    'Logo personalizada',
                                    'Todos os recursos do plano Grátis',
                                ] as $benefit)

                                <div class="flex items-center gap-2">

                                    <span class="
                                                        flex size-5 shrink-0
                                                        items-center justify-center
                                                        rounded-full
                                                        bg-emerald-100
                                                        text-emerald-700

                                                        dark:bg-emerald-950
                                                        dark:text-emerald-300
                                                    ">
                                        <flux:icon.check class="size-3.5" />
                                    </span>

                                    {{ $benefit }}

                                </div>

                            @endforeach

                        </div>

                    </div>


                    <div class="
                                    rounded-2xl
                                    border border-violet-200
                                    bg-white
                                    p-5

                                    lg:w-64

                                    dark:border-violet-900/60
                                    dark:bg-zinc-950/80
                                ">

                        <p class="
                                        text-xs font-medium
                                        text-zinc-500
                                        dark:text-zinc-400
                                    ">
                            Fechou Pro
                        </p>

                        <div class="mt-1 flex items-end gap-1">

                            <span class="
                                            text-sm font-semibold
                                            text-zinc-500
                                            dark:text-zinc-400
                                        ">
                                R$
                            </span>

                            <span class="
                                            text-3xl font-bold
                                            tracking-tight
                                            text-zinc-950
                                            dark:text-white
                                        ">
                                {{ $this->proPriceLabel() }}
                            </span>

                            <span class="
                                            pb-1
                                            text-sm
                                            text-zinc-500
                                            dark:text-zinc-400
                                        ">
                                /mês
                            </span>

                        </div>

                        <form method="POST" action="{{ route('settings.subscription.checkout.asaas') }}" class="mt-5">
                            @csrf

                            <button type="submit" class="
                                            inline-flex w-full
                                            items-center justify-center
                                            gap-2
                                            rounded-lg
                                            bg-violet-600
                                            px-4 py-2.5
                                            text-sm font-semibold
                                            text-white
                                            shadow-sm
                                            transition
                                            hover:bg-violet-700

                                            dark:bg-violet-500
                                            dark:text-zinc-950
                                            dark:hover:bg-violet-400
                                        ">
                                Assinar Fechou Pro
                            </button>
                        </form>

                        <p class="
                                        mt-2
                                        text-center
                                        text-[11px] leading-5
                                        text-zinc-400
                                    ">
                            Pagamento seguro no ambiente do Asaas.
                        </p>

                    </div>

                </div>

            </section>

        @endif


        {{-- ===================================================== --}}
        {{-- COMPARAÇÃO DE PLANOS --}}
        {{-- ===================================================== --}}

        <section id="planos" class="space-y-4">

            <div>

                <h2 class="
                            text-lg font-semibold
                            text-zinc-950
                            dark:text-white
                        ">
                    Compare os planos
                </h2>

                <p class="
                            mt-1
                            text-sm
                            text-zinc-500
                            dark:text-zinc-400
                        ">
                    Escolha o nível de acompanhamento que faz sentido
                    para o seu negócio.
                </p>

            </div>


            <div class="
                        grid gap-5
                        lg:grid-cols-2
                    ">

                {{-- PLANO GRÁTIS --}}

                <article class="
                            relative
                            rounded-2xl
                            border
                            bg-white
                            p-5
                            shadow-sm

                            {{ $this->isFree
            ? 'border-emerald-300 ring-1 ring-emerald-200 dark:border-emerald-800 dark:ring-emerald-900/50'
            : 'border-zinc-200 dark:border-zinc-800'
                            }}

                            dark:bg-zinc-900
                        ">

                    @if ($this->isFree)

                        <span class="
                                        absolute right-5 top-5
                                        rounded-full
                                        bg-emerald-100
                                        px-2.5 py-1
                                        text-[10px] font-bold
                                        text-emerald-700

                                        dark:bg-emerald-950
                                        dark:text-emerald-300
                                    ">
                            PLANO ATUAL
                        </span>

                    @endif

                    <p class="
                                text-sm font-semibold
                                text-zinc-500
                                dark:text-zinc-400
                            ">
                        Grátis
                    </p>

                    <div class="mt-2 flex items-end gap-1">

                        <span class="
                                    text-3xl font-bold
                                    tracking-tight
                                    text-zinc-950
                                    dark:text-white
                                ">
                            R$ 0
                        </span>

                        <span class="
                                    pb-1
                                    text-sm
                                    text-zinc-500
                                    dark:text-zinc-400
                                ">
                            /mês
                        </span>

                    </div>

                    <p class="
                                mt-3
                                text-sm leading-6
                                text-zinc-500
                                dark:text-zinc-400
                            ">
                        Para começar a organizar clientes e enviar
                        propostas profissionais.
                    </p>

                    <div class="
                                my-5
                                h-px
                                bg-zinc-200
                                dark:bg-zinc-800
                            "></div>

                    <div class="space-y-3 text-sm">

                        @foreach ([
                                'Até 5 novas propostas por mês',
                                'Gestão de clientes',
                                'Link público para propostas',
                                'Exportação em PDF',
                                'Compartilhamento pelo WhatsApp',
                                'Aceite e recusa da proposta',
                            ] as $feature)

                            <div class="
                                            flex items-start gap-2.5
                                            text-zinc-700
                                            dark:text-zinc-200
                                        ">

                                <flux:icon.check class="
                                                mt-0.5 size-4 shrink-0
                                                text-emerald-600
                                                dark:text-emerald-400
                                            " />

                                <span>{{ $feature }}</span>

                            </div>

                        @endforeach

                    </div>

                    <div class="mt-6">

                        @if ($this->isFree)

                            <div class="
                                            flex w-full
                                            items-center justify-center
                                            rounded-lg
                                            border border-zinc-200
                                            bg-zinc-50
                                            px-4 py-2.5
                                            text-sm font-semibold
                                            text-zinc-500

                                            dark:border-zinc-800
                                            dark:bg-zinc-950
                                            dark:text-zinc-400
                                        ">
                                Seu plano atual
                            </div>

                        @else

                            <div class="
                                            flex w-full
                                            items-center justify-center
                                            rounded-lg
                                            border border-zinc-200
                                            px-4 py-2.5
                                            text-sm font-semibold
                                            text-zinc-500

                                            dark:border-zinc-800
                                            dark:text-zinc-400
                                        ">
                                Plano Grátis
                            </div>

                        @endif

                    </div>

                </article>


                {{-- PLANO PRO --}}

                <article class="
                            relative
                            overflow-hidden
                            rounded-2xl
                            border border-violet-300
                            bg-white
                            p-5
                            shadow-sm
                            ring-1 ring-violet-200

                            dark:border-violet-800
                            dark:bg-zinc-900
                            dark:ring-violet-900/60
                        ">

                    <div class="
                                absolute
                                right-0 top-0
                                rounded-bl-xl
                                bg-violet-600
                                px-3 py-1.5
                                text-[10px] font-bold
                                text-white

                                dark:bg-violet-500
                                dark:text-zinc-950
                            ">
                        MAIS COMPLETO
                    </div>

                    <p class="
                                text-sm font-semibold
                                text-violet-700
                                dark:text-violet-300
                            ">
                        Pro
                    </p>

                    <div class="mt-2 flex items-end gap-1">

                        <span class="
                                    text-sm font-semibold
                                    text-zinc-500
                                    dark:text-zinc-400
                                ">
                            R$
                        </span>

                        <span class="
                                    text-3xl font-bold
                                    tracking-tight
                                    text-zinc-950
                                    dark:text-white
                                ">
                            {{ $this->proPriceLabel() }}
                        </span>

                        <span class="
                                    pb-1
                                    text-sm
                                    text-zinc-500
                                    dark:text-zinc-400
                                ">
                            /mês
                        </span>

                    </div>

                    <p class="
                                mt-3
                                text-sm leading-6
                                text-zinc-500
                                dark:text-zinc-400
                            ">
                        Para quem quer acompanhar cada proposta
                        e vender com mais organização.
                    </p>

                    <div class="
                                my-5
                                h-px
                                bg-violet-100
                                dark:bg-violet-900/50
                            "></div>

                    <div class="space-y-3 text-sm">

                        @foreach ([
                                'Propostas ilimitadas',
                                'Tudo do plano Grátis',
                                'Versionamento de propostas',
                                'Follow-up inteligente',
                                'Central de notificações',
                                'Logo e identidade da empresa',
                            ] as $feature)

                            <div class="
                                            flex items-start gap-2.5
                                            text-zinc-700
                                            dark:text-zinc-200
                                        ">

                                <span class="
                                                mt-0.5
                                                flex size-4 shrink-0
                                                items-center justify-center
                                                rounded-full
                                                bg-violet-100
                                                text-violet-700

                                                dark:bg-violet-950
                                                dark:text-violet-300
                                            ">
                                    <flux:icon.check class="size-3" />
                                </span>

                                <span>{{ $feature }}</span>

                            </div>

                        @endforeach

                    </div>

                    <div class="mt-6">

                        @if ($this->isPro)

                            <div class="
                                            flex w-full
                                            items-center justify-center
                                            rounded-lg
                                            bg-emerald-50
                                            px-4 py-2.5
                                            text-sm font-semibold
                                            text-emerald-700

                                            dark:bg-emerald-950/50
                                            dark:text-emerald-300
                                        ">
                                Seu plano atual
                            </div>

                        @else

                            <form method="POST" action="{{ route('settings.subscription.checkout.asaas') }}">
                                @csrf

                                <button type="submit" class="
                                                inline-flex w-full
                                                items-center justify-center
                                                gap-2
                                                rounded-lg
                                                bg-violet-600
                                                px-4 py-2.5
                                                text-sm font-semibold
                                                text-white
                                                shadow-sm
                                                transition
                                                hover:bg-violet-700

                                                dark:bg-violet-500
                                                dark:text-zinc-950
                                                dark:hover:bg-violet-400
                                            ">
                                    Assinar Fechou Pro
                                </button>
                            </form>

                        @endif

                    </div>

                </article>

            </div>

        </section>


        {{-- ===================================================== --}}
        {{-- RECURSOS DO PLANO ATUAL --}}
        {{-- ===================================================== --}}

        <section class="
                    overflow-hidden
                    rounded-2xl
                    border border-zinc-200
                    bg-white
                    shadow-sm

                    dark:border-zinc-800
                    dark:bg-zinc-900
                ">

            <div class="
                        border-b border-zinc-200
                        px-6 py-5

                        dark:border-zinc-800
                    ">

                <h2 class="
                            font-semibold
                            text-zinc-950
                            dark:text-white
                        ">
                    Recursos disponíveis agora
                </h2>

                <p class="
                            mt-1
                            text-sm
                            text-zinc-500
                            dark:text-zinc-400
                        ">
                    @if ($this->isAccessSuspended)
                        Seu plano contratado continua sendo o Pro,
                        mas temporariamente estão liberados apenas
                        os recursos do plano Grátis.
                    @elseif ($this->isInGracePeriod)
                        O Pro continua totalmente liberado durante
                        o período de tolerância.
                    @else
                        Funcionalidades atualmente liberadas para sua conta.
                    @endif
                </p>

            </div>


            <div class="grid sm:grid-cols-2 lg:grid-cols-3">

                @forelse (
                        $this->accessPlan?->features ?? []
                        as $feature
                    )

                    <div class="
                                    flex items-center
                                    gap-3
                                    border-b border-zinc-100
                                    px-6 py-4

                                    sm:border-r

                                    dark:border-zinc-800
                                ">

                        <div class="
                                        flex size-8 shrink-0
                                        items-center justify-center
                                        rounded-full
                                        bg-emerald-50
                                        text-emerald-600

                                        dark:bg-emerald-500/10
                                        dark:text-emerald-400
                                    ">
                            <flux:icon.check class="size-4" />
                        </div>

                        <span class="
                                        text-sm font-medium
                                        text-zinc-700
                                        dark:text-zinc-200
                                    ">
                            {{ $this->featureLabel($feature) }}
                        </span>

                    </div>

                @empty

                    <div class="
                                    col-span-full
                                    px-6 py-8
                                    text-center
                                    text-sm
                                    text-zinc-500

                                    dark:text-zinc-400
                                ">
                        Nenhum recurso cadastrado para este plano.
                    </div>

                @endforelse

            </div>

        </section>


        {{-- ===================================================== --}}
        {{-- OBSERVAÇÃO --}}
        {{-- ===================================================== --}}

        <div class="
                    flex items-start gap-3
                    rounded-2xl
                    border border-zinc-200
                    bg-zinc-50
                    px-5 py-4

                    dark:border-zinc-800
                    dark:bg-zinc-900/50
                ">

            <div class="mt-0.5 text-zinc-400">
                <flux:icon.information-circle class="size-5" />
            </div>

            <p class="
                        text-sm leading-6
                        text-zinc-500
                        dark:text-zinc-400
                    ">
                O uso de propostas é contabilizado por ciclo.
                A criação do checkout não libera o Pro automaticamente:
                a ativação será feita após a confirmação financeira
                recebida do Asaas. Em caso de atraso, o Pro permanece
                disponível durante o período de tolerância; os dados do
                plano não são apagados se o acesso for suspenso.
            </p>

        </div>

    @endif

</div>