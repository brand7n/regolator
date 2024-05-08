<x-app-layout>
    <x-rego-section>
        <x-slot name="title">NVHHH #1850</x-slot>
        <x-slot name="content">
                    <div class="flex-col items-center">
                    <img class="h-96" src="/_8b321d67-0654-46e7-90e1-666862d24f50.jpg" />
            <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
August 2, 2024 2pm - August 4, 2024 noon ({{ round(((\Illuminate\Support\Carbon::now())->diffInSeconds(new \Illuminate\Support\Carbon('2024-08-02 14:00:00')))/3600) }} hours from now)</p>
            <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
<b>PLEASE READ CAREFULLY AS SOME POLICIES HAVE CHANGED</b></p>

            <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
Our Tyrant DRAGNET and NVHHH again bring you the East Coast’s premier hash camp-out, nestled between Sproul and Susquehannock State Forests on the mighty West Branch of the Susquehanna! This will be our 21st year at the fabulous Loggia Giosue Carducci 146 Campground (aka The Sons).</p>

            <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
NOTE: Short Bus regos will be $50 CASH paid at the registration table. If you want to be part of the Short Bus crew (and miss the REAL SATURDAY WILDERNESS TRAIL HARED BY THE EX-TYRANTS OF NITTANY VALLEY) indicate your interest during registration, pay the regular rate, and you will be contacted with further details. Plaheeease be aware there is limited space and not everyone applying is guaranteed a seat on the Short Bus.</p>

            <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
WE LOVE DOGS BUT SADLY, NO DOGS ALLOWED AT THIS EVENT. WE CANNOT ACCOMMODATE LARGE POP-UP CAMPERS / TRAVEL TRAILERS / RVs ABOVE VAN-ISH SIZE. CAR CAMPING / TENT CAMPING IS PREFERRED. SCHEDULE OF EVENTS IS APPROXIMATE AND SUBJECT TO CHANGE.</p>

            <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
LOCATION:<br>
Sons of Italy Campground<br>
44 Sons Rd, Lock Haven, PA 17745</p>

            <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
DETAILS ON HIS GLORIOUS THEME: <a href="https://en.wikipedia.org/wiki/Dragnet_(franchise)">DRAGNET 😱</a></p>
        </p>
    </div>
        </x-slot>
    </x-rego-section>
    <x-rego-section>
        <x-slot name="title">ACCEPT WAVER/PAY UP</x-slot>
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
