<div class="max-w-md mx-auto p-4 bg-gray-900 rounded shadow shadow-gray-800">
    @if (session()->has('message'))
        <div class="mb-3 p-2 text-green-200 bg-green-900 rounded">
            {{ session('message') }}
        </div>
    @endif

    <form wire:submit.prevent="submit" class="space-y-4">
        @foreach($fieldDefinitions as $field)
            <div>
                <label for="field_{{ $field['name'] }}" class="block text-sm font-medium text-white">
                    {{ $field['label'] }}
                </label>
                @if(($field['type'] ?? 'text') === 'textarea')
                    <textarea
                        id="field_{{ $field['name'] }}"
                        wire:model.defer="fields.{{ $field['name'] }}"
                        placeholder="{{ $field['placeholder'] ?? '' }}"
                        class="mt-2 block w-full rounded-md border-0 py-1.5 px-3 text-white bg-gray-800
                               ring-1 ring-inset ring-gray-700 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6"
                    ></textarea>
                @else
                    <input
                        type="{{ ($field['type'] ?? 'text') === 'number' ? 'number' : 'text' }}"
                        id="field_{{ $field['name'] }}"
                        wire:model.defer="fields.{{ $field['name'] }}"
                        placeholder="{{ $field['placeholder'] ?? '' }}"
                        class="mt-2 block w-full rounded-md border-0 py-1.5 px-3 text-white bg-gray-800
                               ring-1 ring-inset ring-gray-700 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6"
                        @if(($field['type'] ?? 'text') === 'number')
                            style="appearance: textfield; -moz-appearance: textfield; -webkit-appearance: textfield;"
                        @endif
                    />
                @endif
                @error('fields.' . $field['name'])
                    <span class="text-sm text-red-200">{{ $message }}</span>
                @enderror
            </div>
        @endforeach

        @if(count($fieldDefinitions) > 0)
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
        @endif
    </form>
</div>
