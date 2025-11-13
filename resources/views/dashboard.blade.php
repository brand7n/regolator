@php
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Facades\Auth;

    $now = Carbon::now('America/New_York');
    $target = Carbon::createFromFormat('Y-m-d H:i:s', '2025-12-12 14:00:00', 'America/New_York');
    $totalSeconds = $now->diffInSeconds($target);
    $hoursRemaining = floor($totalSeconds / 3600);
    $minutesRemaining = floor(($totalSeconds % 3600) / 60);
@endphp

<x-app-layout>
    <x-rego-section>
        <x-slot name="title">Circle & Socket&apos;s Festive Holidaze Vessel Weekend </x-slot>
        <x-slot name="content">
                    <div class="flex-col items-center">
                    <img class="h-96" src="/holidaze2025.jpg" />
            <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
December 12, 2025 2pm - December 14, 2025 11:59am</p>
<div class="animate-bounce font-semibold text-gray-800 dark:text-gray-200">({{ $hoursRemaining }} hours and {{ $minutesRemaining }} minutes from now)</div>
            <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
<b>PLEASE READ CAREFULLY AS SOME POLICIES HAVE CHANGED</b></p>

            <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
Circle and Socket: we’re gonna do $35.69 — you get trail &amp; circle beer, a hashpitality-lite cabin, and the first 72 to sign up get a one-of-a-kind Holidaze vessel.</p>

            <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
LOCATION:<br>
Parallel Loop Cottages<br>
Allegany State Park, NY</p>

<div class="mb-4 text-center py-3 bg-red-950 border-2 border-red-500 rounded animate-pulse">
    <p class="text-2xl font-extrabold text-red-500 animate-bounce tracking-wider">
        ⚠️ MAKE SURE YOU HAVE CABIN SPACE BEFORE REGO-ING FOR THIS EVENT ⚠️
    </p>
</div>

            <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
COST: $35.69/REGO (Pay below ⬇️⬇️⬇️ after accepting our waiver)</p>
    </div>
        </x-slot>
    </x-rego-section>
    <x-rego-section>
        <x-slot name="title">LOCATION</x-slot>
        <x-slot name="content">
            <livewire:location-map event-id="2" />
        </x-slot>
    </x-rego-section>
    <x-rego-section>
         <x-slot name="title">EVENT INFO</x-slot>
         <x-slot name="content">
            <livewire:event-info event-id="2"/>
         </x-slot>
    </x-rego-section>
    <x-rego-section>
        <x-slot name="title">ACCEPT WAIVER/PAY UP</x-slot>
        <x-slot name="content">
            <livewire:paypal event-id="2" />
        </x-slot>
    </x-rego-section>
    <x-rego-section>
        <x-slot name="title">OH LAWD THEY COMIN'</x-slot>
        <x-slot name="content">
            <livewire:regos event-id="2" />
        </x-slot>
    </x-rego-section>
</x-app-layout>
