<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <div class="flex justify-center mb-6">
                <img src="{{ asset('7lrvmp.jpg') }}" alt="canihaz" class="h-48 w-auto">
            </div>
        </x-slot>
        @livewire('auth.quick-login-form')
    </x-authentication-card>
</x-guest-layout>