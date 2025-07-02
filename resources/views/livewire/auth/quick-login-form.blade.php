<div>
    <x-validation-errors class="mb-4" />

    @session('status')
        <div class="mb-4 font-medium text-sm text-green-600 dark:text-green-400">
            {{ $value }}
        </div>
    @endsession


    @if ($userExists === null)
        <form wire:submit.prevent="checkEmail" x-data>
            <div>
                <x-label for="email" value="Can I Haz Email" />
                <x-input id="email" wire:model.defer="email" class="block mt-1 w-full" type="email" :value="old('email')" required autofocus autocomplete="username" />
            </div>
            <div class="flex items-center justify-end mt-4">
                <x-button type="submit" class="ms-4">
                Continue
                </x-button>
            </div>
        </form>
    @elseif ($userExists === false)
        <form wire:submit.prevent="registerAndSendLink" x-data x-init="$nextTick(() => $refs.nameInput.focus())">    <div>
                <x-label for="email" value="{{ __('Email') }}" />
                <x-input id="email" wire:model.defer="email" placeholder="Your email" class="block mt-1 w-full" type="email" :value="old('email')" readonly />
            </div>

            <div class="mt-4">
                <x-label for="name" value="Can I Haz Hash Name" />
                <x-input id="name" wire:model.defer="name" placeholder="Your name" class="block mt-1 w-full" type="text" x-ref="nameInput" required autofocus />
            </div>

            <div class="mt-4">
                <x-label for="name" value="Can I Haz Cell Number" />
                <x-input id="name" wire:model.defer="phone" placeholder="+1(555)555-5555" class="block mt-1 w-full" type="text" x-ref="phoneInput" required autofocus />
            </div>

            <div class="flex items-center justify-end mt-4">
                <x-button type="submit" class="ms-4">
                    Register
                </x-button>
            </div>
        </form>
    @endif
</div>