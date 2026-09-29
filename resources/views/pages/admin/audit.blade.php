<?php

use App\Models\AdminAuditLog;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Auditoria | Negozia')]
class extends Component
{
    use WithPagination;

    public string $search = '';

    #[Url(as: 'business')]
    public ?int $businessId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function logs()
    {
        return AdminAuditLog::query()
            ->when(
                $this->businessId !== null,
                fn (Builder $query) =>
                    $query->where(
                        'business_id',
                        $this->businessId
                    )
            )
            ->with([
                'admin',
                'business',
            ])
            ->when(
                trim($this->search) !== '',
                function (Builder $query): void {
                    $search = trim(
                        $this->search
                    );

                    $query->where(
                        function (Builder $query) use ($search): void {
                            $query
                                ->where(
                                    'action',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhereHas(
                                    'admin',
                                    fn (Builder $query) =>
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
                                )
                                ->orWhereHas(
                                    'business',
                                    fn (Builder $query) =>
                                        $query->where(
                                            'name',
                                            'like',
                                            "%{$search}%"
                                        )
                                );
                        }
                    );
                }
            )
            ->latest()
            ->paginate(25);
    }

    #[Computed]
    public function todayCount(): int
    {
        return AdminAuditLog::query()
            ->where(
                'created_at',
                '>=',
                now()->startOfDay()
            )
            ->count();
    }

    #[Computed]
    public function totalCount(): int
    {
        return AdminAuditLog::query()
            ->count();
    }

    public function actionLabel(
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
};
?>

<div class="mx-auto w-full max-w-7xl space-y-6">

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
            Auditoria
        </h1>

        <p
            class="
                mt-1 text-sm
                text-zinc-600
                dark:text-zinc-400
            "
        >
            Histórico das ações administrativas
            realizadas no Negozia.
        </p>
    </div>


    <div
        class="
            grid gap-4
            sm:grid-cols-2
        "
    >
        <div
            class="
                rounded-xl
                border border-zinc-200
                bg-white p-5 shadow-sm
                dark:border-zinc-800
                dark:bg-zinc-900
            "
        >
            <p class="text-sm text-zinc-500">
                Registros
            </p>

            <p
                class="
                    mt-2 text-3xl
                    font-semibold tracking-tight
                    text-zinc-950
                    dark:text-white
                "
            >
                {{ $this->totalCount }}
            </p>
        </div>

        <div
            class="
                rounded-xl
                border border-zinc-200
                bg-white p-5 shadow-sm
                dark:border-zinc-800
                dark:bg-zinc-900
            "
        >
            <p class="text-sm text-zinc-500">
                Ações hoje
            </p>

            <p
                class="
                    mt-2 text-3xl
                    font-semibold tracking-tight
                    text-zinc-950
                    dark:text-white
                "
            >
                {{ $this->todayCount }}
            </p>
        </div>
    </div>


    <div
        class="
            rounded-xl
            border border-zinc-200
            bg-white p-4 shadow-sm
            dark:border-zinc-800
            dark:bg-zinc-900
        "
    >
        <input
            type="search"
            wire:model.live.debounce.400ms="search"
            placeholder="Buscar por empresa, administrador ou ação..."
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
                            text-left text-xs
                            font-semibold uppercase
                            tracking-wide text-zinc-500
                        "
                    >
                        <th class="px-5 py-3">
                            Ação
                        </th>

                        <th class="px-5 py-3">
                            Empresa
                        </th>

                        <th class="px-5 py-3">
                            Administrador
                        </th>

                        <th class="px-5 py-3">
                            Data
                        </th>

                        <th class="px-5 py-3">
                            IP
                        </th>
                    </tr>
                </thead>

                <tbody
                    class="
                        divide-y divide-zinc-100
                        dark:divide-zinc-800
                    "
                >
                    @forelse ($this->logs as $log)

                        <tr
                            wire:key="audit-{{ $log->id }}"
                            class="
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
                                    {{ $this->actionLabel(
                                        $log->action
                                    ) }}
                                </div>

                                @if (
                                    data_get(
                                        $log->metadata,
                                        'ends_at'
                                    )
                                )
                                    <div
                                        class="
                                            mt-1 text-xs
                                            text-zinc-500
                                        "
                                    >
                                        Validade:
                                        {{
                                            \Illuminate\Support\Carbon::parse(
                                                data_get(
                                                    $log->metadata,
                                                    'ends_at'
                                                )
                                            )->format(
                                                'd/m/Y H:i'
                                            )
                                        }}
                                    </div>
                                @endif

                                @if (
                                    data_get(
                                        $log->metadata,
                                        'reason'
                                    )
                                )
                                    <div
                                        class="
                                            mt-1 text-xs
                                            text-zinc-500
                                        "
                                    >
                                        {{
                                            data_get(
                                                $log->metadata,
                                                'reason'
                                            )
                                        }}
                                    </div>
                                @endif
                            </td>


                            <td class="px-5 py-4">
                                @if ($log->business)
                                    <a
                                        href="{{ route(
                                            'admin.businesses.show',
                                            $log->business
                                        ) }}"
                                        wire:navigate
                                        class="
                                            text-sm font-medium
                                            text-zinc-800
                                            hover:text-zinc-950
                                            dark:text-zinc-200
                                            dark:hover:text-white
                                        "
                                    >
                                        {{ $log
                                            ->business
                                            ->name }}
                                    </a>
                                @else
                                    <span
                                        class="
                                            text-sm
                                            text-zinc-400
                                        "
                                    >
                                        —
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
                                    {{ $log
                                        ->admin
                                        ?->name
                                        ?? 'Sistema' }}
                                </div>

                                @if ($log->admin?->email)
                                    <div
                                        class="
                                            mt-1 text-xs
                                            text-zinc-500
                                        "
                                    >
                                        {{ $log
                                            ->admin
                                            ->email }}
                                    </div>
                                @endif
                            </td>


                            <td
                                class="
                                    whitespace-nowrap
                                    px-5 py-4
                                "
                            >
                                <div
                                    class="
                                        text-sm
                                        text-zinc-700
                                        dark:text-zinc-300
                                    "
                                >
                                    {{ $log
                                        ->created_at
                                        ->format(
                                            'd/m/Y H:i'
                                        ) }}
                                </div>

                                <div
                                    class="
                                        mt-1 text-xs
                                        text-zinc-500
                                    "
                                >
                                    {{ $log
                                        ->created_at
                                        ->diffForHumans() }}
                                </div>
                            </td>


                            <td class="px-5 py-4">
                                <span
                                    class="
                                        font-mono text-xs
                                        text-zinc-500
                                    "
                                >
                                    {{ $log->ip_address
                                        ?? '—' }}
                                </span>
                            </td>
                        </tr>

                    @empty

                        <tr>
                            <td
                                colspan="5"
                                class="
                                    px-5 py-14
                                    text-center text-sm
                                    text-zinc-500
                                "
                            >
                                Nenhuma ação administrativa
                                registrada ainda.
                            </td>
                        </tr>

                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->logs->hasPages())
            <div
                class="
                    border-t border-zinc-200
                    px-5 py-4
                    dark:border-zinc-800
                "
            >
                {{ $this->logs->links() }}
            </div>
        @endif
    </div>
</div>
