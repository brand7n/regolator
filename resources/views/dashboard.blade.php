<x-app-layout>
    <x-rego-section>
        <x-slot name="title">NVHHH #1900: NITTANY CALLING</x-slot>
        <x-slot name="content">
                    <div class="flex-col items-center">
                    <img class="h-96" src="/nittanycalling.jpg" />
            <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
August 1, 2025 2pm - August 3, 2024 noon</p>
<div class="animate-bounce font-semibold text-gray-800 dark:text-gray-200">({{ round(((\Illuminate\Support\Carbon::now())->diffInSeconds(new \Illuminate\Support\Carbon('2025-08-01 14:00:00')))/3600) }} hours from now)</div>
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
    <x-rego-section>
        <x-slot name="title">ACCEPT WAIVER/PAY UP</x-slot>
        <x-slot name="content">
            <livewire:paypal />
        </x-slot>
    </x-rego-section>
    <x-rego-section>
        <x-slot name="title">OH LAWD THEY COMIN'</x-slot>
        <x-slot name="content">
            <livewire:regos />
        </x-slot>
    </x-rego-section>
</x-app-layout>
