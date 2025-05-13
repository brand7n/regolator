<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-application-mark class="block h-24 w-auto" />
        </x-slot>
        @livewire('auth.quick-login-form')
    </x-authentication-card>
</x-guest-layout>