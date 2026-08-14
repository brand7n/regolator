<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            @if (Laravel\Fortify\Features::canUpdateProfileInformation())
                @livewire('profile.update-profile-information-form')

                <x-section-border />
            @endif

            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()))
                <div class="mt-10 sm:mt-0">
                    @livewire('profile.update-password-form')
                </div>

                <x-section-border />
            @endif

            @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                <div class="mt-10 sm:mt-0">
                    @livewire('profile.two-factor-authentication-form')
                </div>

                <x-section-border />
            @endif

            <div x-data="{ status: '', error: '' }" class="mt-10 sm:mt-0">
                <section>
                    <header>
                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Passkeys</h2>

                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Add a passkey to sign in using your device's biometric sensor or PIN.
                        </p>
                    </header>

                    <div class="mt-4">
                        <button
                            x-show="!window.Passkeys?.isSupported()"
                            class="px-4 py-2 text-sm bg-gray-500 text-white rounded-md dark:bg-gray-700"
                            disabled
                        >
                            Passkeys not supported in this browser
                        </button>

                        <button
                            x-show="window.Passkeys?.isSupported()"
                            @click="
                                error = '';
                                window.Passkeys.register({ name: 'My device' })
                                    .then(() => { status = 'Passkey registered successfully!'; })
                                    .catch(err => { error = err.message; });
                            "
                            class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 dark:bg-gray-200 dark:text-gray-800 dark:hover:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                        >
                            Add Passkey
                        </button>

                        <p x-show="status" x-text="status" class="mt-3 text-sm text-green-600 dark:text-green-400"></p>
                        <p x-show="error" x-text="error" class="mt-3 text-sm text-red-600 dark:text-red-400"></p>
                    </div>
                </section>
            </div>

            <div class="mt-10 sm:mt-0">
                @livewire('profile.logout-other-browser-sessions-form')
            </div>

            @if (Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures())
                <x-section-border />

                <div class="mt-10 sm:mt-0">
                    @livewire('profile.delete-user-form')
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
