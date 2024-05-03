<div class="max-w-md mx-auto">
    <ul role="list" class="divide-y divide-gray-100 dark:divide-gray-800">
    @foreach ($regos as $rego)
        <li wire:key="{{ $rego->id }}" class="flex justify-between gap-x-6 py-5">
            <div class="flex min-w-0 gap-x-4">
                <img class="rounded-full h-20 w-20 object-cover" src="{{ $rego->profile_photo_url }}" alt="{{ $rego->name }}">
                <div class="min-w-0 flex-auto">
                    <p class="text-sm font-semibold leading-6 text-gray-900 dark:text-white">{{ $rego->name }}</p>
                    <p class="mt-1 truncate text-xs leading-5 text-gray-500 dark:text-gray-400">Registered {{ $rego->rego_paid_at->diffForHumans() }}</p>
                </div>
            </div>
            <div class="hidden shrink-0 sm:flex sm:flex-col sm:items-end">
                <p class="text-sm leading-6 text-gray-900 dark:text-white">{{ $rego->kennel }}</p>
            @if ($rego->short_bus == 'Y')
                <p class="mt-1 truncate text-xs leading-5 text-gray-500 dark:text-gray-400">Short Bus Requested</p>
            @endif
            </div>
        </li>
    @endforeach
    </ul>
</div>
