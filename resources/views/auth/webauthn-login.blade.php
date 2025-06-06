<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Please use your security key to log in.') }}
        </div>

        <livewire:auth.login-with-webauthn />
    </x-authentication-card>
</x-guest-layout> 