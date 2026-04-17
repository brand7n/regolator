<div class="flex justify-center">
    @assets
    <script src="https://www.{{ $sandbox }}paypal.com/sdk/js?client-id={{ $key }}&disable-funding=credit,card"></script>
    @endassets

    @script
    <script>
        let paypalRendered = false;

        window.renderButton = function() {
            requestAnimationFrame(() => {
                const container = document.getElementById('paypal-button-container');
                if (!container || container.offsetParent === null) return;

                if (paypalRendered) return;
                paypalRendered = true;

                const scrollY = window.scrollY;
                paypal.Buttons({
                    createOrder: function(data, actions) {
                        const price = ($wire.price / 100).toFixed(2);
                        const event = $wire.event_tag;
                        const name = $wire.name;

                        return actions.order.create({
                            intent: 'CAPTURE',
                            soft_descriptor: event,
                            purchase_units: [{
                                amount: {
                                    currency_code: "USD",
                                    value: price,
                                    breakdown: {
                                        item_total: {
                                            currency_code: "USD",
                                            value: price,
                                        }
                                    }
                                },
                                items: [{
                                    name: event + " rego for " + name,
                                    quantity: 1,
                                    unit_amount: {
                                        currency_code: "USD",
                                        value: price
                                    }
                                }]
                            }]
                        }).then(function(orderID) {
                            $wire.setOrderID(orderID).then(function () {
                                console.log("Order stored: ", orderID);
                            })
                            return orderID;
                        });
                    },

                    onApprove: function(data, actions) {
                        return actions.order.capture().then(function(details) {
                            $wire.approve(details).then(function () {
                                container.style.display = 'none';
                                $wire.$refresh();
                            });
                        });
                    },

                    onCancel: function(data, actions) {
                        $wire.cancel();
                    },

                    onError: function(err) {
                        $wire.error(err);
                    }
                }).render('#paypal-button-container').then(() => {
                    window.scrollTo({ top: scrollY });
                });
            });
        }

        Livewire.on('render-paypal', () => {
            renderButton();
        });
    </script>
    @endscript

    <div class="flex-col items-center">
        @if ($order && $rego_paid_at)
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Rego paid for {{ $name }} at {{ $rego_paid_at->timezone('US/Eastern')->toDateTimeString() }}.
            </h2>
            <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
                Verify your profile and edit rego preferences by clicking below.
            </p>
            <x-button class="py-3 items-center animate-bounce" wire:click="edit">Edit</x-button>
        @endif

        @if ($order && $order->status !== \App\Models\OrderStatus::Waitlisted)
            @if ($terms_accepted && !$rego_paid_at)
            <div class="space-y-4">
                @foreach($addonDefinitions as $addon)
                    <label class="flex items-center gap-2 font-semibold text-gray-800 dark:text-gray-200 cursor-pointer">
                        <input type="checkbox" wire:click="toggleAddon('{{ $addon['name'] }}')"
                            {{ ($selected_addons[$addon['name']] ?? false) ? 'checked' : '' }}
                            class="rounded border-gray-700 bg-gray-800 text-indigo-600 focus:ring-indigo-600">
                        {{ $addon['label'] }} (+${{ number_format($addon['price'] / 100, 2) }})
                    </label>
                @endforeach
                <p class="font-semibold text-gray-800 dark:text-gray-200">
                    TOTAL: ${{ number_format($price / 100, 2) }}
                </p>
            </div>
            @endif

            @if (!$terms_accepted && !$rego_paid_at)
                <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
                    Participating in hashing and hashing events is a potentially hazardous activity that could result in injury or death. I am participating in this event at my own risk and I assume all risk and responsibility for injuries I may incur as a direct or indirect result of my participating in this event.</p>

                <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
                    Having read this Waiver and knowing the risks involved in my participation in this event, I, for myself and anyone entitled to act on my behalf, waive and release the Nittany Valley Hash House Harriers, its sponsors, representatives, officers and management from all claims or liabilities of any kind arising out of my participation in this event, even though that liability may arise out of negligence or carelessness on the part of the persons or organizations named in this Waiver.</p>

                <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
                    Further, I agree to defend, indemnify and hold harmless the Nittany Valley Hash House Harriers, its sponsors, representatives, officers and management from any and all claims which may result from my participation in this event.</p>

                <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
                    I certify I have read this Waiver, I understand it, and I agree to its terms relating to the event or activity hosted Nittany Valley Hash House Harriers.</p>
                <x-button class="py-3 items-center" wire:click="accept_terms">I Accept</x-button>
                <x-button class="py-3 items-center bg-red-500 dark:bg-red-500" wire:click="decline">I Cannot Attend</x-button>
            @endif
        @endif

        @if (!$order && !$event->private)
            @if (!$terms_accepted && !$rego_paid_at)
                <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
                    Participating in hashing and hashing events is a potentially hazardous activity that could result in injury or death. I am participating in this event at my own risk and I assume all risk and responsibility for injuries I may incur as a direct or indirect result of my participating in this event.</p>

                <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
                    Having read this Waiver and knowing the risks involved in my participation in this event, I, for myself and anyone entitled to act on my behalf, waive and release the Nittany Valley Hash House Harriers, its sponsors, representatives, officers and management from all claims or liabilities of any kind arising out of my participation in this event, even though that liability may arise out of negligence or carelessness on the part of the persons or organizations named in this Waiver.</p>

                <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
                    Further, I agree to defend, indemnify and hold harmless the Nittany Valley Hash House Harriers, its sponsors, representatives, officers and management from any and all claims which may result from my participation in this event.</p>

                <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
                    I certify I have read this Waiver, I understand it, and I agree to its terms relating to the event or activity hosted Nittany Valley Hash House Harriers.</p>
                <x-button class="py-3 items-center" wire:click="accept_terms">I Accept</x-button>
                <x-button class="py-3 items-center bg-red-500 dark:bg-red-500" wire:click="decline">I Cannot Attend</x-button>
            @endif
        @elseif (!$order && $event->private)
            <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
                Interested in this event? Add yourself to the waitlist!
            </p>
            <x-button class="py-3 items-center" wire:click="waitlist">Join Waitlist</x-button>
        @endif

        @if ($order && in_array($order->status, [\App\Models\OrderStatus::Waitlisted, \App\Models\OrderStatus::Blocked]))
            <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
                You are on the waitlist for this event. Verify and edit your profile by clicking below.
            </p>
            <x-button class="py-3 items-center animate-bounce" wire:click="edit">Edit</x-button>
        @endif
        <div wire:ignore.self class="py-4" id="paypal-section">
            <div id="paypal-button-container"></div>
        </div>
    </div>
</div>
