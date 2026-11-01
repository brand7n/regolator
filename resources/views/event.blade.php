@php
    use Illuminate\Support\Carbon;

    $now = Carbon::now('America/New_York');
    $startsAt = $event->starts_at->copy()->setTimezone('America/New_York');
    $endsAt = $event->ends_at->copy()->setTimezone('America/New_York');

    if ($now->lt($startsAt)) {
        $totalSeconds = $now->diffInSeconds($startsAt);
        $daysRemaining = floor($totalSeconds / 86400);
        $hoursRemaining = floor(($totalSeconds % 86400) / 3600);
        $minutesRemaining = floor(($totalSeconds % 3600) / 60);
    }
@endphp

<x-app-layout>
    <x-rego-section>
        <x-slot name="title">{{ $event->name }}</x-slot>
        <x-slot name="content">
            <div class="flex-col items-center">
                @if($event->event_photo_path)
                    <img class="h-96" src="{{ Storage::url($event->event_photo_path) }}" />
                @endif
                <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
                    {{ $startsAt->format('F j, Y g:ia') }} - {{ $endsAt->format('F j, Y g:ia') }}
                </p>
                @if(isset($totalSeconds))
                    <div class="animate-bounce font-semibold text-gray-800 dark:text-gray-200">
                        ({{ $daysRemaining > 0 ? $daysRemaining . ' days, ' : '' }}{{ $hoursRemaining }} hours and {{ $minutesRemaining }} minutes from now)
                    </div>
                @endif
                @if($event->description)
                    <div class="py-3 prose dark:prose-invert prose-strong:font-extrabold font-semibold text-gray-800 dark:text-gray-200">
                        {!! Str::markdown($event->description) !!}
                    </div>
                @endif
                <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
                    LOCATION:<br>
                    {{ $event->location }}
                </p>
                @if($event->creator)
                    <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
                        HOSTED BY: {{ $event->creator->name }}
                    </p>
                @endif
                <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
                    COST: ${{ $event->base_price_in_dollars }}/REGO
                    @if ($now->lt($endsAt))
                        (Pay below ⬇️⬇️⬇️ after accepting our waiver)
                    @endif
                </p>
            </div>
        </x-slot>
    </x-rego-section>
    @if ($event->hasCoordinates())
        <x-rego-section>
            <x-slot name="title">LOCATION</x-slot>
            <x-slot name="content">
                <livewire:location-map :event-id="$event->id" />
            </x-slot>
        </x-rego-section>
    @endif
    @if(!empty(data_get($event->properties, 'fields')))
    <x-rego-section>
        <x-slot name="title">EVENT INFO</x-slot>
        <x-slot name="content">
            <livewire:event-info :event-id="$event->id"/>
        </x-slot>
    </x-rego-section>
    @endif
    @if ($now->lt($endsAt))
        <x-rego-section>
            <x-slot name="title">ACCEPT WAIVER/PAY UP</x-slot>
            <x-slot name="content">
                <livewire:paypal :event-id="$event->id" />
            </x-slot>
        </x-rego-section>
    @endif
    <x-rego-section>
        <x-slot name="title">OH LAWD THEY COMIN'</x-slot>
        <x-slot name="content">
            <livewire:regos :event-id="$event->id" />
        </x-slot>
    </x-rego-section>
    @if ($now->gte($startsAt) || auth()->user()?->is_admin)
        <x-rego-section>
            <x-slot name="title">TRACKS</x-slot>
            <x-slot name="content">
                <livewire:gpx-track :event-id="$event->id" />
                @if ($event->tracks()->exists())
                    <div class="mt-6">
                        <livewire:tracks-map :event-id="$event->id" />
                    </div>
                @endif
            </x-slot>
        </x-rego-section>
    @endif
</x-app-layout>
