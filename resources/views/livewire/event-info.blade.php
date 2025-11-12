<div class="max-w-sm mx-auto p-4 bg-gray-900 rounded shadow shadow-gray-800">
    @if (session()->has('message'))
        <div class="mb-3 p-2 text-green-200 bg-green-900 rounded">
            {{ session('message') }}
        </div>
    @endif

    <form wire:submit.prevent="submit" class="space-y-4">
        <div>
            <label for="cabin_number" class="block text-sm font-medium text-white">
                Cabin #
            </label>
<input
  type="number"
  id="cabin_number"
  wire:model.defer="cabin_number"
  class="mt-2 block w-full rounded-md border-0 py-1.5 px-3 text-white bg-gray-800
         ring-1 ring-inset ring-gray-700 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6"
  style="appearance: textfield; -moz-appearance: textfield; -webkit-appearance: textfield;"
/>
            @error('cabin_number')
                <span class="text-sm text-red-200">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="shot_stop" class="block text-sm font-medium text-white">
                Shot Stop?
            </label>
            <input
                type="text"
                id="shot_stop"
                wire:model.defer="shot_stop"
                placeholder="Let us know if your cabin is planning a shot stop Friday"
                class="mt-2 block w-full rounded-md border-0 py-1.5 px-3 text-white bg-gray-800
                       ring-1 ring-inset ring-gray-700 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6"
            />
            @error('shot_stop')
                <span class="text-sm text-red-200">{{ $message }}</span>
            @enderror
        </div>

        <div class="text-right">
            <button
                type="submit"
                class="mt-2 inline-flex items-center justify-center rounded-md border-0 py-1.5 px-4
                       text-sm font-medium text-white bg-indigo-600 ring-1 ring-inset ring-gray-700
                       hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-600"
                wire:loading.attr="disabled"
            >
                Submit
            </button>
        </div>
    </form>
</div>
