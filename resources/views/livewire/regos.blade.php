<div class="max-w-md mx-auto">
    <div>
        <select id="order" name="order"
            class="mt-2 block w-full rounded-md border-0 py-1.5 pl-3 pr-10 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6"
            wire:model.change="orderby">
            <option value="name">Name</option>
            <option value="rego_paid_at">Rego Time</option>
            <option value="kennel">Kennel</option>
        </select>
    </div>
    <ul role="list" class="divide-y divide-gray-100 dark:divide-gray-800">
    @foreach ($regos as $rego)
        <li wire:key="{{ $rego->id }}" class="flex justify-between gap-x-6 py-5">
            <div class="flex min-w-0 gap-x-4">
                <img class="rounded-full h-20 w-20 object-cover" src="{{ $rego->profile_photo_url }}" alt="{{ $rego->name }}">
                <div class="min-w-0 flex-auto">
                    <p class="text-sm font-semibold leading-6 text-gray-900 dark:text-white">{{ $rego->name }}</p>
                    <p class="mt-1 truncate text-xs leading-5 text-gray-500 dark:text-gray-400">Registered {{ $rego->rego_paid_at->setTimezone('US/Eastern')->diffForHumans() }}</p>
                </div>
            </div>
            <div class="hidden shrink-0 sm:flex sm:flex-col sm:items-end">
            @if ($rego->kennel !== null && strlen($rego->kennel > 0))
                <p class="text-sm leading-6 text-gray-900 dark:text-white">{{ $rego->kennel }}</p>
            @else
                <p class="text-sm leading-6 text-gray-900 dark:text-white"><br /></p>
            @endif
            @if ($rego->short_bus == 'Y')
                <p class="mt-1 truncate text-xs leading-5 text-gray-500 dark:text-gray-400">Short Bus Requested</p>
            @endif
            </div>
        </li>
    @endforeach
    </ul>
</div>
