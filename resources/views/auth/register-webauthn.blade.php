<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <a href="/">
                <x-application-logo class="w-20 h-20 fill-current text-gray-500" />
            </a>
        </x-slot>

        <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Register a security key for your account.') }}
        </div>

        <livewire:auth.register-webauthn />
    </x-authentication-card>
</x-guest-layout> 