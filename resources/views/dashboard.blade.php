@php
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Facades\Auth;

    $now = Carbon::now('America/New_York');
    $target = Carbon::createFromFormat('Y-m-d H:i:s', '2025-08-01 14:00:00', 'America/New_York');
    $totalSeconds = $now->diffInSeconds($target);
    $hoursRemaining = floor($totalSeconds / 3600);
    $minutesRemaining = floor(($totalSeconds % 3600) / 60);
@endphp

<x-app-layout>
    <x-rego-section>
        <x-slot name="title">NVHHH #1900: NITTANY CALLING</x-slot>
        <x-slot name="content">
                    <div class="flex-col items-center">
                    <img class="h-96" src="/nittanycalling.jpg" />
            <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
August 1, 2025 2pm - August 3, 2024 noon</p>
<div class="animate-bounce font-semibold text-gray-800 dark:text-gray-200">({{ $hoursRemaining }} hours and {{ $minutesRemaining }} minutes from now)</div>
            <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
<b>PLEASE READ CAREFULLY AS SOME POLICIES HAVE CHANGED</b></p>

            <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
Our Tyrant DADDY'S HOME...BITCH and NVHHH again bring you the East Coast’s premier hash camp-out, nestled between Sproul and Susquehannock State Forests on the mighty West Branch of the Susquehanna! This will be our 22nd year at the fabulous Loggia Giosue Carducci 146 Campground (aka The Sons).</p>

            <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
NOTE: Short Bus regos will be $60 CASH paid at the registration table. If you want to be part of the Short Bus crew (and miss the REAL SATURDAY WILDERNESS TRAIL HARED BY THE EX-TYRANTS OF NITTANY VALLEY) indicate your interest during registration, pay the regular rate, and you will be contacted with further details. Plaheeease be aware there is limited space and not everyone applying is guaranteed a seat on the Short Bus.</p>

            <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
WE LOVE DOGS BUT SADLY, NO DOGS ALLOWED AT THIS EVENT. WE CANNOT ACCOMMODATE LARGE POP-UP CAMPERS / TRAVEL TRAILERS / RVs ABOVE VAN-ISH SIZE. CAR CAMPING / TENT CAMPING IS PREFERRED. SCHEDULE OF EVENTS IS APPROXIMATE AND SUBJECT TO CHANGE.</p>

            <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
LOCATION:<br>
Sons of Italy Campground<br>
44 Sons Rd, Lock Haven, PA 17745</p>

            <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
DETAILS ON HIS GLORIOUS THEME: <a class="font-medium text-blue-600 dark:text-blue-500 hover:underline" href="https://en.wikipedia.org/wiki/London_Calling">NITTANY CALLING 😱</a></p>

            <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
COST: $165/REGO (Pay below ⬇️⬇️⬇️ after accepting our waiver)</p>
    </div>
        </x-slot>
    </x-rego-section>
@if(\App\Models\Event::findOrFail(1)->regoPaidAt(Auth::user()) === null)
    <x-rego-section>
        <x-slot name="title">YOU'VE UNLOCKED A SPECIAL DEAL</x-slot>
        <x-slot name="content">
            <p class="animate-bounce py-3 font-semibold text-gray-800 dark:text-gray-200">
                Pay for your EH3 rego now along with your NVHHH rego and receive a $15 discount. Just add the deal when you pay below and complete your EH3 rego here (skipping the payment part): <a class="font-medium text-blue-600 dark:text-blue-500 hover:underline" href="https://hashrego.com/events/eh3-eeries-32-analversary-at-brushwood-2025">EH3 32nd Analversary At Brushwood July 3-6</a>
            </p>
        </x-slot>
    </x-rego-section>
@endif
    <x-rego-section>
        <x-slot name="title">ACCEPT WAIVER/PAY UP</x-slot>
        <x-slot name="content">
            <livewire:paypal event-id="1" />
        </x-slot>
    </x-rego-section>
    <x-rego-section>
        <x-slot name="title">OH LAWD THEY COMIN'</x-slot>
        <x-slot name="content">
            <livewire:regos event-id="1" />
        </x-slot>
    </x-rego-section>
</x-app-layout>
