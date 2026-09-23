<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>

    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="flex min-h-svh flex-col items-center justify-center p-6 md:p-10">

            <div class="flex w-full max-w-sm flex-col gap-7">

                <a
                    href="{{ route('home') }}"
                    class="mx-auto inline-flex items-center gap-2.5 font-semibold text-zinc-950 dark:text-white"
                    wire:navigate
                >
                    <x-app-logo-icon class="size-10" />

                    <span class="text-lg tracking-tight">
                        {{ config('app.name', 'Fechou') }}
                    </span>
                </a>

                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>

            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
