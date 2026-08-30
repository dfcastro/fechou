<?php

use App\Models\Client;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Clientes | Fechou')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;
    public ?int $confirmingDeleteId = null;

    public string $name = '';
    public string $document = '';
    public string $email = '';
    public string $phone = '';
    public string $whatsapp = '';
    public string $notes = '';

    #[Computed]
    public function business()
    {
        return Auth::user()->business;
    }

    #[Computed]
    public function clients()
    {
        if (! $this->business) {
            return Client::query()
                ->whereRaw('1 = 0')
                ->paginate(10);
        }

        return $this->business
            ->clients()
            ->withCount('quotes')
            ->when($this->search, function ($query) {
                $search = '%' . $this->search . '%';

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', $search)
                        ->orWhere('document', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhere('phone', 'like', $search)
                        ->orWhere('whatsapp', 'like', $search);
                });
            })
            ->orderBy('name')
            ->paginate(10);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $clientId): void
    {
        abort_unless($this->business, 403);

        $client = $this->business
            ->clients()
            ->whereKey($clientId)
            ->firstOrFail();

        $this->editingId = $client->id;
        $this->name = $client->name;
        $this->document = $client->document ?? '';
        $this->email = $client->email ?? '';
        $this->phone = $client->phone ?? '';
        $this->whatsapp = $client->whatsapp ?? '';
        $this->notes = $client->notes ?? '';

        $this->showForm = true;
    }

    public function save(): void
    {
        abort_unless($this->business, 403);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'document' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'name.required' => 'Informe o nome do cliente.',
            'email.email' => 'Informe um e-mail válido.',
        ]);

        $validated = array_map(
            fn($value) => $value === '' ? null : $value,
            $validated
        );

        if ($this->editingId) {
            $client = $this->business
                ->clients()
                ->whereKey($this->editingId)
                ->firstOrFail();

            $client->update($validated);

            session()->flash('success', 'Cliente atualizado com sucesso.');
        } else {
            $this->business
                ->clients()
                ->create($validated);

            session()->flash('success', 'Cliente cadastrado com sucesso.');
        }

        $this->cancel();
    }

    public function cancel(): void
    {
        $this->showForm = false;

        $this->resetForm();
        $this->resetValidation();
    }

    public function confirmDelete(int $clientId): void
    {
        abort_unless($this->business, 403);

        $this->business
            ->clients()
            ->whereKey($clientId)
            ->firstOrFail();

        $this->resetErrorBag('delete');

        $this->confirmingDeleteId = $clientId;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
        $this->resetErrorBag('delete');
    }

    public function deleteClient(): void
    {
        abort_unless($this->business, 403);

        if (! $this->confirmingDeleteId) {
            return;
        }

        $client = $this->business
            ->clients()
            ->whereKey($this->confirmingDeleteId)
            ->firstOrFail();

        if ($client->quotes()->exists()) {
            $this->addError(
                'delete',
                'Este cliente possui orçamentos vinculados e não pode ser excluído.'
            );

            $this->confirmingDeleteId = null;

            return;
        }

        $client->delete();

        $this->confirmingDeleteId = null;

        session()->flash('success', 'Cliente excluído com sucesso.');
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId',
            'name',
            'document',
            'email',
            'phone',
            'whatsapp',
            'notes',
        ]);
    }
};
?>

<div class="space-y-6">

    {{-- Cabeçalho --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-950 dark:text-zinc-50">
                Clientes
            </h1>

            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Cadastre e gerencie os clientes que recebem seus orçamentos.
            </p>
        </div>

        <button
            type="button"
            wire:click="create"
            class="
                inline-flex items-center justify-center gap-2
                rounded-lg
                bg-emerald-600
                px-4 py-2.5
                text-sm font-semibold text-white
                shadow-sm
                transition
                hover:bg-emerald-700
                focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2
                dark:bg-emerald-500
                dark:text-zinc-950
                dark:hover:bg-emerald-400
                dark:focus:ring-offset-zinc-950
            ">
            <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" d="M12 5v14M5 12h14" />
            </svg>

            Novo cliente
        </button>
    </div>

    {{-- Sucesso --}}
    @if (session('success'))
    <div class="
            rounded-xl border border-emerald-200
            bg-emerald-50 px-4 py-3
            text-sm font-medium text-emerald-800
            dark:border-emerald-900
            dark:bg-emerald-950/40
            dark:text-emerald-300
        ">
        {{ session('success') }}
    </div>
    @endif

    @error('delete')
    <div class="
            rounded-xl border border-red-200
            bg-red-50 px-4 py-3
            text-sm font-medium text-red-800
            dark:border-red-900
            dark:bg-red-950/40
            dark:text-red-300
        ">
        {{ $message }}
    </div>
    @enderror

    {{-- Formulário --}}
    @if ($showForm)
    <form
        wire:submit="save"
        class="
                rounded-2xl
                border border-zinc-200
                bg-white p-6
                shadow-sm
                dark:border-zinc-800
                dark:bg-zinc-900
            ">
        <div class="mb-6 flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">
                    {{ $editingId ? 'Editar cliente' : 'Novo cliente' }}
                </h2>

                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    Preencha apenas as informações disponíveis.
                </p>
            </div>

            <button
                type="button"
                wire:click="cancel"
                title="Fechar"
                class="
                        inline-flex size-9 items-center justify-center
                        rounded-lg
                        text-zinc-500
                        transition
                        hover:bg-zinc-100 hover:text-zinc-900
                        dark:text-zinc-400
                        dark:hover:bg-zinc-800 dark:hover:text-white
                    ">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                </svg>
            </button>
        </div>

        <div class="grid gap-5 md:grid-cols-2">

            <div class="md:col-span-2">
                <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    Nome *
                </label>

                <input
                    type="text"
                    wire:model="name"
                    autofocus
                    class="
                            w-full rounded-lg
                            border border-zinc-300
                            bg-white
                            px-3 py-2.5
                            text-sm text-zinc-900
                            placeholder:text-zinc-400
                            outline-none transition
                            focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20
                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-zinc-100
                            dark:placeholder:text-zinc-600
                            dark:focus:border-emerald-500
                        ">

                @error('name')
                <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">
                    {{ $message }}
                </p>
                @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    CPF / CNPJ
                </label>

                <input
                    type="text"
                    wire:model="document"
                    class="
                            w-full rounded-lg border border-zinc-300
                            bg-white px-3 py-2.5
                            text-sm text-zinc-900
                            outline-none
                            focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20
                            dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100
                        ">
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    WhatsApp
                </label>

                <input
                    type="text"
                    wire:model="whatsapp"
                    class="
                            w-full rounded-lg border border-zinc-300
                            bg-white px-3 py-2.5
                            text-sm text-zinc-900
                            outline-none
                            focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20
                            dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100
                        ">
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    Telefone
                </label>

                <input
                    type="text"
                    wire:model="phone"
                    class="
                            w-full rounded-lg border border-zinc-300
                            bg-white px-3 py-2.5
                            text-sm text-zinc-900
                            outline-none
                            focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20
                            dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100
                        ">
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    E-mail
                </label>

                <input
                    type="email"
                    wire:model="email"
                    class="
                            w-full rounded-lg border border-zinc-300
                            bg-white px-3 py-2.5
                            text-sm text-zinc-900
                            outline-none
                            focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20
                            dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100
                        ">

                @error('email')
                <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">
                    {{ $message }}
                </p>
                @enderror
            </div>

            <div class="md:col-span-2">
                <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    Observações
                </label>

                <textarea
                    wire:model="notes"
                    rows="3"
                    class="
                            w-full resize-y rounded-lg border border-zinc-300
                            bg-white px-3 py-2.5
                            text-sm text-zinc-900
                            outline-none
                            focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20
                            dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100
                        "></textarea>
            </div>

        </div>

        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <button
                type="button"
                wire:click="cancel"
                class="
                        inline-flex items-center justify-center
                        rounded-lg
                        border border-zinc-300
                        bg-white
                        px-4 py-2.5
                        text-sm font-medium text-zinc-700
                        transition
                        hover:bg-zinc-100
                        dark:border-zinc-700
                        dark:bg-zinc-900
                        dark:text-zinc-200
                        dark:hover:bg-zinc-800
                    ">
                Cancelar
            </button>

            <button
                type="submit"
                wire:loading.attr="disabled"
                class="
                        inline-flex items-center justify-center gap-2
                        rounded-lg
                        bg-emerald-600
                        px-5 py-2.5
                        text-sm font-semibold text-white
                        transition
                        hover:bg-emerald-700
                        disabled:cursor-not-allowed disabled:opacity-60
                        dark:bg-emerald-500
                        dark:text-zinc-950
                        dark:hover:bg-emerald-400
                    ">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 12l4 4L19 6" />
                </svg>

                {{ $editingId ? 'Salvar alterações' : 'Cadastrar cliente' }}
            </button>
        </div>

    </form>
    @endif

    {{-- Pesquisa --}}
    <div class="
        rounded-xl
        border border-zinc-200
        bg-white p-4
        shadow-sm
        dark:border-zinc-800
        dark:bg-zinc-900
    ">
        <div class="relative">
            <svg
                class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-zinc-400 dark:text-zinc-500"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2">
                <circle cx="11" cy="11" r="7" />
                <path stroke-linecap="round" d="m20 20-3.5-3.5" />
            </svg>

            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Pesquisar por nome, CPF/CNPJ, e-mail ou telefone..."
                class="
                    w-full rounded-lg
                    border border-zinc-300
                    bg-white
                    py-2.5 pl-10 pr-4
                    text-sm text-zinc-900
                    placeholder:text-zinc-400
                    outline-none
                    focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20
                    dark:border-zinc-700
                    dark:bg-zinc-950
                    dark:text-zinc-100
                    dark:placeholder:text-zinc-600
                ">
        </div>
    </div>

    {{-- Tabela --}}
    <div class="
        overflow-hidden rounded-xl
        border border-zinc-200
        bg-white
        shadow-sm
        dark:border-zinc-800
        dark:bg-zinc-900
    ">

        <div class="overflow-x-auto">
            <table class="w-full text-sm">

                <thead class="
                    border-b border-zinc-200
                    bg-zinc-50
                    text-left text-xs font-semibold
                    uppercase tracking-wide text-zinc-500
                    dark:border-zinc-800
                    dark:bg-zinc-950
                    dark:text-zinc-400
                ">
                    <tr>
                        <th class="px-5 py-3">Cliente</th>
                        <th class="px-5 py-3">Contato</th>
                        <th class="px-5 py-3">Documento</th>
                        <th class="px-5 py-3 text-center">Orçamentos</th>
                        <th class="px-5 py-3 text-right">Ações</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">

                    @forelse ($this->clients as $client)

                    <tr
                        wire:key="client-{{ $client->id }}"
                        class="
                                transition
                                hover:bg-zinc-50
                                dark:hover:bg-zinc-800/60
                            ">
                        <td class="px-5 py-4">
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $client->name }}
                            </div>

                            @if ($client->email)
                            <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $client->email }}
                            </div>
                            @endif
                        </td>

                        <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">
                            {{ $client->whatsapp ?: ($client->phone ?: '—') }}
                        </td>

                        <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">
                            {{ $client->document ?: '—' }}
                        </td>

                        <td class="px-5 py-4 text-center">
                            <span class="
                                    inline-flex min-w-8 items-center justify-center
                                    rounded-full
                                    bg-zinc-100
                                    px-2 py-1
                                    text-xs font-semibold text-zinc-700
                                    dark:bg-zinc-800
                                    dark:text-zinc-200
                                ">
                                {{ $client->quotes_count }}
                            </span>
                        </td>

                        <td class="px-5 py-4">
                            <div class="flex justify-end gap-2">

                                <button
                                    type="button"
                                    wire:click="edit({{ $client->id }})"
                                    class="
                                            inline-flex items-center gap-1.5
                                            rounded-lg
                                            border border-zinc-300
                                            bg-white
                                            px-3 py-1.5
                                            text-xs font-medium text-zinc-700
                                            transition
                                            hover:bg-zinc-100 hover:text-zinc-950
                                            dark:border-zinc-700
                                            dark:bg-zinc-900
                                            dark:text-zinc-200
                                            dark:hover:bg-zinc-800
                                            dark:hover:text-white
                                        ">
                                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z" />
                                    </svg>

                                    Editar
                                </button>

                                <button
                                    type="button"
                                    wire:click="confirmDelete({{ $client->id }})"
                                    class="
                                            inline-flex items-center gap-1.5
                                            rounded-lg
                                            border border-red-200
                                            bg-white
                                            px-3 py-1.5
                                            text-xs font-medium text-red-600
                                            transition
                                            hover:bg-red-50 hover:text-red-700
                                            dark:border-red-900
                                            dark:bg-zinc-900
                                            dark:text-red-400
                                            dark:hover:bg-red-950/40
                                            dark:hover:text-red-300
                                        ">
                                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" d="M4 7h16M10 11v6M14 11v6" />
                                        <path stroke-linejoin="round" d="M6 7l1 14h10l1-14M9 7V4h6v3" />
                                    </svg>

                                    Excluir
                                </button>

                            </div>
                        </td>
                    </tr>

                    @if ($confirmingDeleteId === $client->id)

                    <tr wire:key="delete-client-{{ $client->id }}">
                        <td
                            colspan="5"
                            class="
                                        border-y border-red-200
                                        bg-red-50
                                        px-5 py-4
                                        dark:border-red-950
                                        dark:bg-red-950/30
                                    ">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

                                <div>
                                    <p class="font-semibold text-red-800 dark:text-red-300">
                                        Excluir {{ $client->name }}?
                                    </p>

                                    <p class="mt-0.5 text-sm text-red-600 dark:text-red-400">
                                        Esta ação removerá o cliente da sua lista.
                                    </p>
                                </div>

                                <div class="flex gap-2">

                                    <button
                                        type="button"
                                        wire:click="cancelDelete"
                                        class="
                                                    rounded-lg
                                                    border border-zinc-300
                                                    bg-white
                                                    px-3 py-2
                                                    text-sm font-medium text-zinc-700
                                                    hover:bg-zinc-100
                                                    dark:border-zinc-700
                                                    dark:bg-zinc-900
                                                    dark:text-zinc-200
                                                    dark:hover:bg-zinc-800
                                                ">
                                        Cancelar
                                    </button>

                                    <button
                                        type="button"
                                        wire:click="deleteClient"
                                        class="
                                                    inline-flex items-center gap-2
                                                    rounded-lg
                                                    bg-red-600
                                                    px-3 py-2
                                                    text-sm font-semibold text-white
                                                    hover:bg-red-700
                                                    dark:bg-red-500
                                                    dark:text-white
                                                    dark:hover:bg-red-600
                                                ">
                                        Confirmar exclusão
                                    </button>

                                </div>

                            </div>
                        </td>
                    </tr>

                    @endif

                    @empty

                    <tr>
                        <td colspan="5" class="px-5 py-14 text-center">

                            <div class="
                                    mx-auto flex size-12 items-center justify-center
                                    rounded-full
                                    bg-zinc-100
                                    text-zinc-500
                                    dark:bg-zinc-800
                                    dark:text-zinc-300
                                ">
                                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                                    <circle cx="9" cy="7" r="4" />
                                    <path stroke-linecap="round" d="M19 8v6M16 11h6" />
                                </svg>
                            </div>

                            <p class="mt-4 font-medium text-zinc-700 dark:text-zinc-200">
                                Nenhum cliente encontrado.
                            </p>

                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                Cadastre seu primeiro cliente para criar um orçamento.
                            </p>

                        </td>
                    </tr>

                    @endforelse

                </tbody>
            </table>
        </div>

        @if ($this->clients->hasPages())
        <div class="
                border-t border-zinc-200
                bg-zinc-50/50
                px-5 py-4
                dark:border-zinc-800
                dark:bg-zinc-950/30
            ">
            {{ $this->clients->links() }}
        </div>
        @endif

    </div>

</div>