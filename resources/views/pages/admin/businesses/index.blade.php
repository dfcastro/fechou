<?php

use App\Models\Business;
use App\Services\SubscriptionService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Empresas | Negozia')]
class extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function businesses()
    {
        return Business::query()
            ->with([
                'user',
                'currentSubscription.plan',
            ])
            ->withCount([
                'clients',
                'quotes',
            ])
            ->when(
                trim($this->search) !== '',
                function (Builder $query): void {
                    $search = trim($this->search);

                    $query->where(
                        function (Builder $query) use ($search): void {
                            $query
                                ->where(
                                    'name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'email',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'document',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhereHas(
                                    'user',
                                    function (Builder $query) use ($search): void {
                                        $query
                                            ->where(
                                                'name',
                                                'like',
                                                "%{$search}%"
                                            )
                                            ->orWhere(
                                                'email',
                                                'like',
                                                "%{$search}%"
                                            );
                                    }
                                );
                        }
                    );
                }
            )
            ->orderByDesc('created_at')
            ->paginate(15);
    }

    #[Computed]
    public function totalBusinesses(): int
    {
        return Business::query()->count();
    }

    #[Computed]
    public function activeBusinesses(): int
    {
        return Business::query()
            ->whereHas(
                'user',
                fn (Builder $query) =>
                    $query->where(
                        'last_seen_at',
                        '>=',
                        now()->subMinutes(15)
                    )
            )
            ->count();
    }

    #[Computed]
    public function activeToday(): int
    {
        return Business::query()
            ->whereHas(
                'user',
                fn (Builder $query) =>
                    $query->where(
                        'last_seen_at',
                        '>=',
                        now()->startOfDay()
                    )
            )
            ->count();
    }

    public function accessInfo(
        Business $business
    ): array {
        $service = app(
            SubscriptionService::class
        );

        return [
            'plan' => $service->accessPlan(
                $business
            ),

            'grant' => $service->activeAccessGrant(
                $business
            ),

            'subscription' =>
                $business->currentSubscription,
        ];
    }
};
?>

<div class="mx-auto w-full max-w-7xl space-y-6">

    {{-- Cabeçalho --}}
    <div
        class="
            flex flex-col gap-4
            lg:flex-row
            lg:items-end
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
                Administração
            </p>

            <h1
                class="
                    mt-1 text-2xl font-semibold
                    tracking-tight
                    text-zinc-950
                    dark:text-white
                "
            >
                Empresas
            </h1>

            <p
                class="
                    mt-1 max-w-2xl
                    text-sm
                    text-zinc-600
                    dark:text-zinc-400
                "
            >
                Acompanhe os cadastros, planos,
                utilização e atividade das empresas
                no Negozia.
            </p>
        </div>

        <a
            href="{{ route('admin.metrics') }}"
            wire:navigate
            class="
                inline-flex items-center
                justify-center rounded-lg
                border border-zinc-300
                bg-white px-4 py-2
                text-sm font-medium
                text-zinc-700
                shadow-sm transition
                hover:bg-zinc-50
                dark:border-zinc-700
                dark:bg-zinc-900
                dark:text-zinc-200
                dark:hover:bg-zinc-800
            "
        >
            Visão geral
        </a>
    </div>


    {{-- Resumo --}}
    <div class="grid gap-4 sm:grid-cols-3">

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
            <p
                class="
                    text-sm font-medium
                    text-zinc-500
                    dark:text-zinc-400
                "
            >
                Empresas cadastradas
            </p>

            <p
                class="
                    mt-2 text-3xl
                    font-semibold tracking-tight
                    text-zinc-950
                    dark:text-white
                "
            >
                {{ $this->totalBusinesses }}
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
            <div class="flex items-center gap-2">
                <span
                    class="
                        h-2.5 w-2.5
                        rounded-full
                        bg-emerald-500
                    "
                ></span>

                <p
                    class="
                        text-sm font-medium
                        text-zinc-500
                        dark:text-zinc-400
                    "
                >
                    Ativas agora
                </p>
            </div>

            <p
                class="
                    mt-2 text-3xl
                    font-semibold tracking-tight
                    text-zinc-950
                    dark:text-white
                "
            >
                {{ $this->activeBusinesses }}
            </p>

            <p class="mt-1 text-xs text-zinc-500">
                Atividade nos últimos 15 minutos
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
            <p
                class="
                    text-sm font-medium
                    text-zinc-500
                    dark:text-zinc-400
                "
            >
                Usaram hoje
            </p>

            <p
                class="
                    mt-2 text-3xl
                    font-semibold tracking-tight
                    text-zinc-950
                    dark:text-white
                "
            >
                {{ $this->activeToday }}
            </p>

            <p class="mt-1 text-xs text-zinc-500">
                Empresas com atividade hoje
            </p>
        </div>
    </div>


    {{-- Pesquisa --}}
    <div
        class="
            rounded-xl
            border border-zinc-200
            bg-white p-4
            shadow-sm
            dark:border-zinc-800
            dark:bg-zinc-900
        "
    >
        <div class="relative">
            <input
                type="search"
                wire:model.live.debounce.400ms="search"
                placeholder="Buscar por empresa, responsável, e-mail ou CNPJ..."
                class="
                    w-full rounded-lg
                    border border-zinc-300
                    bg-white
                    px-4 py-2.5
                    text-sm
                    text-zinc-900
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
            >
        </div>
    </div>


    {{-- Tabela --}}
    <div
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
        <div class="overflow-x-auto">
            <table
                class="
                    min-w-full
                    divide-y divide-zinc-200
                    dark:divide-zinc-800
                "
            >
                <thead
                    class="
                        bg-zinc-50
                        dark:bg-zinc-950/60
                    "
                >
                    <tr
                        class="
                            text-left
                            text-xs font-semibold
                            uppercase tracking-wide
                            text-zinc-500
                        "
                    >
                        <th class="px-5 py-3">
                            Empresa
                        </th>

                        <th class="px-5 py-3">
                            Acesso atual
                        </th>

                        <th class="px-5 py-3">
                            Utilização
                        </th>

                        <th class="px-5 py-3">
                            Última atividade
                        </th>

                        <th class="px-5 py-3">
                        </th>
                    </tr>
                </thead>

                <tbody
                    class="
                        divide-y divide-zinc-100
                        dark:divide-zinc-800
                    "
                >
                    @forelse (
                        $this->businesses
                        as $business
                    )

                        @php
                            $access =
                                $this->accessInfo(
                                    $business
                                );

                            $plan =
                                $access['plan'];

                            $grant =
                                $access['grant'];

                            $subscription =
                                $access['subscription'];
                        @endphp

                        <tr
                            wire:key="business-{{ $business->id }}"
                            class="
                                transition
                                hover:bg-zinc-50/80
                                dark:hover:bg-zinc-800/40
                            "
                        >

                            {{-- Empresa --}}
                            <td class="px-5 py-4">
                                <div
                                    class="
                                        font-medium
                                        text-zinc-950
                                        dark:text-white
                                    "
                                >
                                    {{ $business->name }}
                                </div>

                                <div
                                    class="
                                        mt-1
                                        text-xs
                                        text-zinc-500
                                    "
                                >
                                    {{ $business->user?->name
                                        ?? 'Sem responsável' }}
                                </div>

                                @if ($business->user?->email)
                                    <div
                                        class="
                                            mt-0.5
                                            text-xs
                                            text-zinc-400
                                        "
                                    >
                                        {{ $business->user->email }}
                                    </div>
                                @endif
                            </td>


                            {{-- Acesso --}}
                            <td class="px-5 py-4">
                                <div
                                    class="
                                        flex flex-col
                                        items-start gap-1.5
                                    "
                                >
                                    <span
                                        class="
                                            inline-flex
                                            rounded-full
                                            bg-zinc-100
                                            px-2.5 py-1
                                            text-xs
                                            font-semibold
                                            text-zinc-700
                                            dark:bg-zinc-800
                                            dark:text-zinc-200
                                        "
                                    >
                                        {{ $plan?->name
                                            ?? 'Sem acesso' }}
                                    </span>

                                    @if ($grant)
                                        <span
                                            class="
                                                text-xs
                                                font-medium
                                                text-emerald-600
                                                dark:text-emerald-400
                                            "
                                        >
                                            Cortesia até
                                            {{ $grant
                                                ->ends_at
                                                ->format('d/m/Y') }}
                                        </span>
                                    @elseif (
                                        $subscription?->plan
                                    )
                                        <span
                                            class="
                                                text-xs
                                                text-zinc-500
                                            "
                                        >
                                            Contratado:
                                            {{ $subscription
                                                ->plan
                                                ->name }}
                                        </span>
                                    @endif
                                </div>
                            </td>


                            {{-- Uso --}}
                            <td class="px-5 py-4">
                                <div
                                    class="
                                        text-sm
                                        text-zinc-700
                                        dark:text-zinc-300
                                    "
                                >
                                    {{ $business->quotes_count }}
                                    propostas
                                </div>

                                <div
                                    class="
                                        mt-1
                                        text-xs
                                        text-zinc-500
                                    "
                                >
                                    {{ $business->clients_count }}
                                    clientes
                                </div>
                            </td>


                            {{-- Atividade --}}
                            <td class="px-5 py-4">
                                @if (
                                    $business
                                        ->user
                                        ?->last_seen_at
                                )

                                    <div
                                        class="
                                            text-sm
                                            text-zinc-700
                                            dark:text-zinc-300
                                        "
                                    >
                                        {{ $business
                                            ->user
                                            ->last_seen_at
                                            ->diffForHumans() }}
                                    </div>

                                    @if (
                                        $business
                                            ->user
                                            ->last_seen_at
                                            ->gte(
                                                now()
                                                    ->subMinutes(15)
                                            )
                                    )
                                        <div
                                            class="
                                                mt-1.5
                                                flex items-center
                                                gap-1.5
                                                text-xs
                                                font-medium
                                                text-emerald-600
                                                dark:text-emerald-400
                                            "
                                        >
                                            <span
                                                class="
                                                    h-2 w-2
                                                    rounded-full
                                                    bg-emerald-500
                                                "
                                            ></span>

                                            Ativo agora
                                        </div>
                                    @endif

                                @else

                                    <span
                                        class="
                                            text-sm
                                            text-zinc-400
                                        "
                                    >
                                        Sem registro
                                    </span>

                                @endif
                            </td>


                            {{-- Ação --}}
                            <td
                                class="
                                    whitespace-nowrap
                                    px-5 py-4
                                    text-right
                                "
                            >
                                <a
                                    href="{{ route(
                                        'admin.businesses.show',
                                        $business
                                    ) }}"
                                    wire:navigate
                                    class="
                                        text-sm
                                        font-semibold
                                        text-zinc-700
                                        transition
                                        hover:text-zinc-950
                                        dark:text-zinc-300
                                        dark:hover:text-white
                                    "
                                >
                                    Gerenciar →
                                </a>
                            </td>
                        </tr>

                    @empty

                        <tr>
                            <td
                                colspan="5"
                                class="
                                    px-5 py-14
                                    text-center
                                "
                            >
                                <p
                                    class="
                                        font-medium
                                        text-zinc-700
                                        dark:text-zinc-300
                                    "
                                >
                                    Nenhuma empresa encontrada
                                </p>

                                <p
                                    class="
                                        mt-1 text-sm
                                        text-zinc-500
                                    "
                                >
                                    Tente pesquisar por outro termo.
                                </p>
                            </td>
                        </tr>

                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->businesses->hasPages())
            <div
                class="
                    border-t
                    border-zinc-200
                    px-5 py-4
                    dark:border-zinc-800
                "
            >
                {{ $this->businesses->links() }}
            </div>
        @endif
    </div>
</div>