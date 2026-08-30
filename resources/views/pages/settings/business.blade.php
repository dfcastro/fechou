<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Empresa | Fechou')] class extends Component
{
    use WithFileUploads;

    public string $name = '';
    public string $document = '';
    public string $email = '';
    public string $phone = '';
    public string $whatsapp = '';

    public string $address = '';
    public string $city = '';
    public string $state = '';
    public string $postalCode = '';

    public string $pixKey = '';

    public $logo = null;

    public function mount(): void
    {
        $business = Auth::user()
            ->business()
            ->firstOrCreate(
                [],
                [
                    'name' => Auth::user()->name,
                ]
            );

        $this->name = $business->name ?? '';
        $this->document = $business->document ?? '';
        $this->email = $business->email ?? '';
        $this->phone = $business->phone ?? '';
        $this->whatsapp = $business->whatsapp ?? '';

        $this->address = $business->address ?? '';
        $this->city = $business->city ?? '';
        $this->state = $business->state ?? '';
        $this->postalCode = $business->postal_code ?? '';

        $this->pixKey = $business->pix_key ?? '';
    }

    public function updatedLogo(): void
    {
        $this->validateOnly('logo', [
            'logo' => [
                'nullable',
                'image',
                'mimes:png,jpg,jpeg',
                'max:2048',
            ],
        ]);
    }

    public function removeLogo(): void
    {
        $business = Auth::user()->business;

        abort_unless($business, 403);

        if ($business->logo_path) {
            Storage::disk('public')
                ->delete($business->logo_path);
        }

        $business->update([
            'logo_path' => null,
        ]);

        $this->logo = null;

        session()->flash(
            'success',
            'Logo removida com sucesso.'
        );
    }

    public function save(): void
    {
        $business = Auth::user()->business;

        abort_unless($business, 403);

        $validated = $this->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'document' => [
                'nullable',
                'string',
                'max:20',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'whatsapp' => [
                'nullable',
                'string',
                'max:30',
            ],

            'address' => [
                'nullable',
                'string',
                'max:255',
            ],

            'city' => [
                'nullable',
                'string',
                'max:255',
            ],

            'state' => [
                'nullable',
                'string',
                'max:2',
            ],

            'postalCode' => [
                'nullable',
                'string',
                'max:10',
            ],

            'pixKey' => [
                'nullable',
                'string',
                'max:255',
            ],

            'logo' => [
                'nullable',
                'image',
                'mimes:png,jpg,jpeg',
                'max:2048',
            ],
        ], [
            'name.required' =>
            'Informe o nome da empresa.',

            'email.email' =>
            'Informe um e-mail válido.',

            'state.max' =>
            'Use apenas a sigla do estado.',

            'logo.image' =>
            'Selecione uma imagem válida.',

            'logo.mimes' =>
            'A logo deve ser PNG, JPG ou JPEG.',

            'logo.max' =>
            'A logo deve ter no máximo 2 MB.',
        ]);

        $logoPath = $business->logo_path;

        if ($this->logo) {

            if ($logoPath) {
                Storage::disk('public')
                    ->delete($logoPath);
            }

            $logoPath = $this->logo->store(
                'business-logos',
                'public'
            );
        }

        $business->update([
            'name' =>
            trim($validated['name']),

            'document' =>
            $validated['document'] ?: null,

            'email' =>
            $validated['email'] ?: null,

            'phone' =>
            $validated['phone'] ?: null,

            'whatsapp' =>
            $validated['whatsapp'] ?: null,

            'logo_path' =>
            $logoPath,

            'address' =>
            $validated['address'] ?: null,

            'city' =>
            $validated['city'] ?: null,

            'state' =>
            $validated['state']
                ? strtoupper($validated['state'])
                : null,

            'postal_code' =>
            $validated['postalCode'] ?: null,

            'pix_key' =>
            $validated['pixKey'] ?: null,
        ]);

        $this->logo = null;

        session()->flash(
            'success',
            'Dados da empresa atualizados com sucesso.'
        );
    }
};
?>

<div class="mx-auto max-w-4xl space-y-6">

    <div>

        <h1 class="text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">
            Minha empresa
        </h1>

        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
            Essas informações aparecem nos seus orçamentos e PDFs.
        </p>

    </div>


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
            ">
        {{ session('success') }}
    </div>

    @endif


    <form wire:submit="save" class="space-y-6">

        {{-- LOGO --}}

        <section
            class="
                rounded-2xl
                border border-zinc-200
                bg-white
                p-6
                shadow-sm

                dark:border-zinc-800
                dark:bg-zinc-900
            ">

            <h2 class="font-semibold text-zinc-950 dark:text-white">
                Identidade da empresa
            </h2>

            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Use preferencialmente uma logo quadrada ou horizontal em PNG.
            </p>


            <div class="mt-6 flex flex-col gap-5 sm:flex-row sm:items-center">

                <div
                    class="
                        flex size-24 shrink-0
                        items-center justify-center
                        overflow-hidden
                        rounded-2xl
                        border border-zinc-200
                        bg-zinc-50

                        dark:border-zinc-700
                        dark:bg-zinc-950
                    ">

                    @if ($logo)

                    <img
                        src="{{ $logo->temporaryUrl() }}"
                        class="h-full w-full object-contain p-2"
                        alt="Nova logo">

                    @elseif (Auth::user()->business->logo_path)

                    <img
                        src="{{ asset(
                                'storage/'.
                                Auth::user()->business->logo_path
                            ) }}"
                        class="h-full w-full object-contain p-2"
                        alt="{{ Auth::user()->business->name }}">

                    @else

                    <span
                        class="
                                text-3xl font-bold
                                text-emerald-600
                                dark:text-emerald-400
                            ">
                        {{ mb_strtoupper(
                                mb_substr($name ?: 'F', 0, 1)
                            ) }}
                    </span>

                    @endif

                </div>


                <div class="flex-1">

                    <input
                        type="file"
                        wire:model="logo"
                        accept=".png,.jpg,.jpeg,image/png,image/jpeg"
                        class="
                            block w-full
                            text-sm
                            text-zinc-600

                            file:mr-4
                            file:rounded-lg
                            file:border-0
                            file:bg-zinc-100
                            file:px-4
                            file:py-2.5
                            file:text-sm
                            file:font-semibold
                            file:text-zinc-700

                            hover:file:bg-zinc-200

                            dark:text-zinc-400
                            dark:file:bg-zinc-800
                            dark:file:text-zinc-200
                            dark:hover:file:bg-zinc-700
                        ">


                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                        PNG ou JPG, até 2 MB.
                    </p>


                    @error('logo')

                    <p class="mt-2 text-sm text-red-600 dark:text-red-400">
                        {{ $message }}
                    </p>

                    @enderror


                    @if (Auth::user()->business->logo_path)

                    <button
                        type="button"
                        wire:click="removeLogo"
                        wire:confirm="Deseja remover a logo da empresa?"
                        class="
                                mt-3
                                text-sm font-medium
                                text-red-600
                                hover:text-red-700

                                dark:text-red-400
                                dark:hover:text-red-300
                            ">
                        Remover logo
                    </button>

                    @endif

                </div>

            </div>

        </section>


        {{-- DADOS --}}

        <section
            class="
                rounded-2xl
                border border-zinc-200
                bg-white
                shadow-sm

                dark:border-zinc-800
                dark:bg-zinc-900
            ">

            <div class="border-b border-zinc-200 px-6 py-5 dark:border-zinc-800">

                <h2 class="font-semibold text-zinc-950 dark:text-white">
                    Dados da empresa
                </h2>

                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    Informações exibidas nas propostas.
                </p>

            </div>


            <div class="grid gap-5 p-6 md:grid-cols-2">

                <div class="md:col-span-2">

                    <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        Nome da empresa *
                    </label>

                    <input
                        type="text"
                        wire:model="name"
                        class="
                            w-full rounded-lg
                            border border-zinc-300
                            bg-white
                            px-3 py-2.5
                            text-sm text-zinc-900

                            focus:border-emerald-500
                            focus:ring-2
                            focus:ring-emerald-500/20

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-white
                        ">

                    @error('name')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ $message }}
                    </p>
                    @enderror

                </div>


                <div>

                    <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        CPF/CNPJ
                    </label>

                    <input
                        type="text"
                        wire:model="document"
                        class="
                            w-full rounded-lg
                            border border-zinc-300
                            bg-white
                            px-3 py-2.5
                            text-sm text-zinc-900

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-white
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
                            w-full rounded-lg
                            border border-zinc-300
                            bg-white
                            px-3 py-2.5
                            text-sm text-zinc-900

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-white
                        ">

                </div>


                <div>

                    <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        Telefone
                    </label>

                    <input
                        type="text"
                        wire:model="phone"
                        placeholder="(33) 3333-3333"
                        class="
                            w-full rounded-lg
                            border border-zinc-300
                            bg-white
                            px-3 py-2.5
                            text-sm text-zinc-900

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-white
                        ">

                </div>


                <div>

                    <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        WhatsApp
                    </label>

                    <input
                        type="text"
                        wire:model="whatsapp"
                        placeholder="(33) 99999-9999"
                        class="
                            w-full rounded-lg
                            border border-zinc-300
                            bg-white
                            px-3 py-2.5
                            text-sm text-zinc-900

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-white
                        ">

                </div>

            </div>

        </section>


        {{-- ENDEREÇO --}}

        <section
            class="
                rounded-2xl
                border border-zinc-200
                bg-white
                shadow-sm

                dark:border-zinc-800
                dark:bg-zinc-900
            ">

            <div class="border-b border-zinc-200 px-6 py-5 dark:border-zinc-800">

                <h2 class="font-semibold text-zinc-950 dark:text-white">
                    Endereço
                </h2>

            </div>


            <div class="grid gap-5 p-6 md:grid-cols-6">

                <div class="md:col-span-6">

                    <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        Endereço
                    </label>

                    <input
                        type="text"
                        wire:model="address"
                        class="
                            w-full rounded-lg
                            border border-zinc-300
                            bg-white
                            px-3 py-2.5
                            text-sm text-zinc-900

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-white
                        ">

                </div>


                <div class="md:col-span-3">

                    <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        Cidade
                    </label>

                    <input
                        type="text"
                        wire:model="city"
                        class="
                            w-full rounded-lg
                            border border-zinc-300
                            bg-white
                            px-3 py-2.5
                            text-sm text-zinc-900

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-white
                        ">

                </div>


                <div class="md:col-span-1">

                    <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        UF
                    </label>

                    <input
                        type="text"
                        wire:model="state"
                        maxlength="2"
                        class="
                            w-full rounded-lg
                            border border-zinc-300
                            bg-white
                            px-3 py-2.5
                            text-sm uppercase text-zinc-900

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-white
                        ">

                </div>


                <div class="md:col-span-2">

                    <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        CEP
                    </label>

                    <input
                        type="text"
                        wire:model="postalCode"
                        class="
                            w-full rounded-lg
                            border border-zinc-300
                            bg-white
                            px-3 py-2.5
                            text-sm text-zinc-900

                            dark:border-zinc-700
                            dark:bg-zinc-950
                            dark:text-white
                        ">

                </div>

            </div>

        </section>


        {{-- PAGAMENTO --}}

        <section
            class="
                rounded-2xl
                border border-zinc-200
                bg-white
                p-6
                shadow-sm

                dark:border-zinc-800
                dark:bg-zinc-900
            ">

            <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                Chave PIX
            </label>

            <input
                type="text"
                wire:model="pixKey"
                placeholder="CPF, CNPJ, e-mail, telefone ou chave aleatória"
                class="
                    w-full rounded-lg
                    border border-zinc-300
                    bg-white
                    px-3 py-2.5
                    text-sm text-zinc-900

                    dark:border-zinc-700
                    dark:bg-zinc-950
                    dark:text-white
                ">

        </section>


        <div class="flex justify-end">

            <button
                type="submit"
                wire:loading.attr="disabled"
                class="
                    inline-flex items-center justify-center
                    rounded-lg
                    bg-emerald-600
                    px-5 py-3
                    text-sm font-semibold
                    text-white
                    shadow-sm

                    hover:bg-emerald-700

                    disabled:opacity-60

                    dark:bg-emerald-500
                    dark:text-zinc-950
                    dark:hover:bg-emerald-400
                ">

                <span wire:loading.remove wire:target="save">
                    Salvar empresa
                </span>

                <span wire:loading wire:target="save">
                    Salvando...
                </span>

            </button>

        </div>

    </form>

</div>