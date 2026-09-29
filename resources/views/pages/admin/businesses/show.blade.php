<?php

use App\Models\AdminAuditLog;
use App\Models\Business;
use App\Models\BusinessAccessGrant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\AdminAuditService;
use App\Services\SubscriptionService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Gerenciar empresa | Negozia')]
class extends Component
{
    public Business $business;

    public string $reason = '';

    public ?string $customEndsAt = null;

    public ?int $selectedDays = null;

    public ?string $successMessage = null;


    public function mount(
        Business $business
    ): void {
        $this->business = $business;
    }


    /*
    |--------------------------------------------------------------------------
    | Dados
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function subscription(): ?Subscription
    {
        return $this->business
            ->currentSubscription()
            ->with('plan')
            ->first();
    }


    #[Computed]
    public function accessPlan(): ?Plan
    {
        return app(
            SubscriptionService::class
        )->accessPlan(
            $this->business
        );
    }


    #[Computed]
    public function activeGrant(): ?BusinessAccessGrant
    {
        $grant = app(
            SubscriptionService::class
        )->activeAccessGrant(
            $this->business
        );

        $grant?->loadMissing([
            'plan',
            'grantedBy',
        ]);

        return $grant;
    }


    #[Computed]
    public function quotesCount(): int
    {
        return $this->business
            ->quotes()
            ->withTrashed()
            ->count();
    }


    #[Computed]
    public function clientsCount(): int
    {
        return $this->business
            ->clients()
            ->count();
    }


    /*
    |--------------------------------------------------------------------------
    | Cortesia
    |--------------------------------------------------------------------------
    */

    public function selectDays(
        int $days
    ): void {
        if (!in_array(
            $days,
            [7, 15, 30],
            true
        )) {
            return;
        }

        $this->selectedDays = $days;

        /*
         * Ao escolher um atalho,
         * a data personalizada deixa
         * de ser a opção selecionada.
         */
        $this->customEndsAt = null;

        $this->resetValidation();
        $this->successMessage = null;
    }


    public function updatedCustomEndsAt(
        ?string $value
    ): void {
        if ($value) {
            $this->selectedDays = null;
        }

        $this->resetValidation();
        $this->successMessage = null;
    }


    public function recentAuditLogs()
    {
        return AdminAuditLog::query()
            ->with('admin')
            ->where(
                'business_id',
                $this->business->id
            )
            ->latest()
            ->limit(8)
            ->get();
    }


    public function auditActionLabel(
        string $action
    ): string {
        return match ($action) {
            'courtesy.granted' =>
                'Concedeu cortesia Pro',

            'courtesy.extended' =>
                'Prorrogou cortesia Pro',

            'courtesy.revoked' =>
                'Encerrou cortesia',

            default =>
                $action,
        };
    }


    public function auditDetail(
        AdminAuditLog $log
    ): ?string {
        $metadata =
            $log->metadata ?? [];

        $parts = [];


        if (
            in_array(
                $log->action,
                [
                    'courtesy.granted',
                    'courtesy.extended',
                ],
                true
            )
            && !empty(
                $metadata['ends_at']
            )
        ) {
            try {
                $date = \Illuminate\Support\Carbon::parse(
                    $metadata['ends_at']
                )->format('d/m/Y H:i');

                $parts[] =
                    $log->action
                        === 'courtesy.extended'
                        ? 'Nova validade: ' . $date
                        : 'Válido até: ' . $date;

            } catch (\Throwable) {
                // Mantém o histórico utilizável
                // mesmo se um registro legado
                // possuir data em formato inesperado.
            }
        }


        if (
            $log->action
            === 'courtesy.revoked'
        ) {
            $parts[] =
                'Cortesia encerrada';
        }


        if (
            !empty(
                $metadata['reason']
            )
        ) {
            $parts[] =
                'Motivo: '
                . $metadata['reason'];
        }


        return $parts !== []
            ? implode(
                ' • ',
                $parts
            )
            : null;
    }


    public function grantAccess(): void
    {
        $this->ensureAdmin();

        $this->validate([
            'reason' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        $plan = $this->proPlan();


        /*
         * Período rápido: 7, 15 ou 30 dias.
         */
        if (
            $this->selectedDays !== null
            && in_array(
                $this->selectedDays,
                [7, 15, 30],
                true
            )
        ) {
            $days = $this->selectedDays;

            $grant = $this->activeGrant;

            /*
             * Se já existir cortesia Pro ativa,
             * soma os novos dias ao término atual.
             */
            $base = (
                $grant
                && $grant->plan_id === $plan->id
                && $grant->ends_at->isFuture()
            )
                ? $grant->ends_at->copy()
                : now();

            $endsAt = $base
                ->addDays($days)
                ->endOfDay();

            $this->persistGrant(
                $plan,
                $endsAt
            );

            $this->successMessage =
                "{$days} dias de acesso Pro adicionados.";

            $this->selectedDays = null;
            $this->customEndsAt = null;
            $this->reason = '';

            $this->resetValidation();

            return;
        }


        /*
         * Data personalizada.
         */
        if ($this->customEndsAt) {
            $validated = $this->validate([
                'customEndsAt' => [
                    'required',
                    'date',
                    'after:today',
                ],

                'reason' => [
                    'nullable',
                    'string',
                    'max:500',
                ],
            ], [
                'customEndsAt.required' =>
                    'Informe a data final.',

                'customEndsAt.date' =>
                    'Informe uma data válida.',

                'customEndsAt.after' =>
                    'A data precisa ser posterior a hoje.',
            ]);

            $endsAt = Carbon::parse(
                $validated['customEndsAt']
            )->endOfDay();

            $this->persistGrant(
                $plan,
                $endsAt
            );

            $this->successMessage =
                'Acesso Pro concedido até '
                . $endsAt->format('d/m/Y')
                . '.';

            $this->selectedDays = null;
            $this->customEndsAt = null;
            $this->reason = '';

            $this->resetValidation();

            return;
        }


        /*
         * Nenhum período selecionado.
         */
        $this->addError(
            'accessPeriod',
            'Escolha 7, 15 ou 30 dias, ou informe uma data final.'
        );
    }


    public function revokeGrant(): void
    {
        $this->ensureAdmin();

        $grant = $this->activeGrant;

        if (!$grant) {
            return;
        }

        $grant->update([
            'revoked_at' => now(),
            'revoked_by' => auth()->id(),
        ]);

        app(AdminAuditService::class)
            ->record(
                'courtesy.revoked',
                $this->business,
                $grant,
                [
                    'plan_id' =>
                        $grant->plan_id,

                    'ends_at' =>
                        $grant->ends_at
                            ?->toIso8601String(),

                    'reason' =>
                        $grant->reason,
                ]
            );

        $this->successMessage =
            'Cortesia encerrada com sucesso.';

        $this->refreshAdminData();
    }


    private function persistGrant(
        Plan $plan,
        \Carbon\CarbonInterface $endsAt
    ): void {
        $grant = $this->activeGrant;

        /*
         * Se já há cortesia do mesmo plano,
         * apenas atualizamos a validade.
         */
        if (
            $grant
            && $grant->plan_id === $plan->id
        ) {
            $previousEndsAt =
                $grant->ends_at?->copy();

            $grant->update([
                'ends_at' => $endsAt,

                'reason' =>
                    trim($this->reason) !== ''
                        ? trim($this->reason)
                        : $grant->reason,
            ]);

            app(AdminAuditService::class)
                ->record(
                    'courtesy.extended',
                    $this->business,
                    $grant,
                    [
                        'plan_id' =>
                            $plan->id,

                        'previous_ends_at' =>
                            $previousEndsAt
                                ?->toIso8601String(),

                        'ends_at' =>
                            $endsAt
                                ->toIso8601String(),

                        'reason' =>
                            $grant->reason,
                    ]
                );

            $this->refreshAdminData();

            return;
        }


        /*
         * Se existia benefício de outro plano,
         * encerramos antes de criar o novo.
         */
        if ($grant) {
            $grant->update([
                'revoked_at' => now(),
                'revoked_by' => auth()->id(),
            ]);
        }


        $newGrant = BusinessAccessGrant::create([
            'business_id' =>
                $this->business->id,

            'plan_id' =>
                $plan->id,

            'type' =>
                'courtesy',

            'starts_at' =>
                now(),

            'ends_at' =>
                $endsAt,

            'reason' =>
                trim($this->reason) !== ''
                    ? trim($this->reason)
                    : null,

            'granted_by' =>
                auth()->id(),
        ]);

        app(AdminAuditService::class)
            ->record(
                'courtesy.granted',
                $this->business,
                $newGrant,
                [
                    'plan_id' =>
                        $plan->id,

                    'ends_at' =>
                        $endsAt
                            ->toIso8601String(),

                    'reason' =>
                        $newGrant->reason,
                ]
            );

        $this->refreshAdminData();
    }


    private function proPlan(): Plan
    {
        return Plan::query()
            ->where('slug', 'pro')
            ->where('is_active', true)
            ->firstOrFail();
    }


    private function ensureAdmin(): void
    {
        abort_unless(
            auth()->user()?->is_admin === true,
            403
        );
    }


    private function refreshAdminData(): void
    {
        unset(
            $this->activeGrant,
            $this->accessPlan,
            $this->subscription,
            $this->quotesCount,
            $this->clientsCount,
        );

        $this->business->refresh();
    }
};
?>

<div class="mx-auto w-full max-w-7xl space-y-6">

    {{-- Voltar --}}
    <div>
        <a
            href="{{ route(
                'admin.businesses.index'
            ) }}"
            wire:navigate
            class="
                inline-flex items-center
                gap-1 text-sm font-medium
                text-zinc-500
                transition
                hover:text-zinc-900
                dark:text-zinc-400
                dark:hover:text-white
            "
        >
            ← Empresas
        </a>
    </div>


    {{-- Cabeçalho --}}
    <div
        class="
            flex flex-col gap-4
            lg:flex-row
            lg:items-start
            lg:justify-between
        "
    >
        <div>
            <p
                class="
                    text-sm font-medium
                    text-zinc-500
                    dark:text-zinc-400
                "
            >
                Administração da empresa
            </p>

            <h1
                class="
                    mt-1 text-2xl
                    font-semibold tracking-tight
                    text-zinc-950
                    dark:text-white
                "
            >
                {{ $business->name }}
            </h1>

            <div
                class="
                    mt-2 flex flex-wrap
                    gap-x-4 gap-y-1
                    text-sm text-zinc-500
                "
            >
                @if ($business->email)
                    <span>
                        {{ $business->email }}
                    </span>
                @endif

                @if ($business->document)
                    <span>
                        {{ $business->document }}
                    </span>
                @endif
            </div>
        </div>


        @if ($this->accessPlan)
            <div
                class="
                    rounded-xl
                    border border-zinc-200
                    bg-white px-4 py-3
                    shadow-sm
                    dark:border-zinc-800
                    dark:bg-zinc-900
                "
            >
                <p
                    class="
                        text-xs font-medium
                        uppercase tracking-wide
                        text-zinc-500
                    "
                >
                    Acesso efetivo
                </p>

                <div
                    class="
                        mt-1 flex
                        items-center gap-2
                    "
                >
                    <span
                        class="
                            text-lg font-semibold
                            text-zinc-950
                            dark:text-white
                        "
                    >
                        {{ $this->accessPlan->name }}
                    </span>

                    @if ($this->activeGrant)
                        <span
                            class="
                                rounded-full
                                bg-emerald-100
                                px-2 py-0.5
                                text-xs font-semibold
                                text-emerald-700
                                dark:bg-emerald-950/50
                                dark:text-emerald-300
                            "
                        >
                            Cortesia
                        </span>
                    @endif
                </div>
            </div>
        @endif
    </div>


    {{-- Sucesso --}}
    @if ($successMessage)
        <div
            class="
                rounded-xl
                border border-emerald-200
                bg-emerald-50
                px-4 py-3
                text-sm
                text-emerald-800
                dark:border-emerald-900
                dark:bg-emerald-950/40
                dark:text-emerald-300
            "
        >
            {{ $successMessage }}
        </div>
    @endif


    {{-- Indicadores --}}
    <div
        class="
            grid gap-4
            sm:grid-cols-2
            lg:grid-cols-4
        "
    >

        <div
            class="
                rounded-xl
                border border-zinc-200
                bg-white p-5
                shadow-sm
                dark:border-zinc-800
                dark:bg-zinc-900
            "
        >
            <p class="text-sm text-zinc-500">
                Propostas
            </p>

            <p
                class="
                    mt-2 text-2xl
                    font-semibold
                    text-zinc-950
                    dark:text-white
                "
            >
                {{ $this->quotesCount }}
            </p>
        </div>


        <div
            class="
                rounded-xl
                border border-zinc-200
                bg-white p-5
                shadow-sm
                dark:border-zinc-800
                dark:bg-zinc-900
            "
        >
            <p class="text-sm text-zinc-500">
                Clientes
            </p>

            <p
                class="
                    mt-2 text-2xl
                    font-semibold
                    text-zinc-950
                    dark:text-white
                "
            >
                {{ $this->clientsCount }}
            </p>
        </div>


        <div
            class="
                rounded-xl
                border border-zinc-200
                bg-white p-5
                shadow-sm
                dark:border-zinc-800
                dark:bg-zinc-900
            "
        >
            <p class="text-sm text-zinc-500">
                Responsável
            </p>

            <p
                class="
                    mt-2
                    font-semibold
                    text-zinc-950
                    dark:text-white
                "
            >
                {{ $business->user?->name
                    ?? 'Não informado' }}
            </p>

            @if ($business->user?->email)
                <p
                    class="
                        mt-1 truncate
                        text-xs text-zinc-500
                    "
                >
                    {{ $business->user->email }}
                </p>
            @endif
        </div>


        <div
            class="
                rounded-xl
                border border-zinc-200
                bg-white p-5
                shadow-sm
                dark:border-zinc-800
                dark:bg-zinc-900
            "
        >
            <p class="text-sm text-zinc-500">
                Última atividade
            </p>

            @if ($business->user?->last_seen_at)

                <p
                    class="
                        mt-2 font-semibold
                        text-zinc-950
                        dark:text-white
                    "
                >
                    {{ $business
                        ->user
                        ->last_seen_at
                        ->diffForHumans() }}
                </p>

                <p
                    class="
                        mt-1 text-xs
                        text-zinc-500
                    "
                >
                    {{ $business
                        ->user
                        ->last_seen_at
                        ->format('d/m/Y H:i') }}
                </p>

            @else

                <p
                    class="
                        mt-2 text-sm
                        text-zinc-400
                    "
                >
                    Ainda não registrado
                </p>

            @endif
        </div>
    </div>


    <div
        class="
            grid gap-6
            lg:grid-cols-[minmax(0,1fr)_minmax(360px,0.8fr)]
        "
    >

        {{-- Assinatura --}}
        <div class="space-y-6">

            <div
                class="
                    rounded-xl
                    border border-zinc-200
                    bg-white p-6
                    shadow-sm
                    dark:border-zinc-800
                    dark:bg-zinc-900
                "
            >
                <div>
                    <h2
                        class="
                            text-base font-semibold
                            text-zinc-950
                            dark:text-white
                        "
                    >
                        Plano contratado
                    </h2>

                    <p
                        class="
                            mt-1 text-sm
                            text-zinc-500
                        "
                    >
                        Assinatura financeira registrada
                        para esta empresa.
                    </p>
                </div>

                @if ($this->subscription)

                    <div
                        class="
                            mt-5
                            divide-y divide-zinc-100
                            dark:divide-zinc-800
                        "
                    >
                        <div
                            class="
                                flex items-center
                                justify-between
                                gap-4 py-3
                            "
                        >
                            <span
                                class="
                                    text-sm
                                    text-zinc-500
                                "
                            >
                                Plano
                            </span>

                            <span
                                class="
                                    font-semibold
                                    text-zinc-950
                                    dark:text-white
                                "
                            >
                                {{ $this
                                    ->subscription
                                    ->plan
                                    ?->name
                                    ?? '—' }}
                            </span>
                        </div>


                        <div
                            class="
                                flex items-center
                                justify-between
                                gap-4 py-3
                            "
                        >
                            <span
                                class="
                                    text-sm
                                    text-zinc-500
                                "
                            >
                                Status
                            </span>

                            <span
                                class="
                                    text-sm font-medium
                                    text-zinc-700
                                    dark:text-zinc-300
                                "
                            >
                                @switch(
                                    $this->subscription->status
                                )
                                    @case('active')
                                        Ativa
                                        @break

                                    @case('trialing')
                                        Período de teste
                                        @break

                                    @case('past_due')
                                        Pagamento pendente
                                        @break

                                    @default
                                        {{ $this
                                            ->subscription
                                            ->status }}
                                @endswitch
                            </span>
                        </div>


                        <div
                            class="
                                flex items-center
                                justify-between
                                gap-4 py-3
                            "
                        >
                            <span
                                class="
                                    text-sm
                                    text-zinc-500
                                "
                            >
                                Cobrança
                            </span>

                            <span
                                class="
                                    text-sm font-medium
                                    text-zinc-700
                                    dark:text-zinc-300
                                "
                            >
                                {{ $this
                                    ->subscription
                                    ->payment_provider
                                    === 'asaas'
                                        ? 'Asaas'
                                        : 'Interna' }}
                            </span>
                        </div>
                    </div>

                @else

                    <div
                        class="
                            mt-5 rounded-lg
                            bg-zinc-50
                            px-4 py-4
                            text-sm
                            text-zinc-500
                            dark:bg-zinc-950
                        "
                    >
                        Esta empresa não possui uma
                        assinatura registrada.
                    </div>

                @endif
            </div>


            {{-- Cortesia atual --}}
            <div
                class="
                    rounded-xl
                    border border-zinc-200
                    bg-white p-6
                    shadow-sm
                    dark:border-zinc-800
                    dark:bg-zinc-900
                "
            >
                <h2
                    class="
                        text-base font-semibold
                        text-zinc-950
                        dark:text-white
                    "
                >
                    Cortesia atual
                </h2>

                @if ($this->activeGrant)

                    <div
                        class="
                            mt-5 rounded-xl
                            border border-emerald-200
                            bg-emerald-50 p-5
                            dark:border-emerald-900
                            dark:bg-emerald-950/30
                        "
                    >
                        <div
                            class="
                                flex flex-col gap-3
                                sm:flex-row
                                sm:items-start
                                sm:justify-between
                            "
                        >
                            <div>
                                <div
                                    class="
                                        flex items-center
                                        gap-2
                                    "
                                >
                                    <span
                                        class="
                                            font-semibold
                                            text-emerald-900
                                            dark:text-emerald-200
                                        "
                                    >
                                        {{ $this
                                            ->activeGrant
                                            ->plan
                                            ->name }}
                                    </span>

                                    <span
                                        class="
                                            rounded-full
                                            bg-emerald-200/70
                                            px-2 py-0.5
                                            text-xs font-semibold
                                            text-emerald-800
                                            dark:bg-emerald-900
                                            dark:text-emerald-200
                                        "
                                    >
                                        ATIVA
                                    </span>
                                </div>

                                <p
                                    class="
                                        mt-2 text-sm
                                        text-emerald-800
                                        dark:text-emerald-300
                                    "
                                >
                                    Válida até
                                    <strong>
                                        {{ $this
                                            ->activeGrant
                                            ->ends_at
                                            ->format(
                                                'd/m/Y \à\s H:i'
                                            ) }}
                                    </strong>
                                </p>

                                @if (
                                    $this
                                        ->activeGrant
                                        ->grantedBy
                                )
                                    <p
                                        class="
                                            mt-1 text-xs
                                            text-emerald-700
                                            dark:text-emerald-400
                                        "
                                    >
                                        Concedida por
                                        {{ $this
                                            ->activeGrant
                                            ->grantedBy
                                            ->name }}
                                    </p>
                                @endif

                                @if (
                                    $this
                                        ->activeGrant
                                        ->reason
                                )
                                    <p
                                        class="
                                            mt-3 text-sm
                                            text-emerald-800
                                            dark:text-emerald-300
                                        "
                                    >
                                        {{ $this
                                            ->activeGrant
                                            ->reason }}
                                    </p>
                                @endif
                            </div>

                            <button
                                type="button"
                                wire:click="revokeGrant"
                                wire:confirm="Deseja realmente encerrar esta cortesia?"
                                class="
                                    text-sm font-semibold
                                    text-red-600
                                    transition
                                    hover:text-red-700
                                    dark:text-red-400
                                "
                            >
                                Encerrar cortesia
                            </button>
                        </div>
                    </div>

                @else

                    <div
                        class="
                            mt-5 rounded-lg
                            bg-zinc-50
                            px-4 py-4
                            text-sm text-zinc-500
                            dark:bg-zinc-950
                        "
                    >
                        Nenhuma cortesia administrativa
                        está ativa para esta empresa.
                    </div>

                @endif
            </div>
        </div>


        {{-- Conceder acesso --}}
        <div>
            <div
                class="
                    rounded-xl
                    border border-zinc-200
                    bg-white p-6
                    shadow-sm
                    dark:border-zinc-800
                    dark:bg-zinc-900
                "
            >
                <h2
                    class="
                        text-base font-semibold
                        text-zinc-950
                        dark:text-white
                    "
                >
                    Conceder acesso Pro
                </h2>

                <p
                    class="
                        mt-1 text-sm
                        text-zinc-500
                    "
                >
                    Libere temporariamente os
                    recursos do plano Pro sem alterar
                    a assinatura financeira.
                </p>


                {{-- Alerta importante --}}
                <div
                    class="
                        mt-4 rounded-lg
                        border border-amber-200
                        bg-amber-50
                        px-3 py-3
                        text-xs
                        leading-relaxed
                        text-amber-800
                        dark:border-amber-900
                        dark:bg-amber-950/30
                        dark:text-amber-300
                    "
                >
                    A cortesia libera os recursos no
                    Negozia, mas não cancela nem pausa
                    cobranças existentes no Asaas.
                </div>


                {{-- Motivo --}}
                <div class="mt-5">
                    <label
                        for="reason"
                        class="
                            text-sm font-medium
                            text-zinc-700
                            dark:text-zinc-300
                        "
                    >
                        Motivo
                        <span
                            class="
                                font-normal
                                text-zinc-400
                            "
                        >
                            (opcional)
                        </span>
                    </label>

                    <textarea
                        id="reason"
                        wire:model="reason"
                        rows="3"
                        placeholder="Ex.: teste comercial, parceria, compensação..."
                        class="
                            mt-2 w-full
                            rounded-lg border
                            border-zinc-300
                            bg-white px-3 py-2
                            text-sm text-zinc-900
                            outline-none
                            transition
                            focus:border-zinc-500
                            focus:ring-2
                            focus:ring-zinc-200
                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-white
                            dark:focus:ring-zinc-800
                        "
                    ></textarea>

                    @error('reason')
                        <p
                            class="
                                mt-1 text-xs
                                text-red-600
                            "
                        >
                            {{ $message }}
                        </p>
                    @enderror
                </div>


                {{-- Atalhos --}}
                <div class="mt-6">
                    <p
                        class="
                            text-sm font-medium
                            text-zinc-700
                            dark:text-zinc-300
                        "
                    >
                        Adicionar período
                    </p>

                    <div
                        class="
                            mt-2 grid
                            grid-cols-3 gap-2
                        "
                    >
                        <button
                            type="button"
                            wire:click="selectDays(7)"
                            class="
                                rounded-lg
                                border border-zinc-300
                                bg-white
                                px-3 py-2.5
                                text-sm font-semibold
                                text-zinc-700
                                transition
                                hover:border-zinc-400
                                hover:bg-zinc-50
                                dark:border-zinc-700
                                dark:bg-zinc-950
                                dark:text-zinc-200
                                dark:hover:bg-zinc-800
                            "
                        >
                            +7 dias
                        </button>

                        <button
                            type="button"
                            wire:click="selectDays(15)"
                            class="
                                rounded-lg
                                border border-zinc-300
                                bg-white
                                px-3 py-2.5
                                text-sm font-semibold
                                text-zinc-700
                                transition
                                hover:border-zinc-400
                                hover:bg-zinc-50
                                dark:border-zinc-700
                                dark:bg-zinc-950
                                dark:text-zinc-200
                                dark:hover:bg-zinc-800
                            "
                        >
                            +15 dias
                        </button>

                        <button
                            type="button"
                            wire:click="selectDays(30)"
                            class="
                                rounded-lg
                                border border-zinc-300
                                bg-white
                                px-3 py-2.5
                                text-sm font-semibold
                                text-zinc-700
                                transition
                                hover:border-zinc-400
                                hover:bg-zinc-50
                                dark:border-zinc-700
                                dark:bg-zinc-950
                                dark:text-zinc-200
                                dark:hover:bg-zinc-800
                            "
                        >
                            +30 dias
                        </button>
                    </div>

                    @if ($selectedDays)
                        <div
                            class="
                                mt-3 rounded-lg
                                border border-emerald-200
                                bg-emerald-50
                                px-3 py-2
                                text-sm font-medium
                                text-emerald-700
                                dark:border-emerald-900
                                dark:bg-emerald-950/30
                                dark:text-emerald-300
                            "
                        >
                            Período selecionado:
                            +{{ $selectedDays }} dias
                        </div>
                    @endif
                </div>


                {{-- Data personalizada --}}
                <div
                    class="
                        mt-6
                        border-t border-zinc-200
                        pt-5
                        dark:border-zinc-800
                    "
                >
                    <label
                        for="customEndsAt"
                        class="
                            text-sm font-medium
                            text-zinc-700
                            dark:text-zinc-300
                        "
                    >
                        Ou definir data final
                    </label>

                    <div
                        class="
                            mt-2 flex
                            flex-col gap-2
                            sm:flex-row
                        "
                    >
                        <input
                            id="customEndsAt"
                            type="date"
                            min="{{ now()
                                ->addDay()
                                ->format('Y-m-d') }}"
                            wire:model.live="customEndsAt"
                            class="
                                min-w-0 flex-1
                                rounded-lg border
                                border-zinc-300
                                bg-white px-3 py-2
                                text-sm text-zinc-900
                                dark:border-zinc-700
                                dark:bg-zinc-950
                                dark:text-white
                            "
                        >

                        <button
                            type="button"
                            wire:click="grantAccess"
                            class="
                                rounded-lg
                                bg-zinc-950
                                px-4 py-2
                                text-sm font-semibold
                                text-white
                                transition
                                hover:bg-zinc-800
                                dark:bg-white
                                dark:text-zinc-950
                                dark:hover:bg-zinc-200
                            "
                        >
                            Conceder
                        </button>
                    </div>

                    @error('customEndsAt')
                        <p
                            class="
                                mt-1 text-xs
                                text-red-600
                            "
                        >
                            {{ $message }}
                        </p>
                    @enderror

                    @error('accessPeriod')
                        <p
                            class="
                                mt-2 text-xs
                                font-medium
                                text-red-600
                            "
                        >
                            {{ $message }}
                        </p>
                    @enderror
                </div>
            </div>
        </div>
    </div>


    {{-- ================================================= --}}
    {{-- HISTÓRICO ADMINISTRATIVO --}}
    {{-- ================================================= --}}

    @php
        $recentAuditLogs =
            $this->recentAuditLogs();
    @endphp

    <section
        class="
            overflow-hidden
            rounded-xl
            border border-zinc-200
            bg-white
            shadow-sm
            dark:border-zinc-800
            dark:bg-zinc-900
        "
    >

        <div
            class="
                flex flex-col gap-3
                border-b border-zinc-200
                px-5 py-4
                sm:flex-row
                sm:items-center
                sm:justify-between
                dark:border-zinc-800
            "
        >
            <div>
                <h2
                    class="
                        text-sm font-semibold
                        text-zinc-950
                        dark:text-white
                    "
                >
                    Histórico administrativo
                </h2>

                <p
                    class="
                        mt-0.5 text-xs
                        text-zinc-500
                        dark:text-zinc-400
                    "
                >
                    Últimas ações administrativas
                    realizadas nesta empresa.
                </p>
            </div>


            <a
                href="{{ route(
                    'admin.audit',
                    [
                        'business' =>
                            $this->business->id,
                    ]
                ) }}"
                wire:navigate
                class="
                    whitespace-nowrap
                    text-xs font-semibold
                    text-zinc-600
                    transition
                    hover:text-zinc-950
                    dark:text-zinc-400
                    dark:hover:text-white
                "
            >
                Ver histórico completo →
            </a>
        </div>


        @if ($recentAuditLogs->isEmpty())

            <div
                class="
                    px-5 py-8
                    text-center
                "
            >
                <p
                    class="
                        text-sm font-medium
                        text-zinc-700
                        dark:text-zinc-300
                    "
                >
                    Nenhuma ação administrativa registrada.
                </p>

                <p
                    class="
                        mt-1 text-xs
                        text-zinc-500
                        dark:text-zinc-400
                    "
                >
                    Concessões, prorrogações e
                    encerramentos de cortesia
                    aparecerão aqui.
                </p>
            </div>

        @else

            <div
                class="
                    divide-y divide-zinc-100
                    dark:divide-zinc-800
                "
            >

                @foreach ($recentAuditLogs as $log)

                    <div
                        class="
                            flex flex-col gap-3
                            px-5 py-4
                            sm:flex-row
                            sm:items-start
                            sm:justify-between
                        "
                    >

                        <div
                            class="
                                min-w-0
                                flex-1
                            "
                        >

                            <div
                                class="
                                    flex flex-wrap
                                    items-center gap-2
                                "
                            >
                                <span
                                    class="
                                        text-sm font-semibold
                                        text-zinc-950
                                        dark:text-white
                                    "
                                >
                                    {{
                                        $this->auditActionLabel(
                                            $log->action
                                        )
                                    }}
                                </span>

                                <span
                                    class="
                                        text-[11px]
                                        text-zinc-400
                                        dark:text-zinc-500
                                    "
                                >
                                    {{
                                        $log
                                            ->created_at
                                            ->format(
                                                'd/m/Y H:i'
                                            )
                                    }}
                                </span>
                            </div>


                            @if (
                                $this->auditDetail(
                                    $log
                                )
                            )
                                <p
                                    class="
                                        mt-1 text-xs
                                        text-zinc-500
                                        dark:text-zinc-400
                                    "
                                >
                                    {{
                                        $this->auditDetail(
                                            $log
                                        )
                                    }}
                                </p>
                            @endif


                            <p
                                class="
                                    mt-1 text-[11px]
                                    text-zinc-400
                                    dark:text-zinc-500
                                "
                            >
                                Por
                                {{
                                    $log->admin?->name
                                    ?? 'Administrador removido'
                                }}
                            </p>

                        </div>


                        @if ($log->ip_address)
                            <span
                                class="
                                    shrink-0
                                    text-[11px]
                                    text-zinc-400
                                    dark:text-zinc-500
                                "
                            >
                                IP {{ $log->ip_address }}
                            </span>
                        @endif

                    </div>

                @endforeach

            </div>

        @endif

    </section>

</div>