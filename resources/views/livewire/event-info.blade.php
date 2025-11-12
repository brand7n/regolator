<div class="max-w-sm mx-auto p-4 bg-white rounded shadow">
    @if (session()->has('message'))
        <div class="mb-3 p-2 text-green-700 bg-green-100 rounded">
            {{ session('message') }}
        </div>
    @endif

    <form wire:submit.prevent="submit" class="space-y-3">
        <div>
            <label for="cabin_number" class="block text-sm font-medium text-gray-700">Cabin #</label>
            <input
                type="number"
                id="cabin_number"
                wire:model.defer="cabin_number"
                class="w-full border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500"
            />
            @error('cabin_number') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
        </div>

        <div>
            <label for="shot_stop" class="block text-sm font-medium text-gray-700">Shot Stop</label>
            <input
                type="text"
                id="shot_stop"
                wire:model.defer="shot_stop"
                class="w-full border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500"
                placeholder="Name or initials"
            />
            @error('shot_stop') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
        </div>

        <div class="text-right">
            <button
                type="submit"
                class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700"
                wire:loading.attr="disabled"
            >
                Submit
            </button>
        </div>
    </form>
</div>

