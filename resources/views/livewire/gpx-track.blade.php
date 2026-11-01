<div class="font-semibold text-gray-800 dark:text-gray-200">
    @if($tracks->isEmpty())
        <p class="py-3">No tracks uploaded yet.</p>
    @endif

    @if($tracks->isNotEmpty())
        <ul class="py-3 space-y-2">
            @foreach($tracks as $track)
                <li class="flex items-center justify-between gap-4 rounded border border-gray-200 dark:border-gray-700 p-2">
                    <div>
                        <a href="{{ Storage::url($track->path) }}" download class="underline">{{ $track->name }}</a>
                        <p class="text-sm font-normal text-gray-600 dark:text-gray-400">
                            {{ $track->user?->name ?? 'Unknown' }}
                            &middot; {{ count($track->points ?? []) }} pts
                            &middot; {{ number_format($track->size / 1024) }} KB
                        </p>
                    </div>
                    @if($canDelete($track))
                        <button
                            wire:click="delete({{ $track->id }})"
                            wire:confirm="Delete {{ $track->name }}?"
                            wire:loading.attr="disabled"
                            class="text-sm font-normal text-red-600 dark:text-red-400"
                        >Delete</button>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if($canUpload)
        <form wire:submit="save" class="py-3">
            <input type="file" wire:model="gpx" accept=".gpx,application/gpx+xml"
                   class="block w-full text-sm font-normal text-gray-600 dark:text-gray-400">
            @error('gpx')
                <span class="text-sm font-normal text-red-600 dark:text-red-400">{{ $message }}</span>
            @enderror
            <button type="submit" wire:loading.attr="disabled"
                    class="mt-2 rounded bg-gray-800 px-4 py-2 text-white dark:bg-gray-200 dark:text-gray-900">
                <span wire:loading.remove wire:target="gpx">Upload track</span>
                <span wire:loading wire:target="gpx">Uploading&hellip;</span>
            </button>
        </form>
    @else
        <p class="py-3 text-sm font-normal text-gray-600 dark:text-gray-400">
            Anyone can upload a track once the hash has started.
        </p>
    @endif
</div>
