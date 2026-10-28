<x-app-layout>
    <x-rego-section>
        <x-slot name="title">{{ $kennel?->name ?? 'All Trail Maps' }}</x-slot>
        <x-slot name="content">
            @if($kennel)
                <p class="mb-4 text-sm">
                    <a href="{{ route('tracks.index', array_filter(['from' => $from, 'to' => $to])) }}" class="underline">&larr; All kennels</a>
                </p>
            @endif

            <form method="GET" action="{{ route('tracks.index') }}" class="mb-6 flex flex-wrap items-end gap-3">
                @if($kennel)
                    <input type="hidden" name="kennel" value="{{ $kennel->id }}">
                @endif

                <label class="text-sm text-gray-600 dark:text-gray-400">
                    From
                    <input type="date" name="from" value="{{ $from }}" class="mt-1 block rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 text-sm">
                </label>

                <label class="text-sm text-gray-600 dark:text-gray-400">
                    To
                    <input type="date" name="to" value="{{ $to }}" class="mt-1 block rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 text-sm">
                </label>

                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Filter</button>

                @if($from || $to)
                    <a href="{{ route('tracks.index', $kennel ? ['kennel' => $kennel->id] : []) }}" class="text-sm underline">Clear</a>
                @endif

                <span class="text-sm text-gray-600 dark:text-gray-400">
                    {{ $events->count() }} {{ Str::plural('event', $events->count()) }}
                    &middot; {{ $events->sum('tracks_count') }} {{ Str::plural('track', $events->sum('tracks_count')) }}
                </span>
            </form>

            @if($events->isNotEmpty())
                <div class="mb-6">
                    <livewire:location-map :kennel-id="$kennel?->id" :from="$from" :to="$to" />
                </div>
            @endif

            @if($kennel)
                @foreach($events as $event)
                    <a href="{{ route('events.show', $event) }}" class="block mb-4 p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $event->name }}</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $event->starts_at->setTimezone('America/New_York')->format('F j, Y') }}
                            &middot; {{ $event->location }}
                        </p>
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                            {{ $event->tracks_count }} {{ Str::plural('track', $event->tracks_count) }}
                        </p>
                    </a>
                @endforeach
            @else
                @forelse($kennels as $kennelEvents)
                    @php($groupKennel = $kennelEvents->first()->kennel)
                    <a href="{{ route('tracks.index', array_filter(['kennel' => $groupKennel?->id, 'from' => $from, 'to' => $to])) }}" class="block mb-4 p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $groupKennel?->name ?? 'Unassigned' }}</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $kennelEvents->count() }} {{ Str::plural('event', $kennelEvents->count()) }}
                            &middot; {{ $kennelEvents->sum('tracks_count') }} {{ Str::plural('track', $kennelEvents->sum('tracks_count')) }}
                            &middot; last on {{ $kennelEvents->first()->starts_at->setTimezone('America/New_York')->format('F j, Y') }}
                        </p>
                    </a>
                @empty
                    <p class="text-gray-600 dark:text-gray-400">No past events with uploaded tracks yet.</p>
                @endforelse
            @endif
        </x-slot>
    </x-rego-section>
</x-app-layout>
