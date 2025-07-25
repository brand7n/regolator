<x-form-section submit="updateProfileInformation">
    <x-slot name="title">
        {{ __('Profile Information') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Update your account\'s profile information and email address.') }}
    </x-slot>

    <x-slot name="form">
        <!-- Profile Photo -->
        @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
            <div x-data="{photoName: null, photoPreview: null}" class="col-span-6 sm:col-span-4">
                <!-- Profile Photo File Input -->
                <input type="file" id="photo" class="hidden"
                            wire:model.live="photo"
                            x-ref="photo"
                            x-on:change="
                                    photoName = $refs.photo.files[0].name;
                                    const reader = new FileReader();
                                    reader.onload = (e) => {
                                        photoPreview = e.target.result;
                                    };
                                    reader.readAsDataURL($refs.photo.files[0]);
                            " />

                <x-label for="photo" value="{{ __('Photo') }}" />

                <!-- Current Profile Photo -->
                <div class="mt-2" x-show="! photoPreview">
                    <img src="{{ $this->user->profile_photo_url }}" alt="{{ $this->user->name }}" class="rounded-full h-20 w-20 object-cover">
                </div>

                <!-- New Profile Photo Preview -->
                <div class="mt-2" x-show="photoPreview" style="display: none;">
                    <span class="block rounded-full w-20 h-20 bg-cover bg-no-repeat bg-center"
                          x-bind:style="'background-image: url(\'' + photoPreview + '\');'">
                    </span>
                </div>

                <x-secondary-button class="mt-2 me-2" type="button" x-on:click.prevent="$refs.photo.click()">
                    {{ __('Select A New Photo') }}
                </x-secondary-button>

                @if ($this->user->profile_photo_path)
                    <x-secondary-button type="button" class="mt-2" wire:click="deleteProfilePhoto">
                        {{ __('Remove Photo') }}
                    </x-secondary-button>
                @endif

                <x-input-error for="photo" class="mt-2" />
            </div>
        @endif

        <!-- Name -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="name" value="Hash Name" />
            <x-input id="name" type="text" class="mt-1 block w-full" wire:model="state.name" required autocomplete="name" />
            <x-input-error for="name" class="mt-2" />
        </div>

        <!-- Nerd Name -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="nerd_name" value="Nerd Name" />
            <x-input id="nerd_name" type="text" class="mt-1 block w-full" wire:model="state.nerd_name" required autocomplete="nerd_name" />
            <x-input-error for="nerd_name" class="mt-2" />
        </div>

        <!-- Kennel -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="kennel" value="Kennel" />
            <x-input id="kennel" type="text" class="mt-1 block w-full" wire:model="state.kennel" required autocomplete="kennel" />
            <x-input-error for="kennel" class="mt-2" />
        </div>

        <!-- Shirt Size -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="shirt_size" value="Shirt Size" />
            <select id="shirt_size" type="text" class="mt-1 block w-full" wire:model="state.shirt_size" required autocomplete="shirt_size" />
                <option value="XS">Extra Small</option>
                <option value="SM">Small</option>
                <option value="MD">Medium</option>
                <option value="LG">Large</option>
                <option value="XL">Extra Large</option>
                <option value="2XL">2X Large</option>
                <option value="3XL">3X Large</option>
            </select>
            <x-input-error for="shirt_size" class="mt-2" />
        </div>

        <!-- Short Bus -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="short_bus" value="Short Bus" />
            <select id="short_bus" type="text" class="mt-1 block w-full" wire:model="state.short_bus" required autocomplete="short_bus" >
                <option value="Y">Yes</option>
                <option value="N">No</option>
            </select>
            <x-input-error for="short_bus" class="mt-2" />
        </div>

        <!-- Email -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="email" value="{{ __('Email') }}" />
            <x-input id="email" type="email" class="mt-1 block w-full" wire:model="state.email" required autocomplete="username" />
            <x-input-error for="email" class="mt-2" />

            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::emailVerification()) && ! $this->user->hasVerifiedEmail())
                <p class="text-sm mt-2 dark:text-white">
                    {{ __('Your email address is unverified.') }}

                    <button type="button" class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" wire:click.prevent="sendEmailVerification">
                        {{ __('Click here to re-send the verification email.') }}
                    </button>
                </p>

                @if ($this->verificationLinkSent)
                    <p class="mt-2 font-medium text-sm text-green-600 dark:text-green-400">
                        {{ __('A new verification link has been sent to your email address.') }}
                    </p>
                @endif
            @endif
        </div>

        <!-- Phone -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="phone" value="Phone" />
            <x-input id="phone" type="text" class="mt-1 block w-full" wire:model="state.phone" required autocomplete="phone" />
            <x-input-error for="phone" class="mt-2" />
        </div>
    </x-slot>

    <x-slot name="actions">
        <x-action-message class="me-3" on="saved">
            {{ __('Saved.') }}
        </x-action-message>

        <x-button wire:loading.attr="disabled" wire:target="photo">
            {{ __('Save') }}
        </x-button>
    </x-slot>
</x-form-section>
