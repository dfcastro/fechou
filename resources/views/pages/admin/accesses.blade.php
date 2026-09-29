<?php

use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Acessos | Negozia')]
class extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function users()
    {
        return User::query()
            ->whereHas('business')
            ->with([
                'business.currentSubscription.plan',
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
                                ->orWhereHas(
                                    'business',
                                    function (Builder $query) use ($search): void {
                                        $query
                                            ->where(
                                                'name',
                                                'like',
                                                "%{$search}%"
                                            )
                                            ->orWhere(
                                                'document',
                                                'like',
                                                "%{$search}%"
                                            );
                                    }
                                );
                        }
                    );
                }
            )
            ->orderByRaw(
                'last_seen_at IS NULL, last_seen_at DESC'
            )
            ->paginate(20);
    }

    #[Computed]
    public function totalUsers(): int
    {
        return User::query()
            ->whereHas('business')
            ->count();
    }

    #[Computed]
    public function activeNow(): int
    {
        return User::query()
            ->whereHas('business')
            ->where(
                'last_seen_at',
                '>=',
                now()->subMinutes(15)
            )
            ->count();
    }

    #[Computed]
    public function activeToday(): int
    {
        return User::query()
            ->whereHas('business')
            ->where(
                'last_seen_at',
                '>=',
                now()->startOfDay()
            )
            ->count();
    }

    #[Computed]
    public function activeSevenDays(): int
    {
        return User::query()
            ->whereHas('business')
            ->where(
                'last_seen_at',
                '>=',
                now()->subDays(7)
            )
            ->count();
    }

    public function accessInfo(
        User $user
    ): array {
        $business = $user->business;

        if (!$business) {
            return [
                'plan' => null,
                'grant' => null,
            ];
        }

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
        ];
    }
};
?>

<div class="mx-auto w-full max-w-7xl space-y-6">

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
                    text-emerald-600
                    dark:text-emerald-400
                "
            >
                Administração
            </p>

            <h1
                class="
                    mt-1 text-2xl
                    font-semibold tracking-tight
                    text-zinc-950
                    dark:text-white
                "
            >
                Acessos
            </h1>

            <p
                class="
                    mt-1 text-sm
                    text-zinc-600
                    dark:text-zinc-400
                "
            >
                Acompanhe quem está utilizando o Negozia
                e a atividade recente das empresas.
            </p>
        </div>
    </div>


    {{-- Indicadores --}}
    <div
        class="
            grid gap-4
            sm:grid-cols-2
            xl:grid-cols-4
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
                Usuários
            </p>

            <p
                class="
                    mt-2 text-3xl
                    font-semibold tracking-tight
                    text-zinc-950
                    dark:text-white
                "
            >
                {{ $this->totalUsers }}
            </p>

            <p class="mt-1 text-xs text-zinc-500">
                Com empresa vinculada
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

                <p class="text-sm text-zinc-500">
                    Ativos agora
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
                {{ $this->activeNow }}
            </p>

            <p class="mt-1 text-xs text-zinc-500">
                Últimos 15 minutos
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
                Ativos hoje
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
                Com atividade desde 00:00
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
                Últimos 7 dias
            </p>

            <p
                class="
                    mt-2 text-3xl
                    font-semibold tracking-tight
                    text-zinc-950
                    dark:text-white
                "
            >
                {{ $this->activeSevenDays }}
            </p>

            <p class="mt-1 text-xs text-zinc-500">
                Usuários que retornaram
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
        <input
            type="search"
            wire:model.live.debounce.400ms="search"
            placeholder="Buscar usuário, e-mail, empresa ou CNPJ..."
            class="
                w-full rounded-lg
                border border-zinc-300
                bg-white px-4 py-2.5
                text-sm text-zinc-900
                outline-none transition
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


    {{-- Tabela --}}
    <div
        class="
            overflow-hidden
            rounded-xl
            border border-zinc-200
            bg-white shadow-sm
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
                            Usuário
                        </th>

                        <th class="px-5 py-3">
                            Empresa
                        </th>

                        <th class="px-5 py-3">
                            Acesso
                        </th>

                        <th class="px-5 py-3">
                            Última atividade
                        </th>

                        <th class="px-5 py-3">
                            Cadastro
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
                    @forelse ($this->users as $user)

                        @php
                            $access =
                                $this->accessInfo($user);

                            $plan =
                                $access['plan'];

                            $grant =
                                $access['grant'];
                        @endphp

                        <tr
                            wire:key="access-user-{{ $user->id }}"
                            class="
                                transition
                                hover:bg-zinc-50/80
                                dark:hover:bg-zinc-800/40
                            "
                        >

                            <td class="px-5 py-4">
                                <div
                                    class="
                                        font-medium
                                        text-zinc-950
                                        dark:text-white
                                    "
                                >
                                    {{ $user->name }}
                                </div>

                                <div
                                    class="
                                        mt-1 text-xs
                                        text-zinc-500
                                    "
                                >
                                    {{ $user->email }}
                                </div>
                            </td>


                            <td class="px-5 py-4">
                                <div
                                    class="
                                        text-sm font-medium
                                        text-zinc-800
                                        dark:text-zinc-200
                                    "
                                >
                                    {{ $user->business?->name ?? '—' }}
                                </div>
                            </td>


                            <td class="px-5 py-4">
                                <div
                                    class="
                                        flex flex-col
                                        items-start gap-1
                                    "
                                >
                                    <span
                                        class="
                                            inline-flex
                                            rounded-full
                                            bg-zinc-100
                                            px-2.5 py-1
                                            text-xs font-semibold
                                            text-zinc-700
                                            dark:bg-zinc-800
                                            dark:text-zinc-200
                                        "
                                    >
                                        {{ $plan?->name ?? 'Sem acesso' }}
                                    </span>

                                    @if ($grant)
                                        <span
                                            class="
                                                text-xs font-medium
                                                text-emerald-600
                                                dark:text-emerald-400
                                            "
                                        >
                                            Cortesia até
                                            {{ $grant
                                                ->ends_at
                                                ->format('d/m/Y') }}
                                        </span>
                                    @endif
                                </div>
                            </td>


                            <td class="px-5 py-4">

                                @if ($user->last_seen_at)

                                    <div
                                        class="
                                            flex items-center
                                            gap-2
                                        "
                                    >
                                        @if (
                                            $user
                                                ->last_seen_at
                                                ->gte(
                                                    now()
                                                        ->subMinutes(15)
                                                )
                                        )
                                            <span
                                                class="
                                                    h-2 w-2
                                                    rounded-full
                                                    bg-emerald-500
                                                "
                                            ></span>
                                        @endif

                                        <span
                                            class="
                                                text-sm
                                                text-zinc-700
                                                dark:text-zinc-300
                                            "
                                        >
                                            {{ $user
                                                ->last_seen_at
                                                ->diffForHumans() }}
                                        </span>
                                    </div>

                                    <div
                                        class="
                                            mt-1 text-xs
                                            text-zinc-500
                                        "
                                    >
                                        {{ $user
                                            ->last_seen_at
                                            ->format(
                                                'd/m/Y H:i'
                                            ) }}
                                    </div>

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


                            <td class="px-5 py-4">
                                <div
                                    class="
                                        text-sm
                                        text-zinc-700
                                        dark:text-zinc-300
                                    "
                                >
                                    {{ $user
                                        ->created_at
                                        ->format('d/m/Y') }}
                                </div>

                                <div
                                    class="
                                        mt-1 text-xs
                                        text-zinc-500
                                    "
                                >
                                    {{ $user
                                        ->created_at
                                        ->diffForHumans() }}
                                </div>
                            </td>


                            <td
                                class="
                                    whitespace-nowrap
                                    px-5 py-4
                                    text-right
                                "
                            >
                                @if ($user->business)
                                    <a
                                        href="{{ route(
                                            'admin.businesses.show',
                                            $user->business
                                        ) }}"
                                        wire:navigate
                                        class="
                                            text-sm font-semibold
                                            text-zinc-700
                                            transition
                                            hover:text-zinc-950
                                            dark:text-zinc-300
                                            dark:hover:text-white
                                        "
                                    >
                                        Gerenciar →
                                    </a>
                                @endif
                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td
                                colspan="6"
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
                                    Nenhum acesso encontrado
                                </p>

                                <p
                                    class="
                                        mt-1 text-sm
                                        text-zinc-500
                                    "
                                >
                                    Os registros aparecerão conforme
                                    os usuários utilizarem o Negozia.
                                </p>
                            </td>
                        </tr>

                    @endforelse
                </tbody>
            </table>

        </div>

        @if ($this->users->hasPages())
            <div
                class="
                    border-t border-zinc-200
                    px-5 py-4
                    dark:border-zinc-800
                "
            >
                {{ $this->users->links() }}
            </div>
        @endif
    </div>
</div>
