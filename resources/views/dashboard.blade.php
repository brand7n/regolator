<x-app-layout>
    <x-rego-section>
        <x-slot name="title">Upcoming Events</x-slot>
        <x-slot name="content">
            @forelse($events as $event)
                <a href="{{ route('events.show', $event) }}" class="block mb-4 p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    <div class="flex items-center gap-4">
                        @if($event->event_photo_path)
                            <img class="h-24 w-24 object-cover rounded" src="{{ Storage::url($event->event_photo_path) }}" alt="{{ $event->name }}" />
                        @endif
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $event->name }}</h2>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ $event->starts_at->setTimezone('America/New_York')->format('F j, Y g:ia') }} - {{ $event->ends_at->setTimezone('America/New_York')->format('F j, Y g:ia') }}
                            </p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $event->location }}</p>
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">${{ $event->base_price_in_dollars }}/rego</p>
                        </div>
                    </div>
                </a>
            @empty
                <p class="text-gray-600 dark:text-gray-400">No upcoming events.</p>
            @endforelse
        </x-slot>
    </x-rego-section>
</x-app-layout>
